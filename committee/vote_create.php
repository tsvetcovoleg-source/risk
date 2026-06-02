<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
$currentSection = 'committee';
$pageTitle = 'Add committee vote';
$decisionId = get_int_param('decision_id');
$decision = null;
if ($decisionId && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT cd.*, ca.application_number, c.client_name FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE cd.id = ? AND ca.deleted_at IS NULL');
    $statement->execute([$decisionId]);
    $decision = $statement->fetch() ?: null;
}
require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$decision) { render_error_page('Committee decision not found', 'The requested committee decision does not exist.', url('committee/index.php'), 'Back to committee list'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Credit committee</p><h1 class="h2 mb-1">Add vote</h1><p class="text-secondary mb-0"><?= e($decision['application_number']) ?> · <?= e($decision['client_name']) ?></p></section>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="post" action="<?= e(url('committee/vote_store.php')) ?>"><input type="hidden" name="decision_id" value="<?= e($decision['id']) ?>"><div class="mb-3"><label class="form-label" for="vote">Vote</label><select class="form-select" id="vote" name="vote" required><option value="">Select vote</option><?php foreach (committee_vote_options() as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="comment">Comment</label><textarea class="form-control" id="comment" name="comment" rows="4"></textarea></div><button class="btn btn-primary" type="submit">Save vote</button><a class="btn btn-outline-secondary" href="<?= e(url('committee/view.php?application_id=' . $decision['application_id'])) ?>">Cancel</a></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
