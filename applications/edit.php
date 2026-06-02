<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$currentSection = 'applications'; $pageTitle = 'Edit application'; $statuses = ['draft', 'submitted', 'in_analysis', 'risk_review', 'committee_review', 'approved', 'approved_with_conditions', 'rejected', 'cancelled', 'disbursed']; $currencies = ['MDL', 'EUR', 'USD']; $priorities = ['normal', 'high', 'urgent']; $errors = []; $clients = []; $fixedClient = null; $application = null; $id = get_int_param('id');
if ($id && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT * FROM credit_applications WHERE id = ? AND deleted_at IS NULL'); $s->execute([$id]); $application = $s->fetch(); $s = $pdo->prepare('SELECT id, client_name, idno FROM clients WHERE deleted_at IS NULL ORDER BY client_name'); $s->execute(); $clients = $s->fetchAll(); }
require_once dirname(__DIR__) . '/header.php'; require_once dirname(__DIR__) . '/sidebar.php';
if (!$application) { render_error_page('Application not found', 'The requested application does not exist or was archived.', url('applications/index.php'), 'Back to applications list'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Application registry</p><h1 class="h2 mb-0">Edit application</h1></section>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="post" action="<?= e(url('applications/update.php')) ?>" class="row g-3"><input type="hidden" name="id" value="<?= e($application['id']) ?>"><?php require __DIR__ . '/_form.php'; ?><div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Update application</button><a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $application['id'])) ?>">Back to application card</a></div></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
