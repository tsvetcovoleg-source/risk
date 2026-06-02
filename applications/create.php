<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$currentSection = 'applications'; $pageTitle = 'Create application'; $statuses = ['draft', 'submitted', 'in_analysis', 'risk_review', 'committee_review', 'approved', 'approved_with_conditions', 'rejected', 'cancelled', 'disbursed']; $currencies = ['MDL', 'EUR', 'USD']; $priorities = ['normal', 'high', 'urgent']; $errors = []; $clients = []; $fixedClient = null; $application = ['application_date' => date('Y-m-d'), 'currency' => 'MDL', 'status' => 'draft', 'priority' => 'normal'];
$clientId = get_int_param('client_id');
if ($pdo instanceof PDO) { if ($clientId) { $s = $pdo->prepare('SELECT id, client_name, idno FROM clients WHERE id = ? AND deleted_at IS NULL'); $s->execute([$clientId]); $fixedClient = $s->fetch(); $application['client_id'] = $clientId; } $s = $pdo->prepare('SELECT id, client_name, idno FROM clients WHERE deleted_at IS NULL ORDER BY client_name'); $s->execute(); $clients = $s->fetchAll(); }
require_once dirname(__DIR__) . '/header.php'; require_once dirname(__DIR__) . '/sidebar.php';
if ($clientId && !$fixedClient) { render_error_page('Client not found', 'The selected client does not exist or was archived.', url('applications/index.php'), 'Back to applications list'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Application registry</p><h1 class="h2 mb-0">Create new application</h1></section>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="post" action="<?= e(url('applications/store.php')) ?>" class="row g-3"><?php require __DIR__ . '/_form.php'; ?><div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save application</button><a class="btn btn-outline-secondary" href="<?= e(url('applications/index.php')) ?>">Back to applications list</a></div></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
