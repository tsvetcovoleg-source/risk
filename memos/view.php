<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'memos';
$pageTitle = 'Credit memo';
$applicationId = get_int_param('application_id');
$memo = null;

if ($applicationId && $pdo instanceof PDO) {
    $statement = $pdo->prepare(
        'SELECT cm.*, ca.application_number, ca.requested_amount, ca.currency, ca.requested_term_months, ca.status AS application_status,
                c.client_name, c.idno
         FROM credit_memos cm
         INNER JOIN credit_applications ca ON ca.id = cm.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE cm.application_id = ? AND ca.deleted_at IS NULL
         LIMIT 1'
    );
    $statement->execute([$applicationId]);
    $memo = $statement->fetch() ?: null;
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$memo) {
    render_error_page('Credit memo not found', 'The credit memo does not exist for this application yet.', $applicationId ? url('memos/create.php?application_id=' . $applicationId) : url('memos/index.php'), $applicationId ? 'Create credit memo' : 'Back to memos list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
$sections = [
    'executive_summary' => 'Executive summary',
    'client_description' => 'Client description',
    'transaction_description' => 'Transaction description',
    'financial_analysis' => 'Financial analysis',
    'risk_analysis' => 'Risk analysis',
    'collateral_analysis' => 'Collateral analysis',
    'strengths' => 'Strengths',
    'weaknesses' => 'Weaknesses',
    'recommendation' => 'Recommendation',
];
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Credit memo</p>
            <h1 class="h2 mb-1">Credit memo</h1>
            <p class="text-secondary mb-0"><?= e($memo['application_number']) ?> · <?= e($memo['client_name']) ?></p>
        </div>
        <div class="align-self-lg-center d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="<?= e(url('memos/edit.php?application_id=' . $applicationId)) ?>">Edit memo</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('memos/print.php?application_id=' . $applicationId)) ?>">Print memo</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Back to application</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('memos/index.php')) ?>">Back to memos list</a>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
    <div class="row g-3">
        <?php foreach ([
            'Application number' => $memo['application_number'],
            'Client name' => $memo['client_name'],
            'IDNO' => $memo['idno'],
            'Requested amount' => format_amount($memo['requested_amount'], $memo['currency']),
            'Currency' => $memo['currency'],
            'Requested term' => ($memo['requested_term_months'] ?: '-') . ' months',
            'Application status' => $memo['application_status'],
            'Recommended decision' => memo_decision_label($memo['recommended_decision']),
            'Prepared at' => format_date($memo['prepared_at'], 'd.m.Y H:i'),
            'Updated at' => format_date($memo['updated_at'], 'd.m.Y H:i'),
        ] as $label => $value): ?>
            <div class="col-md-3"><div class="border rounded p-3 h-100"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value) ?></div></div></div>
        <?php endforeach; ?>
    </div>
</div></div>

<?php foreach ($sections as $field => $label): ?>
    <div class="card border-0 shadow-sm mb-3"><div class="card-body p-4">
        <h2 class="h5 mb-3"><?= e($label) ?></h2>
        <div class="memo-text text-body"><?= format_memo_text($memo[$field] ?? '') ?></div>
    </div></div>
<?php endforeach; ?>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
    <h2 class="h5 mb-3">Recommended decision</h2>
    <span class="badge text-bg-<?= e(memo_decision_badge_class($memo['recommended_decision'])) ?> fs-6"><?= e(memo_decision_label($memo['recommended_decision'])) ?></span>
</div></div>
<form method="post" action="<?= e(url('memos/delete.php')) ?>" onsubmit="return confirm('Delete this credit memo?');" class="mb-4">
    <input type="hidden" name="id" value="<?= e($memo['id']) ?>">
    <input type="hidden" name="application_id" value="<?= e($applicationId) ?>">
    <button class="btn btn-outline-danger" type="submit">Delete memo</button>
</form>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
