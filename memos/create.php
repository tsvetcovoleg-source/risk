<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'memos';
$pageTitle = 'Create credit memo';
$applicationId = get_int_param('application_id');
$context = null;
$draft = null;
$existingMemo = null;

if ($applicationId && $pdo instanceof PDO) {
    $context = get_application_full_context($pdo, $applicationId);
    if ($context['application']) {
        $statement = $pdo->prepare('SELECT * FROM credit_memos WHERE application_id = ? LIMIT 1');
        $statement->execute([$applicationId]);
        $existingMemo = $statement->fetch() ?: null;
        if (!$existingMemo) {
            $draft = generate_credit_memo_draft($context);
        }
    }
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$applicationId || !$context || !$context['application']) {
    render_error_page('Application not found', 'The requested application does not exist or was archived.', url('applications/index.php'), 'Back to applications list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
if ($existingMemo) {
    ?>
    <section class="page-heading mb-4"><p class="eyebrow mb-2">Credit memo</p><h1 class="h2 mb-3">Credit memo already exists</h1></section>
    <div class="alert alert-info border-0 shadow-sm">A credit memo has already been created for application <?= e($context['application']['application_number']) ?>.</div>
    <a class="btn btn-primary" href="<?= e(url('memos/view.php?application_id=' . $applicationId)) ?>">Open existing memo</a>
    <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Back to application</a>
    <?php
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Credit memo</p>
    <h1 class="h2 mb-3">Create credit memo</h1>
    <p class="text-secondary mb-0"><?= e($context['application']['application_number']) ?> · <?= e($context['client']['client_name']) ?></p>
</section>

<form method="post" action="<?= e(url('memos/store.php')) ?>">
    <input type="hidden" name="application_id" value="<?= e($applicationId) ?>">
    <div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
        <h2 class="h5 mb-3">Draft generated from current application data</h2>
        <div class="alert alert-warning">The draft is template-based and must be reviewed by an analyst before use.</div>
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
                <textarea class="form-control" id="<?= e($field) ?>" name="<?= e($field) ?>" rows="<?= in_array($field, ['financial_analysis', 'risk_analysis', 'collateral_analysis'], true) ? 8 : 5 ?>"><?= e($draft[$field] ?? '') ?></textarea>
            </div>
        <?php endforeach; ?>
        <div class="mb-3">
            <label class="form-label fw-semibold" for="recommended_decision">Recommended decision</label>
            <select class="form-select" id="recommended_decision" name="recommended_decision" required>
                <?php foreach (memo_decision_options() as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= selected_attr($draft['recommended_decision'] ?? '', $value) ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-primary" type="submit">Save credit memo</button>
            <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $applicationId)) ?>">Cancel</a>
        </div>
    </div></div>
</form>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
