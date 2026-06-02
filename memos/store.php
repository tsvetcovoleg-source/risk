<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('memos/index.php'));
}

$applicationId = post_int('application_id');
$decision = clean_input($_POST['recommended_decision'] ?? '');
$textFields = ['executive_summary', 'client_description', 'transaction_description', 'financial_analysis', 'risk_analysis', 'collateral_analysis', 'strengths', 'weaknesses', 'recommendation'];

if (!$applicationId || !$pdo instanceof PDO || !is_valid_memo_decision($decision)) {
    redirect(url('memos/index.php'));
}

$statement = $pdo->prepare('SELECT id FROM credit_applications WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$applicationId]);
if (!$statement->fetch()) {
    redirect(url('applications/index.php'));
}

try {
    $pdo->beginTransaction();

    $statement = $pdo->prepare('SELECT id FROM credit_memos WHERE application_id = ? LIMIT 1 FOR UPDATE');
    $statement->execute([$applicationId]);
    if ($existing = $statement->fetch()) {
        $pdo->rollBack();
        redirect(url('memos/view.php?application_id=' . $applicationId));
    }

    $values = [];
    foreach ($textFields as $field) {
        $values[$field] = clean_input($_POST[$field] ?? '');
    }
    $values['recommended_decision'] = $decision;
    $values['application_id'] = $applicationId;

    $statement = $pdo->prepare(
        'INSERT INTO credit_memos
         (application_id, executive_summary, client_description, transaction_description, financial_analysis, risk_analysis, collateral_analysis, strengths, weaknesses, recommendation, recommended_decision, prepared_at)
         VALUES
         (:application_id, :executive_summary, :client_description, :transaction_description, :financial_analysis, :risk_analysis, :collateral_analysis, :strengths, :weaknesses, :recommendation, :recommended_decision, NOW())'
    );
    $statement->execute($values);
    $memoId = (int) $pdo->lastInsertId();

    log_action($pdo, 'create_credit_memo', 'credit_memo', $memoId, null, ['application_id' => $applicationId, 'recommended_decision' => $decision]);

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

redirect(url('memos/view.php?application_id=' . $applicationId));
