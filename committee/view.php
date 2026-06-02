<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
require_once __DIR__ . '/_partials.php';

$currentSection = 'committee';
$pageTitle = 'Committee decision';
$applicationId = get_int_param('application_id');
$context = null;
if ($applicationId && $pdo instanceof PDO) {
    $context = get_committee_decision_context($pdo, $applicationId);
}
require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$context || !$context['application']) {
    render_error_page('Application not found', 'The requested application does not exist or was archived.', url('committee/index.php'), 'Back to committee list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
$application = $context['application'];
$client = $context['client'];
$decision = $context['committee_decision'];
if (!$decision) {
    render_error_page('Committee decision not found', 'The committee decision has not been recorded yet.', url('committee/create.php?application_id=' . $applicationId), 'Record committee decision');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
$votes = $context['committee_votes'];
$summary = $context['voting_summary'];
$memo = $context['credit_memo'];
$differsFromMemo = $memo && committee_decision_differs_from_memo($decision['decision'], $memo['recommended_decision']);
?>
<section class="page-heading mb-4"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><p class="eyebrow mb-2">Credit committee</p><h1 class="h2 mb-1">Committee decision</h1><p class="text-secondary mb-0"><?= e($application['application_number']) ?> · <?= e($client['client_name']) ?></p></div><div class="align-self-lg-center d-flex gap-2 flex-wrap"><a class="btn btn-primary" href="<?= e(url('committee/edit.php?application_id=' . $applicationId)) ?>">Edit decision</a><a class="btn btn-outline-primary" href="<?= e(url('committee/vote_create.php?decision_id=' . $decision['id'])) ?>">Add vote</a><a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Back to application</a><a class="btn btn-outline-secondary" href="<?= e(url('committee/index.php')) ?>">Back to committee list</a><?php if ($memo): ?><a class="btn btn-outline-secondary" href="<?= e(url('memos/view.php?application_id=' . $applicationId)) ?>">Open credit memo</a><?php endif; ?><a class="btn btn-outline-secondary" href="<?= e(url('clients/view.php?id=' . $client['id'])) ?>">Open client</a></div></div></section>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><div class="row g-3"><?php foreach (['Committee date' => format_date($decision['committee_date']), 'Application number' => $application['application_number'], 'Client name' => $client['client_name'], 'IDNO' => $client['idno'], 'Requested amount' => format_amount($application['requested_amount'], $application['currency']), 'Currency' => $application['currency'], 'Requested term' => ($application['requested_term_months'] ?: '-') . ' months', 'Application status' => $application['status']] as $label => $value): ?><div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value ?: '-') ?></div></div></div><?php endforeach; ?><div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-secondary small">Decision</div><span class="badge text-bg-<?= e(committee_decision_badge_class($decision['decision'])) ?> fs-6"><?= e(format_decision_label($decision['decision'])) ?></span></div></div></div></div></div>
<?php if ($decision['approved_amount'] !== null && (float) $decision['approved_amount'] > (float) $application['requested_amount']): ?><div class="alert alert-warning">Approved amount exceeds requested amount. Manual review is required.</div><?php endif; ?>
<?php if ($differsFromMemo): ?><div class="alert alert-warning">Committee decision differs from the recommended decision in the credit memo.</div><?php endif; ?>
<div class="row g-4 mb-4"><div class="col-lg-7"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Decision</h2><div class="row g-3"><div class="col-md-6"><span class="text-secondary small">Decision</span><div class="fw-semibold"><?= e(format_decision_label($decision['decision'])) ?></div></div><div class="col-md-6"><span class="text-secondary small">Approved terms</span><div class="fw-semibold"><?= e(format_amount($decision['approved_amount'], $decision['approved_currency'] ?: '')) ?> · <?= e($decision['approved_term_months'] ? $decision['approved_term_months'] . ' months' : '-') ?></div></div><div class="col-12"><span class="text-secondary small">Conditions</span><div><?= nl2br(e($decision['conditions'] ?: '-')) ?></div></div><div class="col-12"><span class="text-secondary small">Rejection reason</span><div><?= nl2br(e($decision['rejection_reason'] ?: '-')) ?></div></div><div class="col-12"><span class="text-secondary small">Decision notes</span><div><?= nl2br(e($decision['decision_notes'] ?: '-')) ?></div></div><div class="col-md-6"><span class="text-secondary small">Created at</span><div><?= e(format_date($decision['created_at'], 'd.m.Y H:i')) ?></div></div><div class="col-md-6"><span class="text-secondary small">Updated at</span><div><?= e(format_date($decision['updated_at'], 'd.m.Y H:i')) ?></div></div></div></div></div></div><div class="col-lg-5"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Voting summary</h2><?php if ($summary['total'] === 0): ?><div class="alert alert-warning mb-0">No votes have been recorded yet.</div><?php else: ?><div class="row g-2"><?php foreach (['Total votes' => 'total', 'Votes for' => 'for', 'Votes against' => 'against', 'Abstain' => 'abstain', 'Conditional' => 'conditional'] as $label => $field): ?><div class="col-6"><div class="border rounded p-2"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($summary[$field]) ?></div></div></div><?php endforeach; ?><div class="col-12"><div class="alert alert-<?= e($summary['majority_supportive'] ? 'success' : 'secondary') ?> mb-0">Majority supportive: <?= e($summary['majority_supportive'] ? 'Yes' : 'No') ?></div></div></div><?php endif; ?></div></div></div></div>
<?php render_committee_decision_context($context, false); ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Votes</h2><a class="btn btn-sm btn-primary" href="<?= e(url('committee/vote_create.php?decision_id=' . $decision['id'])) ?>">Add vote</a></div><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Vote</th><th>Comment</th><th>Created at</th><th class="text-end">Actions</th></tr></thead><tbody><?php if (!$votes): ?><tr><td colspan="4" class="text-center text-secondary py-3">No votes have been recorded yet.</td></tr><?php endif; ?><?php foreach ($votes as $vote): ?><tr><td><span class="badge text-bg-<?= e(committee_vote_badge_class($vote['vote'])) ?>"><?= e(format_vote_label($vote['vote'])) ?></span></td><td><?= nl2br(e($vote['comment'] ?: '-')) ?></td><td><?= e(format_date($vote['created_at'], 'd.m.Y H:i')) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('committee/vote_edit.php?id=' . $vote['id'])) ?>">Edit</a><form class="d-inline" method="post" action="<?= e(url('committee/vote_delete.php')) ?>" onsubmit="return confirm('Delete this vote?');"><input type="hidden" name="id" value="<?= e($vote['id']) ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
