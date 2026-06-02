<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'memos';
$pageTitle = 'Edit credit memo';
$applicationId = get_int_param('application_id');
$memo = null;

if ($applicationId && $pdo instanceof PDO) {
    $statement = $pdo->prepare(
        'SELECT cm.*, ca.application_number, c.client_name
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
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Credit memo</p>
    <h1 class="h2 mb-3">Edit credit memo</h1>
    <p class="text-secondary mb-0"><?= e($memo['application_number']) ?> · <?= e($memo['client_name']) ?></p>
</section>
<div class="alert alert-info border-0 shadow-sm">Changing the credit memo does not change the original data in the client, application, financial statements, collateral or scoring modules.</div>
<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
    <form method="post" action="<?= e(url('memos/regenerate.php')) ?>" class="d-flex flex-column flex-md-row justify-content-between gap-3 align-items-md-center">
        <div>
            <h2 class="h6 mb-1">Regenerate draft from current data</h2>
            <p class="text-secondary small mb-0">Use this action if source application data changed after memo preparation.</p>
        </div>
        <input type="hidden" name="application_id" value="<?= e($applicationId) ?>">
        <button class="btn btn-outline-warning" type="submit">Regenerate draft from current data</button>
    </form>
</div></div>
<form method="post" action="<?= e(url('memos/update.php')) ?>">
    <input type="hidden" name="application_id" value="<?= e($applicationId) ?>">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
        <?php foreach ([
            'executive_summary' => 'Executive summary',
            'client_description' => 'Client description',
            'transaction_description' => 'Transaction description',
            'financial_analysis' => 'Financial analysis',
            'risk_analysis' => 'Risk analysis',
            'collateral_analysis' => 'Collateral analysis',
            'strengths' => 'Strengths',
            'weaknesses' => 'Weaknesses',
            'recommendation' => 'Recommendation',
        ] as $field => $label): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold" for="<?= e($field) ?>"><?= e($label) ?></label>
                <textarea class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= in_array($field, ['financial_analysis', 'risk_analysis', 'collateral_analysis'], true) ? 8 : 5 ?>"><?= e($memo[$field] ?? '') ?></textarea>
            </div>
        <?php endforeach; ?>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="recommended_decision">Recommended decision</label>
            <select class="form-select" id="recommended_decision" name="recommended_decision" required>
                <?php foreach (memo_decision_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= selected_attr($memo['recommended_decision'], $value) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit">Save changes</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('memos/view.php?application_id=' . $applicationId)) ?>">Cancel</a>
        </div>
    </div></div>
</form>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
