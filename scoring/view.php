<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'scoring';
$pageTitle = 'Scoring result';
$applicationId = get_int_param('application_id');
$data = $result = $factors = $period = $ratios = $balance = null;
$collateralSummary = null;

if ($applicationId && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT ca.*, c.client_name, c.idno, c.id AS client_id FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.id = ? AND ca.deleted_at IS NULL');
    $statement->execute([$applicationId]);
    $data = $statement->fetch() ?: null;
    if ($data) {
        $statement = $pdo->prepare('SELECT * FROM scoring_results WHERE application_id = ? ORDER BY id DESC LIMIT 1');
        $statement->execute([$applicationId]);
        $result = $statement->fetch() ?: null;
        if ($result) {
            $statement = $pdo->prepare('SELECT * FROM scoring_non_financial_factors WHERE scoring_result_id = ? ORDER BY id DESC LIMIT 1');
            $statement->execute([$result['id']]);
            $factors = $statement->fetch() ?: [];
        }
        $statement = $pdo->prepare('SELECT * FROM financial_periods WHERE application_id = ? AND deleted_at IS NULL ORDER BY period_end_date DESC, id DESC LIMIT 1');
        $statement->execute([$applicationId]);
        $period = $statement->fetch() ?: null;
        if ($period) {
            $statement = $pdo->prepare('SELECT * FROM financial_ratios WHERE application_id = ? AND financial_period_id = ?');
            $statement->execute([$applicationId, $period['id']]);
            $ratios = $statement->fetch() ?: null;
            $statement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?');
            $statement->execute([$period['id']]);
            $balance = $statement->fetch() ?: null;
        }
        $statement = $pdo->prepare('SELECT * FROM collateral WHERE application_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, id DESC');
        $statement->execute([$applicationId]);
        $collateralSummary = calculate_collateral_summary($statement->fetchAll(), $data['requested_amount'], $data['currency']);
    }
}
$calculatedRisk = $result ? determine_risk_level($result['final_score']) : null;
$effectiveRisk = $result ? $result['risk_level'] : null;
$financialDetails = $ratios ? calculate_financial_score($ratios, $balance) : null;
$collateralDetails = $collateralSummary ? calculate_collateral_score($collateralSummary) : null;

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$data || !$result) {
    render_error_page('Scoring not found', 'The requested scoring result does not exist. Calculate scoring first.', $applicationId ? url('scoring/calculate.php?application_id=' . $applicationId) : url('scoring/index.php'), 'Calculate scoring');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><p class="eyebrow mb-2">SME scoring</p><h1 class="h2 mb-1">Scoring result</h1><p class="text-secondary mb-0"><?= e($data['application_number']) ?> · <?= e($data['client_name']) ?></p></div><div class="align-self-lg-center d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="<?= e(url('scoring/calculate.php?application_id=' . $applicationId)) ?>">Recalculate scoring</a><a class="btn btn-outline-warning" href="<?= e(url('scoring/edit.php?application_id=' . $applicationId)) ?>">Edit expert override</a></div></div></section>

<?php if ($result['expert_override']): ?><div class="alert alert-warning border-0 shadow-sm"><strong>Expert override is active.</strong> The displayed effective risk level was changed manually. Reason: <?= e($result['override_reason']) ?></div><?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Application and client</h2>
        <?php foreach (['Application number' => $data['application_number'], 'Client name' => $data['client_name'], 'IDNO' => $data['idno'], 'Requested amount' => format_amount($data['requested_amount'], $data['currency']), 'Currency' => $data['currency'], 'Application status' => $data['status']] as $label => $value): ?><div class="mb-2"><span class="text-secondary small"><?= e($label) ?></span><div class="fw-semibold"><?= e($value ?: 'N/A') ?></div></div><?php endforeach; ?>
        <div class="d-flex gap-2 mt-3"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Open application</a><a class="btn btn-sm btn-outline-primary" href="<?= e(url('clients/view.php?id=' . $data['client_id'])) ?>">Open client</a></div>
    </div></div></div>
    <div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Scoring summary</h2><div class="row g-3">
        <?php foreach (['Financial score' => $result['financial_score'], 'Non-financial score' => $result['non_financial_score'], 'Collateral score' => $result['collateral_score'], 'Final score' => $result['final_score']] as $label => $value): ?><div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-secondary small"><?= e($label) ?></div><div class="h4 mb-0"><?= e(format_score($value)) ?></div></div></div><?php endforeach; ?>
        <div class="col-md-6"><div class="border rounded p-3"><div class="text-secondary small">Calculated risk level</div><span class="badge text-bg-<?= e(risk_level_badge_class($calculatedRisk)) ?> fs-6"><?= e($calculatedRisk) ?></span></div></div>
        <div class="col-md-6"><div class="border rounded p-3 <?= $result['expert_override'] ? 'border-warning bg-warning-subtle' : '' ?>"><div class="text-secondary small">Effective risk level</div><span class="badge text-bg-<?= e(risk_level_badge_class($effectiveRisk)) ?> fs-6"><?= e($effectiveRisk) ?></span></div></div>
    </div></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Financial score details</h2>
    <?php if (!$financialDetails): ?><div class="alert alert-warning mb-0">Financial ratios are not available for the latest period.</div><?php else: ?>
    <div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Ratio name</th><th class="text-end">Value</th><th class="text-end">Points</th><th>Comment</th></tr></thead><tbody><?php foreach ($financialDetails['details'] as $detail): ?><tr><td><?= e($detail['label']) ?></td><td class="text-end"><?= e(format_ratio($detail['value'], $detail['percent'])) ?></td><td class="text-end"><?= e($detail['points']) ?></td><td><?= e($detail['comment']) ?></td></tr><?php endforeach; ?></tbody></table></div>
    <?php foreach ($financialDetails['warnings'] as $warning): ?><div class="alert alert-warning py-2 mb-2"><?= e($warning) ?></div><?php endforeach; endif; ?>
</div></div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Collateral score details</h2><div class="row g-3">
    <div class="col-md-3"><span class="text-secondary small">Requested amount</span><div class="fw-semibold"><?= e(format_amount($data['requested_amount'], $data['currency'])) ?></div></div>
    <div class="col-md-3"><span class="text-secondary small">Accepted collateral</span><div class="fw-semibold"><?= e(format_amount($collateralSummary['total_accepted_application_currency'] ?? 0, $data['currency'])) ?></div></div>
    <div class="col-md-3"><span class="text-secondary small">Coverage ratio</span><div class="fw-semibold"><?= e(format_ratio($collateralDetails['coverage_ratio'] ?? null, true)) ?></div></div>
    <div class="col-md-3"><span class="text-secondary small">Collateral score</span><div class="fw-semibold"><?= e(format_score($result['collateral_score'])) ?></div></div>
</div><?php foreach (($collateralDetails['warnings'] ?? []) as $warning): ?><div class="alert alert-warning py-2 mt-3 mb-0"><?= e($warning) ?></div><?php endforeach; ?></div></div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Non-financial score details</h2><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Factor name</th><th class="text-end">Selected score</th><th>Interpretation</th></tr></thead><tbody><?php foreach (non_financial_factor_definitions() as $key => $label): $point = $factors[$key] ?? null; ?><tr><td><?= e($label) ?></td><td class="text-end"><?= e($point ?: 'N/A') ?></td><td><?= e($point ? interpret_score_point($point) : 'not assessed') ?></td></tr><?php endforeach; ?></tbody></table></div><div class="mt-3"><span class="text-secondary small">Comments</span><div><?= e(($factors['comments'] ?? '') ?: 'N/A') ?></div></div></div></div>

<div class="d-flex gap-2 flex-wrap mb-4"><a class="btn btn-primary" href="<?= e(url('scoring/calculate.php?application_id=' . $applicationId)) ?>">Recalculate scoring</a><a class="btn btn-outline-warning" href="<?= e(url('scoring/edit.php?application_id=' . $applicationId)) ?>">Edit expert override</a><a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Back to application</a><a class="btn btn-outline-secondary" href="<?= e(url('scoring/index.php')) ?>">Back to scoring list</a></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
