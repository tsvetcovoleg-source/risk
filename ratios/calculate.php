<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Ratios can be calculated only via POST.');
}

$financialPeriodId = post_int('financial_period_id');
if (!$financialPeriodId || !($pdo instanceof PDO)) {
    exit('Missing or invalid financial period ID.');
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare(
        'SELECT fp.*, ca.id AS application_id
         FROM financial_periods fp
         INNER JOIN credit_applications ca ON ca.id = fp.application_id AND ca.deleted_at IS NULL
         WHERE fp.id = ? AND fp.deleted_at IS NULL'
    );
    $statement->execute([$financialPeriodId]);
    $period = $statement->fetch();

    if (!$period) {
        throw new RuntimeException('Financial period was not found or was deleted.');
    }

    $statement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?');
    $statement->execute([$financialPeriodId]);
    $balance = $statement->fetch() ?: [];

    $statement = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ?');
    $statement->execute([$financialPeriodId]);
    $income = $statement->fetch() ?: [];

    $statement = $pdo->prepare(
        'SELECT i.*
         FROM financial_periods fp
         INNER JOIN financial_income_statement i ON i.financial_period_id = fp.id
         WHERE fp.application_id = ? AND fp.deleted_at IS NULL AND fp.period_end_date < ?
         ORDER BY fp.period_end_date DESC, fp.id DESC
         LIMIT 1'
    );
    $statement->execute([$period['application_id'], $period['period_end_date']]);
    $previousIncome = $statement->fetch() ?: null;

    $ratios = calculate_financial_ratios($balance, $income, $previousIncome);
    $columns = array_keys(ratio_columns());

    $statement = $pdo->prepare('SELECT * FROM financial_ratios WHERE financial_period_id = ? LIMIT 1');
    $statement->execute([$financialPeriodId]);
    $oldRatio = $statement->fetch() ?: null;

    $assignments = implode(', ', array_map(static fn ($column) => "$column = VALUES($column)", $columns));
    $sql = 'INSERT INTO financial_ratios (application_id, financial_period_id, ' . implode(', ', $columns) . ', calculated_at)
            VALUES (:application_id, :financial_period_id, :' . implode(', :', $columns) . ', NOW())
            ON DUPLICATE KEY UPDATE ' . $assignments . ', calculated_at = NOW(), updated_at = CURRENT_TIMESTAMP';
    $statement = $pdo->prepare($sql);
    $params = [
        'application_id' => $period['application_id'],
        'financial_period_id' => $financialPeriodId,
    ];
    foreach ($columns as $column) {
        $params[$column] = $ratios[$column] ?? null;
    }
    $statement->execute($params);

    $statement = $pdo->prepare('SELECT * FROM financial_ratios WHERE financial_period_id = ? LIMIT 1');
    $statement->execute([$financialPeriodId]);
    $newRatio = $statement->fetch();

    if ($newRatio) {
        log_action($pdo, 'calculate_ratios', 'financial_ratios', (int) $newRatio['id'], $oldRatio, $ratios);
    }

    $pdo->commit();
    redirect(url('financials/view_period.php?id=' . $financialPeriodId));
} catch (Throwable $exception) {
    if ($pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo 'Unable to calculate financial ratios: ' . e($exception->getMessage());
}
