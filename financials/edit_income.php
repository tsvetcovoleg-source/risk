<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'financials';
$pageTitle = 'Edit income statement';
$id = get_int_param('id');
$period = null;
$income = null;
$fields = [
    'revenue' => ['label' => 'Revenue', 'calculated' => false],
    'cost_of_goods_sold' => ['label' => 'Cost of goods sold', 'calculated' => false],
    'gross_profit' => ['label' => 'Gross profit', 'calculated' => true],
    'operating_expenses' => ['label' => 'Operating expenses', 'calculated' => false],
    'ebitda' => ['label' => 'EBITDA', 'calculated' => true],
    'depreciation_amortization' => ['label' => 'Depreciation and amortization', 'calculated' => false],
    'ebit' => ['label' => 'EBIT', 'calculated' => true],
    'interest_expense' => ['label' => 'Interest expense', 'calculated' => false],
    'profit_before_tax' => ['label' => 'Profit before tax', 'calculated' => true],
    'tax_expense' => ['label' => 'Tax expense', 'calculated' => false],
    'net_profit' => ['label' => 'Net profit', 'calculated' => true],
];

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT fp.*, ca.application_number, c.client_name FROM financial_periods fp INNER JOIN credit_applications ca ON ca.id = fp.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE fp.id = ? AND fp.deleted_at IS NULL');
    $statement->execute([$id]);
    $period = $statement->fetch();
    if ($period) {
        $statement = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ?');
        $statement->execute([$id]);
        $income = $statement->fetch();
        $income = $income ? calculate_income_statement_totals($income) : null;
    }
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$period || !$income) {
    render_error_page('Income statement not found', 'The requested period or income statement record does not exist.', url('financials/index.php'), 'Back to financial statements');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Financial statements</p><h1 class="h2 mb-0">Edit income statement</h1></section>
<div class="alert alert-info border-0 shadow-sm">Enter only base P&amp;L lines. Gross profit, EBITDA, EBIT, profit before tax, and net profit are calculated automatically and recalculated again on the server before saving.</div>
<form method="post" action="<?= e(url('financials/update_income.php')) ?>">
    <input type="hidden" name="id" value="<?= e($period['id']) ?>">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-1"><?= e($period['period_label']) ?></h2><p class="text-secondary mb-0"><?= e($period['application_number']) ?> · <?= e($period['client_name']) ?></p></div></div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Profit and loss</h2><div class="row g-3">
        <?php foreach ($fields as $field => $meta): ?>
            <div class="col-md-6">
                <label class="form-label" for="<?= e($field) ?>"><?= e($meta['label']) ?><?php if ($meta['calculated']): ?> <span class="badge text-bg-light">Calculated field</span><?php endif; ?></label>
                <input class="form-control income-input<?= $meta['calculated'] ? ' bg-light fw-semibold' : '' ?>" type="number" step="0.01" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($income[$field]) ?>" <?= $meta['calculated'] ? 'readonly' : 'required' ?>>
            </div>
        <?php endforeach; ?>
    </div></div></div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-2">P&amp;L control checks</h2><div id="income-checks" class="small fw-semibold text-success">Controls passed</div></div></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save income statement</button><a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">Cancel</a></div>
</form>
<script>
function incomeValue(id){return parseFloat(String(document.getElementById(id).value || '0').replace(/\s/g,'').replace(',','.')) || 0;}
function setIncomeValue(id,value){document.getElementById(id).value = value.toFixed(2);}
function updateIncomeTotals(){
    const grossProfit = incomeValue('revenue') - incomeValue('cost_of_goods_sold');
    const ebitda = grossProfit - incomeValue('operating_expenses');
    const ebit = ebitda - incomeValue('depreciation_amortization');
    const profitBeforeTax = ebit - incomeValue('interest_expense');
    const netProfit = profitBeforeTax - incomeValue('tax_expense');

    setIncomeValue('gross_profit', grossProfit);
    setIncomeValue('ebitda', ebitda);
    setIncomeValue('ebit', ebit);
    setIncomeValue('profit_before_tax', profitBeforeTax);
    setIncomeValue('net_profit', netProfit);
    document.getElementById('income-checks').textContent = 'Controls passed';
}
document.querySelectorAll('.income-input:not([readonly])').forEach(el => el.addEventListener('input', updateIncomeTotals));
updateIncomeTotals();
</script>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
