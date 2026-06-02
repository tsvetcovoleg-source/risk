<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'financials';
$pageTitle = 'Edit balance sheet';
$id = get_int_param('id');
$period = null;
$balance = null;
$assetFields = [
    'cash_and_equivalents' => ['label' => 'Cash and equivalents', 'calculated' => false],
    'accounts_receivable' => ['label' => 'Accounts receivable', 'calculated' => false],
    'inventory' => ['label' => 'Inventory', 'calculated' => false],
    'other_current_assets' => ['label' => 'Other current assets', 'calculated' => false],
    'total_current_assets' => ['label' => 'Total current assets', 'calculated' => true],
    'fixed_assets' => ['label' => 'Fixed assets', 'calculated' => false],
    'other_non_current_assets' => ['label' => 'Other non-current assets', 'calculated' => false],
    'total_non_current_assets' => ['label' => 'Total non-current assets', 'calculated' => true],
    'total_assets' => ['label' => 'Total assets', 'calculated' => true],
];
$liabilityFields = [
    'short_term_debt' => ['label' => 'Short-term debt', 'calculated' => false],
    'accounts_payable' => ['label' => 'Accounts payable', 'calculated' => false],
    'other_current_liabilities' => ['label' => 'Other current liabilities', 'calculated' => false],
    'total_current_liabilities' => ['label' => 'Total current liabilities', 'calculated' => true],
    'long_term_debt' => ['label' => 'Long-term debt', 'calculated' => false],
    'other_non_current_liabilities' => ['label' => 'Other non-current liabilities', 'calculated' => false],
    'total_non_current_liabilities' => ['label' => 'Total non-current liabilities', 'calculated' => true],
    'equity' => ['label' => 'Equity', 'calculated' => false],
    'total_liabilities_and_equity' => ['label' => 'Total liabilities and equity', 'calculated' => true],
];

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT fp.*, ca.application_number, c.client_name FROM financial_periods fp INNER JOIN credit_applications ca ON ca.id = fp.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE fp.id = ? AND fp.deleted_at IS NULL');
    $statement->execute([$id]);
    $period = $statement->fetch();
    if ($period) {
        $statement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?');
        $statement->execute([$id]);
        $balance = $statement->fetch();
        $balance = $balance ? calculate_balance_sheet_totals($balance) : null;
    }
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$period || !$balance) {
    render_error_page('Balance sheet not found', 'The requested period or balance sheet record does not exist.', url('financials/index.php'), 'Back to financial statements');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Financial statements</p><h1 class="h2 mb-0">Edit balance sheet</h1></section>
<div class="alert alert-info border-0 shadow-sm">Enter only base balance sheet lines. Total rows are calculated automatically in the browser for review and recalculated again on the server before saving.</div>
<form method="post" action="<?= e(url('financials/update_balance.php')) ?>">
    <input type="hidden" name="id" value="<?= e($period['id']) ?>">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-1"><?= e($period['period_label']) ?></h2><p class="text-secondary mb-0"><?= e($period['application_number']) ?> · <?= e($period['client_name']) ?></p></div></div>
    <div class="row g-4">
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Assets</h2><div class="row g-3">
            <?php foreach ($assetFields as $field => $meta): ?>
                <div class="col-12">
                    <label class="form-label" for="<?= e($field) ?>"><?= e($meta['label']) ?><?php if ($meta['calculated']): ?> <span class="badge text-bg-light">Calculated field</span><?php endif; ?></label>
                    <input class="form-control financial-input<?= $meta['calculated'] ? ' bg-light fw-semibold' : '' ?>" type="number" step="0.01" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($balance[$field]) ?>" <?= $meta['calculated'] ? 'readonly' : 'required' ?>>
                </div>
            <?php endforeach; ?>
        </div></div></div></div>
        <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Liabilities and equity</h2><div class="row g-3">
            <?php foreach ($liabilityFields as $field => $meta): ?>
                <div class="col-12">
                    <label class="form-label" for="<?= e($field) ?>"><?= e($meta['label']) ?><?php if ($meta['calculated']): ?> <span class="badge text-bg-light">Calculated field</span><?php endif; ?></label>
                    <input class="form-control financial-input<?= $meta['calculated'] ? ' bg-light fw-semibold' : '' ?>" type="number" step="0.01" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($balance[$field]) ?>" <?= $meta['calculated'] ? 'readonly' : 'required' ?>>
                </div>
            <?php endforeach; ?>
        </div></div></div></div>
    </div>
    <div class="card border-0 shadow-sm my-4"><div class="card-body"><h2 class="h6 mb-2">Balance control</h2><div id="balance-check" class="fw-semibold"></div><div class="small text-secondary mt-1">Difference: <span id="balance-difference">0.00</span></div></div></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save balance sheet</button><a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">Cancel</a></div>
</form>
<script>
function financialValue(id){return parseFloat(String(document.getElementById(id).value || '0').replace(/\s/g,'').replace(',','.')) || 0;}
function setFinancialValue(id,value){document.getElementById(id).value = value.toFixed(2);}
function updateBalanceTotals(){
    const totalCurrentAssets = financialValue('cash_and_equivalents') + financialValue('accounts_receivable') + financialValue('inventory') + financialValue('other_current_assets');
    const totalNonCurrentAssets = financialValue('fixed_assets') + financialValue('other_non_current_assets');
    const totalAssets = totalCurrentAssets + totalNonCurrentAssets;
    const totalCurrentLiabilities = financialValue('short_term_debt') + financialValue('accounts_payable') + financialValue('other_current_liabilities');
    const totalNonCurrentLiabilities = financialValue('long_term_debt') + financialValue('other_non_current_liabilities');
    const totalLiabilitiesAndEquity = totalCurrentLiabilities + totalNonCurrentLiabilities + financialValue('equity');

    setFinancialValue('total_current_assets', totalCurrentAssets);
    setFinancialValue('total_non_current_assets', totalNonCurrentAssets);
    setFinancialValue('total_assets', totalAssets);
    setFinancialValue('total_current_liabilities', totalCurrentLiabilities);
    setFinancialValue('total_non_current_liabilities', totalNonCurrentLiabilities);
    setFinancialValue('total_liabilities_and_equity', totalLiabilitiesAndEquity);

    const difference = totalAssets - totalLiabilitiesAndEquity;
    const box = document.getElementById('balance-check');
    document.getElementById('balance-difference').textContent = difference.toFixed(2);
    box.textContent = Math.abs(difference) <= 0.01 ? 'Balanced' : 'Not balanced';
    box.className = Math.abs(difference) <= 0.01 ? 'fw-semibold text-success' : 'fw-semibold text-danger';
}
document.querySelectorAll('.financial-input:not([readonly])').forEach(el => el.addEventListener('input', updateBalanceTotals));
updateBalanceTotals();
</script>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
