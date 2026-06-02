<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
$currentSection = 'committee';
$pageTitle = 'Edit committee vote';
$id = get_int_param('id');
$voteRow = null;
if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT cv.*, cd.application_id, ca.application_number, c.client_name FROM committee_votes cv INNER JOIN committee_decisions cd ON cd.id = cv.committee_decision_id INNER JOIN credit_applications ca ON ca.id = cd.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE cv.id = ? AND ca.deleted_at IS NULL');
    $statement->execute([$id]);
    $voteRow = $statement->fetch() ?: null;
}
require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$voteRow) { render_error_page('Committee vote not found', 'The requested committee vote does not exist.', url('committee/index.php'), 'Back to committee list'); require_once dirname(__DIR__) . '/footer.php'; exit; }
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Credit committee</p><h1 class="h2 mb-1">Edit vote</h1><p class="text-secondary mb-0"><?= e($voteRow['application_number']) ?> · <?= e($voteRow['client_name']) ?></p></section>
<div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="post" action="<?= e(url('committee/vote_update.php')) ?>"><input type="hidden" name="id" value="<?= e($voteRow['id']) ?>"><div class="mb-3"><label class="form-label" for="vote">Vote</label><select class="form-select" id="vote" name="vote" required><?php foreach (committee_vote_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= selected_attr($voteRow['vote'], $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div><div class="mb-3"><label class="form-label" for="comment">Comment</label><textarea class="form-control" id="comment" name="comment" rows="4"><?= e($voteRow['comment'] ?? '') ?></textarea></div><button class="btn btn-primary" type="submit">Update vote</button><a class="btn btn-outline-secondary" href="<?= e(url('committee/view.php?application_id=' . $voteRow['application_id'])) ?>">Cancel</a></form></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
