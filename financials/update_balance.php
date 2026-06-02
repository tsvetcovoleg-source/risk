<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$fields = ['cash_and_equivalents','accounts_receivable','inventory','other_current_assets','total_current_assets','fixed_assets','other_non_current_assets','total_non_current_assets','total_assets','short_term_debt','accounts_payable','other_current_liabilities','total_current_liabilities','long_term_debt','other_non_current_liabilities','total_non_current_liabilities','equity','total_liabilities_and_equity'];
function balance_error(string $message): never { global $currentSection,$pageTitle; $currentSection='financials'; $pageTitle='Balance sheet error'; require dirname(__DIR__).'/header.php'; require dirname(__DIR__).'/sidebar.php'; render_error_page('Cannot update balance sheet',$message,url('financials/index.php'),'Back to financial statements'); require dirname(__DIR__).'/footer.php'; exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(url('financials/index.php')); }
if (!$pdo instanceof PDO) { balance_error('Database connection is not available.'); }
$id = post_int('id'); if (!$id) { balance_error('Missing financial period ID.'); }
$s = $pdo->prepare('SELECT id FROM financial_periods WHERE id = ? AND deleted_at IS NULL'); $s->execute([$id]); if (!$s->fetch()) { balance_error('The requested financial period was not found.'); }
$s = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?'); $s->execute([$id]); $old = $s->fetch(); if (!$old) { balance_error('The balance sheet record was not found.'); }
$values = []; foreach ($fields as $field) { $parsed = parse_decimal($_POST[$field] ?? null); if ($parsed === null) { balance_error('All balance sheet fields must be valid numbers.'); } $values[$field] = $parsed; }
$assignments = implode(', ', array_map(fn($field) => $field . ' = :' . $field, $fields));
$sql = 'UPDATE financial_balance_sheet SET ' . $assignments . ' WHERE financial_period_id = :financial_period_id';
$values['financial_period_id'] = $id; $s = $pdo->prepare($sql); $s->execute($values);
log_action($pdo, 'update balance sheet', 'balance_sheet', (int) $old['id'], $old, $values);
redirect(url('financials/view_period.php?id=' . $id));
