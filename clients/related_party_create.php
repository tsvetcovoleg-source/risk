<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$currentSection = 'clients'; $pageTitle = 'Add related party'; $partyTypes = ['individual', 'legal_entity']; $relationshipTypes = ['shareholder', 'beneficial_owner', 'administrator', 'group_company', 'guarantor', 'other']; $errors = []; $party = ['party_type' => 'individual', 'relationship_type' => 'other'];
$clientId = get_int_param('client_id'); $client = null;
if ($clientId && $pdo instanceof PDO) { $statement = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL'); $statement->execute([$clientId]); $client = $statement->fetch(); }
require_once dirname(__DIR__) . '/header.php'; require_once dirname(__DIR__) . '/sidebar.php';
if (!$client) { render_error_page('Client not found', 'Select an active client before adding a related party.', url('clients/index.php'), 'Back to clients list'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Related parties</p><h1 class="h2 mb-0">Add related party for <?= e($client['client_name']) ?></h1></section>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><form class="row g-3" method="post" action="<?= e(url('clients/related_party_store.php')) ?>"><?php require __DIR__ . '/_related_party_form.php'; ?><div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save related party</button><a class="btn btn-outline-secondary" href="<?= e(url('clients/view.php?id=' . $client['id'])) ?>">Back to client card</a></div></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
