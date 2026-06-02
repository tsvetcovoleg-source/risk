<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$currentSection = 'clients'; $pageTitle = 'Edit related party'; $partyTypes = ['individual', 'legal_entity']; $relationshipTypes = ['shareholder', 'beneficial_owner', 'administrator', 'group_company', 'guarantor', 'other']; $errors = []; $id = get_int_param('id'); $party = null; $client = null;
if ($id && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT * FROM client_related_parties WHERE id = ? AND deleted_at IS NULL'); $s->execute([$id]); $party = $s->fetch(); if ($party) { $s = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL'); $s->execute([$party['client_id']]); $client = $s->fetch(); } }
require_once dirname(__DIR__) . '/header.php'; require_once dirname(__DIR__) . '/sidebar.php';
if (!$party || !$client) { render_error_page('Related party not found', 'The requested related party does not exist or was archived.', url('clients/index.php'), 'Back to clients list'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Related parties</p><h1 class="h2 mb-0">Edit related party</h1></section>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><form class="row g-3" method="post" action="<?= e(url('clients/related_party_update.php')) ?>"><input type="hidden" name="id" value="<?= e($party['id']) ?>"><?php require __DIR__ . '/_related_party_form.php'; ?><div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Update related party</button><a class="btn btn-outline-secondary" href="<?= e(url('clients/view.php?id=' . $client['id'])) ?>">Back to client card</a></div></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
