<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$baseFields = [
    'revenue',
    'cost_of_goods_sold',
    'operating_expenses',
    'depreciation_amortization',
    'interest_expense',
    'tax_expense',
];
$fields = [
    'revenue',
    'cost_of_goods_sold',
    'gross_profit',
    'operating_expenses',
    'ebitda',
    'depreciation_amortization',
    'ebit',
    'interest_expense',
    'profit_before_tax',
    'tax_expense',
    'net_profit',
];

function income_error(string $message): never
{
    global $currentSection, $pageTitle;
    $currentSection = 'financials';
    $pageTitle = 'Income statement error';
    require dirname(__DIR__) . '/header.php';
    require dirname(__DIR__) . '/sidebar.php';
    render_error_page('Cannot update income statement', $message, url('financials/index.php'), 'Back to financial statements');
    require dirname(__DIR__) . '/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('financials/index.php'));
}
if (!$pdo instanceof PDO) {
    income_error('Database connection is not available.');
}

$id = post_int('id');
if (!$id) {
    income_error('Missing financial period ID.');
}

$statement = $pdo->prepare('SELECT id FROM financial_periods WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$id]);
if (!$statement->fetch()) {
    income_error('The requested financial period was not found.');
}

$statement = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ?');
$statement->execute([$id]);
$old = $statement->fetch();
if (!$old) {
    income_error('The income statement record was not found.');
}

$values = [];
foreach ($baseFields as $field) {
    $parsed = parse_decimal($_POST[$field] ?? null);
    if ($parsed === null) {
        income_error('All editable income statement fields must be valid numbers.');
    }
    $values[$field] = $parsed;
}

$values = calculate_income_statement_totals($values);
$assignments = implode(', ', array_map(static fn ($field) => $field . ' = :' . $field, $fields));
$sql = 'UPDATE financial_income_statement SET ' . $assignments . ' WHERE financial_period_id = :financial_period_id';
$params = array_intersect_key($values, array_flip($fields));
$params['financial_period_id'] = $id;

$statement = $pdo->prepare($sql);
$statement->execute($params);

log_action($pdo, 'update income statement', 'income_statement', (int) $old['id'], $old, $params);
redirect(url('financials/view_period.php?id=' . $id));
