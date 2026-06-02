<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/_partials.php';

$currentSection = 'committee';
$pageTitle = 'Record committee decision';
$applicationId = get_int_param('application_id');
$context = null;

if ($applicationId && $pdo instanceof PDO) {
    $context = get_committee_decision_context($pdo, $applicationId);
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$context || !$context['application']) {
    render_error_page('Application not found', 'The requested application does not exist or was archived.', url('applications/index.php'), 'Back to applications');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
if ($context['committee_decision']) {
    ?>
    <section class="page-heading mb-4"><p class="eyebrow mb-2">Credit committee</p><h1 class="h2 mb-3">Committee decision already exists</h1><p class="text-secondary">This application already has a recorded committee decision.</p></section>
    <a class="btn btn-primary" href="<?= e(url('committee/view.php?application_id=' . $applicationId)) ?>">Open existing decision</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Back to application</a>
    <?php require_once dirname(__DIR__) . '/footer.php'; exit;
}
$values = ['committee_date' => date('Y-m-d'), 'approved_currency' => $context['application']['currency'] ?? 'MDL'];
?>
<section class="page-heading mb-4"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><p class="eyebrow mb-2">Credit committee</p><h1 class="h2 mb-1">Record committee decision</h1><p class="text-secondary mb-0"><?= e($context['application']['application_number']) ?> · <?= e($context['client']['client_name']) ?></p></div><div class="align-self-lg-center"><a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Back to application</a></div></div></section>
<?php if (!empty($_GET['error'])): ?><div class="alert alert-danger"><?= e($_GET['error']) ?></div><?php endif; ?>
<?php render_committee_decision_context($context, true); ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Committee decision</h2><form method="post" action="<?= e(url('committee/store.php')) ?>"><input type="hidden" name="application_id" value="<?= e($applicationId) ?>"><?php committee_form_fields($values); ?><div class="mt-4"><button class="btn btn-primary" type="submit">Save decision</button><a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Cancel</a></div></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
