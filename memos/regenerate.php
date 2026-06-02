<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'memos';
$pageTitle = 'Regenerate credit memo draft';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('memos/index.php'));
}

$applicationId = post_int('application_id');
$confirmed = clean_input($_POST['confirm_regenerate'] ?? '') === '1';
$memo = null;
$context = null;

if ($applicationId && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT * FROM credit_memos WHERE application_id = ? LIMIT 1');
    $statement->execute([$applicationId]);
    $memo = $statement->fetch() ?: null;
    if ($memo) {
        $context = get_application_full_context($pdo, $applicationId);
    }
}

if (!$applicationId || !$memo || !$context || !$context['application']) {
    require_once dirname(__DIR__) . '/header.php';
    require_once dirname(__DIR__) . '/sidebar.php';
    render_error_page('Credit memo not found', 'The credit memo could not be regenerated because it does not exist or the application is archived.', url('memos/index.php'), 'Back to memos list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}

if (!$confirmed) {
    require_once dirname(__DIR__) . '/header.php';
    require_once dirname(__DIR__) . '/sidebar.php';
    ?>
    <section class="page-heading mb-4"><p class="eyebrow mb-2">Credit memo</p><h1 class="h2 mb-3">Regenerate draft from current data</h1></section>
    <div class="alert alert-warning border-0 shadow-sm">This action will overwrite the current memo text with a new draft generated from current application data.</div>
    <form method="post" action="<?= e(url('memos/regenerate.php')) ?>" class="d-flex gap-2 flex-wrap">
        <input type="hidden" name="application_id" value="<?= e($applicationId) ?>">
        <input type="hidden" name="confirm_regenerate" value="1">
        <button class="btn btn-warning" type="submit">Confirm regeneration</button>
        <a class="btn btn-outline-secondary" href="<?= e(url('memos/edit.php?application_id=' . $applicationId)) ?>">Cancel</a>
    </form>
    <?php
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}

$draft = generate_credit_memo_draft($context);
$statement = $pdo->prepare(
    'UPDATE credit_memos SET
        executive_summary = :executive_summary,
        client_description = :client_description,
        transaction_description = :transaction_description,
        financial_analysis = :financial_analysis,
        risk_analysis = :risk_analysis,
        collateral_analysis = :collateral_analysis,
        strengths = :strengths,
        weaknesses = :weaknesses,
        recommendation = :recommendation,
        recommended_decision = :recommended_decision
     WHERE id = :id'
);
$statement->execute([
    'executive_summary' => $draft['executive_summary'],
    'client_description' => $draft['client_description'],
    'transaction_description' => $draft['transaction_description'],
    'financial_analysis' => $draft['financial_analysis'],
    'risk_analysis' => $draft['risk_analysis'],
    'collateral_analysis' => $draft['collateral_analysis'],
    'strengths' => $draft['strengths'],
    'weaknesses' => $draft['weaknesses'],
    'recommendation' => $draft['recommendation'],
    'recommended_decision' => $draft['recommended_decision'],
    'id' => $memo['id'],
]);

log_action($pdo, 'regenerate_credit_memo', 'credit_memo', (int) $memo['id'], ['recommended_decision' => $memo['recommended_decision']], ['recommended_decision' => $draft['recommended_decision']]);

redirect(url('memos/view.php?application_id=' . $applicationId));
