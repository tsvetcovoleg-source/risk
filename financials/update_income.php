<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$fields = ['revenue','cost_of_goods_sold','gross_profit','operating_expenses','ebitda','depreciation_amortization','ebit','interest_expense','profit_before_tax','tax_expense','net_profit'];
function income_error(string $message): never { global $currentSection,$pageTitle; $currentSection='financials'; $pageTitle='Income statement error'; require dirname(__DIR__).'/header.php'; require dirname(__DIR__).'/sidebar.php'; render_error_page('Cannot update income statement',$message,url('financials/index.php'),'Back to financial statements'); require dirname(__DIR__).'/footer.php'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(url('financials/index.php')); }
if (!$pdo instanceof PDO) { income_error('Database connection is not available.'); }
$id = post_int('id'); if (!$id) { income_error('Missing financial period ID.'); }
$s = $pdo->prepare('SELECT id FROM financial_periods WHERE id = ? AND deleted_at IS NULL'); $s->execute([$id]); if (!$s->fetch()) { income_error('The requested financial period was not found.'); }
$s = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ?'); $s->execute([$id]); $old = $s->fetch(); if (!$old) { income_error('The income statement record was not found.'); }
$values = []; foreach ($fields as $field) { $parsed = parse_decimal($_POST[$field] ?? null); if ($parsed === null) { income_error('All income statement fields must be valid numbers.'); } $values[$field] = $parsed; }
$assignments = implode(', ', array_map(fn($field) => $field . ' = :' . $field, $fields));
$sql = 'UPDATE financial_income_statement SET ' . $assignments . ' WHERE financial_period_id = :financial_period_id';
$values['financial_period_id'] = $id; $s = $pdo->prepare($sql); $s->execute($values);
log_action($pdo, 'update income statement', 'income_statement', (int) $old['id'], $old, $values);
redirect(url('financials/view_period.php?id=' . $id));
