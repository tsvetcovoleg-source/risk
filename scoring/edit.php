<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'scoring';
$pageTitle = 'Edit expert override';
$applicationId = get_int_param('application_id');
$result = $application = null;
if ($applicationId && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT sr.*, ca.application_number, c.client_name FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE sr.application_id = ? ORDER BY sr.id DESC LIMIT 1');
    $statement->execute([$applicationId]);
    $result = $statement->fetch() ?: null;
}
$calculatedRisk = $result ? determine_risk_level($result['final_score']) : null;
$allowedRiskLevels = ['low', 'moderate', 'medium', 'high', 'very_high'];

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$result) {
    render_error_page('Scoring not found', 'Calculate scoring before editing expert override.', $applicationId ? url('scoring/calculate.php?application_id=' . $applicationId) : url('scoring/index.php'), 'Calculate scoring');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Expert override</p><h1 class="h2 mb-1">Edit expert override</h1><p class="text-secondary mb-0"><?= e($result['application_number']) ?> · <?= e($result['client_name']) ?></p></section>
<div class="alert alert-info">Expert override changes only the effective risk level. Calculated scores remain unchanged. Calculated risk level from final score: <strong><?= e($calculatedRisk) ?></strong>.</div>
<form class="card border-0 shadow-sm" method="post" action="<?= e(url('scoring/update.php')) ?>"><div class="card-body p-4">
    <input type="hidden" name="application_id" value="<?= e($applicationId) ?>">
    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" role="switch" id="expert_override" name="expert_override" value="1" <?= checked_attr($result['expert_override']) ?>><label class="form-check-label" for="expert_override">Enable expert override</label></div>
    <div class="mb-3"><label class="form-label" for="risk_level">Effective risk level when override is enabled</label><select class="form-select" id="risk_level" name="risk_level"><option value="">Select risk level</option><?php foreach ($allowedRiskLevels as $level): ?><option value="<?= e($level) ?>" <?= selected_attr($result['risk_level'], $level) ?>><?= e($level) ?></option><?php endforeach; ?></select></div>
    <div class="mb-3"><label class="form-label" for="override_reason">Override reason</label><textarea class="form-control" id="override_reason" name="override_reason" rows="4"><?= e($result['override_reason']) ?></textarea><div class="form-text">Required when expert override is enabled. It can be cleared when override is disabled.</div></div>
    <div class="d-flex gap-2"><button class="btn btn-primary" type="submit">Save override</button><a class="btn btn-outline-secondary" href="<?= e(url('scoring/view.php?application_id=' . $applicationId)) ?>">Cancel</a></div>
</div></form>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
