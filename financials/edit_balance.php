<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
$currentSection = 'financials'; $pageTitle = 'Edit balance sheet'; $id = get_int_param('id'); $period = null; $balance = null;
$assetFields = ['cash_and_equivalents' => 'Cash and equivalents', 'accounts_receivable' => 'Accounts receivable', 'inventory' => 'Inventory', 'other_current_assets' => 'Other current assets', 'total_current_assets' => 'Total current assets', 'fixed_assets' => 'Fixed assets', 'other_non_current_assets' => 'Other non-current assets', 'total_non_current_assets' => 'Total non-current assets', 'total_assets' => 'Total assets'];
$liabilityFields = ['short_term_debt' => 'Short-term debt', 'accounts_payable' => 'Accounts payable', 'other_current_liabilities' => 'Other current liabilities', 'total_current_liabilities' => 'Total current liabilities', 'long_term_debt' => 'Long-term debt', 'other_non_current_liabilities' => 'Other non-current liabilities', 'total_non_current_liabilities' => 'Total non-current liabilities', 'equity' => 'Equity', 'total_liabilities_and_equity' => 'Total liabilities and equity'];
if ($id && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT fp.*, ca.application_number, c.client_name FROM financial_periods fp INNER JOIN credit_applications ca ON ca.id = fp.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE fp.id = ? AND fp.deleted_at IS NULL'); $s->execute([$id]); $period = $s->fetch(); if ($period) { $s = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?'); $s->execute([$id]); $balance = $s->fetch(); } }
require_once dirname(__DIR__) . '/header.php'; require_once dirname(__DIR__) . '/sidebar.php';
if (!$period || !$balance) { render_error_page('Balance sheet not found', 'The requested period or balance sheet record does not exist.', url('financials/index.php'), 'Back to financial statements'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Financial statements</p><h1 class="h2 mb-0">Edit balance sheet</h1></section>
<div class="alert alert-info border-0 shadow-sm">Values are entered in MDL unless another internal process explicitly defines a different reporting currency. Total fields are not auto-filled.</div>
<form method="post" action="<?= e(url('financials/update_balance.php')) ?>">
    <input type="hidden" name="id" value="<?= e($period['id']) ?>">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-1"><?= e($period['period_label']) ?></h2><p class="text-secondary mb-0"><?= e($period['application_number']) ?> · <?= e($period['client_name']) ?></p></div></div>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Assets</h2><div class="row g-3"><?php foreach ($assetFields as $field => $label): ?><div class="col-12"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label><input class="form-control financial-input" type="number" step="0.01" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($balance[$field]) ?>" required></div><?php endforeach; ?></div></div></div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Liabilities and equity</h2><div class="row g-3"><?php foreach ($liabilityFields as $field => $label): ?><div class="col-12"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label><input class="form-control financial-input" type="number" step="0.01" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($balance[$field]) ?>" required></div><?php endforeach; ?></div></div></div></div>
    </div>
    <div class="card border-0 shadow-sm my-4"><div class="card-body"><h2 class="h6 mb-2">Balance control</h2><div id="balance-check" class="fw-semibold"></div></div></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save balance sheet</button><a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">Cancel</a></div>
</form>
<script>
function updateBalanceCheck(){const a=parseFloat(document.getElementById('total_assets').value||0);const l=parseFloat(document.getElementById('total_liabilities_and_equity').value||0);const d=(a-l).toFixed(2);const box=document.getElementById('balance-check');box.textContent=Math.abs(d)<=0.01?'Balanced':'Not balanced. Difference: '+d;box.className=Math.abs(d)<=0.01?'fw-semibold text-success':'fw-semibold text-danger';}
document.querySelectorAll('.financial-input').forEach(el=>el.addEventListener('input',updateBalanceCheck));updateBalanceCheck();
</script>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
