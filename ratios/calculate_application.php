<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Ratios can be calculated only via POST.');
}

$applicationId = post_int('application_id');
if (!$applicationId || !($pdo instanceof PDO)) {
    exit('Missing or invalid application ID.');
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare('SELECT * FROM credit_applications WHERE id = ? AND deleted_at IS NULL');
    $statement->execute([$applicationId]);
    $application = $statement->fetch();
    if (!$application) {
        throw new RuntimeException('Credit application was not found or was deleted.');
    }

    $statement = $pdo->prepare('SELECT * FROM financial_periods WHERE application_id = ? AND deleted_at IS NULL ORDER BY period_end_date ASC, id ASC');
    $statement->execute([$applicationId]);
    $periods = $statement->fetchAll();
    if (!$periods) {
        throw new RuntimeException('No financial periods are available for this application.');
    }

    $columns = array_keys(ratio_columns());
    $assignments = implode(', ', array_map(static fn ($column) => "$column = VALUES($column)", $columns));
    $upsert = $pdo->prepare(
        'INSERT INTO financial_ratios (application_id, financial_period_id, ' . implode(', ', $columns) . ', calculated_at)
         VALUES (:application_id, :financial_period_id, :' . implode(', :', $columns) . ', NOW())
         ON DUPLICATE KEY UPDATE ' . $assignments . ', calculated_at = NOW(), updated_at = CURRENT_TIMESTAMP'
    );
    $balanceStatement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?');
    $incomeStatement = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ?');
    $existingStatement = $pdo->prepare('SELECT * FROM financial_ratios WHERE financial_period_id = ? LIMIT 1');

    $previousIncome = null;
    $newValues = [];
    foreach ($periods as $period) {
        $balanceStatement->execute([$period['id']]);
        $balance = $balanceStatement->fetch() ?: [];
        $balance = $balance ? persist_balance_sheet_totals($pdo, $balance) : [];

        $incomeStatement->execute([$period['id']]);
        $income = $incomeStatement->fetch() ?: [];
        $income = $income ? persist_income_statement_totals($pdo, $income) : [];

        $existingStatement->execute([$period['id']]);
        $oldRatio = $existingStatement->fetch() ?: null;

        $ratios = calculate_financial_ratios($balance, $income, $previousIncome);
        $params = ['application_id' => $applicationId, 'financial_period_id' => $period['id']];
        foreach ($columns as $column) {
            $params[$column] = $ratios[$column] ?? null;
        }
        $upsert->execute($params);
        $newValues[$period['id']] = $ratios;

        $existingStatement->execute([$period['id']]);
        $newRatio = $existingStatement->fetch();
        if ($newRatio) {
            log_action($pdo, 'calculate_ratios', 'financial_ratios', (int) $newRatio['id'], $oldRatio, $ratios);
        }

        $previousIncome = $income ?: null;
    }

    log_action($pdo, 'calculate_ratios_for_application', 'application', $applicationId, null, $newValues);

    $pdo->commit();
    redirect(url('applications/view.php?id=' . $applicationId));
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo 'Unable to calculate financial ratios for application: ' . e($exception->getMessage());
}
