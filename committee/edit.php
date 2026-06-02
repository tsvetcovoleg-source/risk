<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/_partials.php';
$currentSection = 'committee';
$pageTitle = 'Edit committee decision';
$applicationId = get_int_param('application_id');
$context = $applicationId && $pdo instanceof PDO ? get_committee_decision_context($pdo, $applicationId) : null;
require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$context || !$context['application'] || !$context['committee_decision']) {
    render_error_page('Committee decision not found', 'The requested committee decision does not exist.', url('committee/index.php'), 'Back to committee list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
$decision = $context['committee_decision'];
?>
<section class="page-heading mb-4"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><p class="eyebrow mb-2">Credit committee</p><h1 class="h2 mb-1">Edit committee decision</h1><p class="text-secondary mb-0"><?= e($context['application']['application_number']) ?> · <?= e($context['client']['client_name']) ?></p></div><div class="align-self-lg-center"><a class="btn btn-outline-secondary" href="<?= e(url('committee/view.php?application_id=' . $applicationId)) ?>">Back to decision</a></div></div></section>
<div class="alert alert-info">Changing the committee decision may also update the application status.</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><form method="post" action="<?= e(url('committee/update.php')) ?>"><input type="hidden" name="id" value="<?= e($decision['id']) ?>"><input type="hidden" name="application_id" value="<?= e($applicationId) ?>"><?php committee_form_fields($decision); ?><div class="mt-4"><button class="btn btn-primary" type="submit">Update decision</button><a class="btn btn-outline-secondary" href="<?= e(url('committee/view.php?application_id=' . $applicationId)) ?>">Cancel</a></div></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
