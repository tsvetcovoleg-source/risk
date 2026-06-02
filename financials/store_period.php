<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$periodTypes = ['annual', 'quarterly', 'interim', 'management'];
$dataSources = ['official_financial_statements', 'management_accounts', 'tax_reports', 'manual_input', 'other'];

function financial_error(string $message): never
{
    global $currentSection, $pageTitle;
    $currentSection = 'financials';
    $pageTitle = 'Financial period error';
    require dirname(__DIR__) . '/header.php';
    require dirname(__DIR__) . '/sidebar.php';
    render_error_page('Cannot save financial period', $message, url('financials/create_period.php'), 'Back to form');
    require dirname(__DIR__) . '/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('financials/create_period.php'));
}
if (!$pdo instanceof PDO) {
    financial_error('Database connection is not available.');
}

$applicationId = post_int('application_id');
$periodType = clean_input($_POST['period_type'] ?? '');
$periodEndDate = clean_input($_POST['period_end_date'] ?? '');
$periodLabel = clean_input($_POST['period_label'] ?? '');
$isAudited = isset($_POST['is_audited']) ? 1 : 0;
$dataSource = clean_input($_POST['data_source'] ?? 'manual_input');
$notes = nullable_input($_POST['notes'] ?? null);

if (!$applicationId || $periodLabel === '' || !validate_date($periodEndDate) || !in_array($periodType, $periodTypes, true) || !in_array($dataSource, $dataSources, true)) {
    financial_error('Please provide a valid application, period type, period end date, period label, and data source.');
}

$statement = $pdo->prepare('SELECT id FROM credit_applications WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$applicationId]);
if (!$statement->fetch()) {
    financial_error('The selected credit application was not found.');
}

try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('INSERT INTO financial_periods (application_id, period_type, period_end_date, period_label, is_audited, data_source, notes) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $statement->execute([$applicationId, $periodType, $periodEndDate, $periodLabel, $isAudited, $dataSource, $notes]);
    $periodId = (int) $pdo->lastInsertId();

    $statement = $pdo->prepare('INSERT INTO financial_balance_sheet (financial_period_id) VALUES (?)');
    $statement->execute([$periodId]);
    $balanceId = (int) $pdo->lastInsertId();

    $statement = $pdo->prepare('INSERT INTO financial_income_statement (financial_period_id) VALUES (?)');
    $statement->execute([$periodId]);
    $incomeId = (int) $pdo->lastInsertId();

    log_action($pdo, 'create financial period', 'financial_period', $periodId, null, [
        'application_id' => $applicationId,
        'period_type' => $periodType,
        'period_end_date' => $periodEndDate,
        'period_label' => $periodLabel,
        'is_audited' => $isAudited,
        'data_source' => $dataSource,
        'notes' => $notes,
        'balance_sheet_id' => $balanceId,
        'income_statement_id' => $incomeId,
    ]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    financial_error(APP_DEBUG ? $exception->getMessage() : 'Financial period could not be created.');
}

redirect(url('financials/view_period.php?id=' . $periodId));
