<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$periodTypes = ['annual', 'quarterly', 'interim', 'management'];
$dataSources = ['official_financial_statements', 'management_accounts', 'tax_reports', 'manual_input', 'other'];
function update_period_error(string $message): never
{
    global $currentSection, $pageTitle;
    $currentSection = 'financials'; $pageTitle = 'Financial period error';
    require dirname(__DIR__) . '/header.php'; require dirname(__DIR__) . '/sidebar.php';
    render_error_page('Cannot update financial period', $message, url('financials/index.php'), 'Back to financial statements');
    require dirname(__DIR__) . '/footer.php'; exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(url('financials/index.php')); }
if (!$pdo instanceof PDO) { update_period_error('Database connection is not available.'); }
$id = post_int('id');
$periodType = clean_input($_POST['period_type'] ?? '');
$periodEndDate = clean_input($_POST['period_end_date'] ?? '');
$periodLabel = clean_input($_POST['period_label'] ?? '');
$isAudited = isset($_POST['is_audited']) ? 1 : 0;
$dataSource = clean_input($_POST['data_source'] ?? 'manual_input');
$notes = nullable_input($_POST['notes'] ?? null);
if (!$id || $periodLabel === '' || !validate_date($periodEndDate) || !in_array($periodType, $periodTypes, true) || !in_array($dataSource, $dataSources, true)) { update_period_error('Please provide valid financial period data.'); }
$statement = $pdo->prepare('SELECT * FROM financial_periods WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$id]);
$old = $statement->fetch();
if (!$old) { update_period_error('The requested financial period was not found.'); }
$new = ['period_type' => $periodType, 'period_end_date' => $periodEndDate, 'period_label' => $periodLabel, 'is_audited' => $isAudited, 'data_source' => $dataSource, 'notes' => $notes];
$statement = $pdo->prepare('UPDATE financial_periods SET period_type = ?, period_end_date = ?, period_label = ?, is_audited = ?, data_source = ?, notes = ? WHERE id = ?');
$statement->execute([$periodType, $periodEndDate, $periodLabel, $isAudited, $dataSource, $notes, $id]);
log_action($pdo, 'update financial period', 'financial_period', $id, $old, $new);
redirect(url('financials/view_period.php?id=' . $id));
