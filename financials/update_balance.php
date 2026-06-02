<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$baseFields = [
    'cash_and_equivalents',
    'accounts_receivable',
    'inventory',
    'other_current_assets',
    'fixed_assets',
    'other_non_current_assets',
    'short_term_debt',
    'accounts_payable',
    'other_current_liabilities',
    'long_term_debt',
    'other_non_current_liabilities',
    'equity',
];
$fields = [
    'cash_and_equivalents',
    'accounts_receivable',
    'inventory',
    'other_current_assets',
    'total_current_assets',
    'fixed_assets',
    'other_non_current_assets',
    'total_non_current_assets',
    'total_assets',
    'short_term_debt',
    'accounts_payable',
    'other_current_liabilities',
    'total_current_liabilities',
    'long_term_debt',
    'other_non_current_liabilities',
    'total_non_current_liabilities',
    'equity',
    'total_liabilities_and_equity',
];

function balance_error(string $message): never
{
    global $currentSection, $pageTitle;
    $currentSection = 'financials';
    $pageTitle = 'Balance sheet error';
    require dirname(__DIR__) . '/header.php';
    require dirname(__DIR__) . '/sidebar.php';
    render_error_page('Cannot update balance sheet', $message, url('financials/index.php'), 'Back to financial statements');
    require dirname(__DIR__) . '/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('financials/index.php'));
}
if (!$pdo instanceof PDO) {
    balance_error('Database connection is not available.');
}

$id = post_int('id');
if (!$id) {
    balance_error('Missing financial period ID.');
}

$statement = $pdo->prepare('SELECT id FROM financial_periods WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$id]);
if (!$statement->fetch()) {
    balance_error('The requested financial period was not found.');
}

$statement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?');
$statement->execute([$id]);
$old = $statement->fetch();
if (!$old) {
    balance_error('The balance sheet record was not found.');
}

$values = [];
foreach ($baseFields as $field) {
    $parsed = parse_decimal($_POST[$field] ?? null);
    if ($parsed === null) {
        balance_error('All editable balance sheet fields must be valid numbers.');
    }
    $values[$field] = $parsed;
}

$values = calculate_balance_sheet_totals($values);
$assignments = implode(', ', array_map(static fn ($field) => $field . ' = :' . $field, $fields));
$sql = 'UPDATE financial_balance_sheet SET ' . $assignments . ' WHERE financial_period_id = :financial_period_id';
$params = array_intersect_key($values, array_flip($fields));
$params['financial_period_id'] = $id;

$statement = $pdo->prepare($sql);
$statement->execute($params);

log_action($pdo, 'update balance sheet', 'balance_sheet', (int) $old['id'], $old, $params);
redirect(url('financials/view_period.php?id=' . $id));
