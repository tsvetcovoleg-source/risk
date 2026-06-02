<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
$currentSection = 'financials'; $pageTitle = 'Edit income statement'; $id = get_int_param('id'); $period = null; $income = null;
$fields = ['revenue' => 'Revenue', 'cost_of_goods_sold' => 'Cost of goods sold', 'gross_profit' => 'Gross profit', 'operating_expenses' => 'Operating expenses', 'ebitda' => 'EBITDA', 'depreciation_amortization' => 'Depreciation and amortization', 'ebit' => 'EBIT', 'interest_expense' => 'Interest expense', 'profit_before_tax' => 'Profit before tax', 'tax_expense' => 'Tax expense', 'net_profit' => 'Net profit'];
if ($id && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT fp.*, ca.application_number, c.client_name FROM financial_periods fp INNER JOIN credit_applications ca ON ca.id = fp.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE fp.id = ? AND fp.deleted_at IS NULL'); $s->execute([$id]); $period = $s->fetch(); if ($period) { $s = $pdo->prepare('SELECT * FROM financial_income_statement WHERE financial_period_id = ?'); $s->execute([$id]); $income = $s->fetch(); } }
require_once dirname(__DIR__) . '/header.php'; require_once dirname(__DIR__) . '/sidebar.php';
if (!$period || !$income) { render_error_page('Income statement not found', 'The requested period or income statement record does not exist.', url('financials/index.php'), 'Back to financial statements'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Financial statements</p><h1 class="h2 mb-0">Edit income statement</h1></section>
<div class="alert alert-info border-0 shadow-sm">Values are entered in the client's reporting currency without conversion. Calculated lines are not auto-filled, so analyst-entered figures remain unchanged.</div>
<form method="post" action="<?= e(url('financials/update_income.php')) ?>">
    <input type="hidden" name="id" value="<?= e($period['id']) ?>">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-1"><?= e($period['period_label']) ?></h2><p class="text-secondary mb-0"><?= e($period['application_number']) ?> · <?= e($period['client_name']) ?></p></div></div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Profit and loss</h2><div class="row g-3"><?php foreach ($fields as $field => $label): ?><div class="col-md-6"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label><input class="form-control income-input" type="number" step="0.01" id="<?= e($field) ?>" name="<?= e($field) ?>" value="<?= e($income[$field]) ?>" required></div><?php endforeach; ?></div></div></div>
    <div class="card border-0 shadow-sm mb-4"><div class="card-body"><h2 class="h6 mb-2">P&amp;L control checks</h2><div id="income-checks" class="small"></div></div></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save income statement</button><a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">Cancel</a></div>
</form>
<script>
const checks=[['gross_profit','revenue','cost_of_goods_sold','gross_profit = revenue - cost_of_goods_sold'],['ebitda','gross_profit','operating_expenses','EBITDA = gross_profit - operating_expenses'],['ebit','ebitda','depreciation_amortization','EBIT = EBITDA - depreciation_amortization'],['profit_before_tax','ebit','interest_expense','profit_before_tax = EBIT - interest_expense'],['net_profit','profit_before_tax','tax_expense','net_profit = profit_before_tax - tax_expense']];
function val(id){return parseFloat(document.getElementById(id).value||0);}function updateIncomeChecks(){let html='';let issues=0;checks.forEach(c=>{const actual=val(c[0]);const expected=val(c[1])-val(c[2]);const diff=(actual-expected).toFixed(2);if(Math.abs(diff)>0.01){issues++;html+='<div class="text-danger">'+c[3]+'. Difference: '+diff+'</div>';}});document.getElementById('income-checks').innerHTML=issues?html:'<span class="text-success fw-semibold">No control discrepancies.</span>';}
document.querySelectorAll('.income-input').forEach(el=>el.addEventListener('input',updateIncomeChecks));updateIncomeChecks();
</script>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
