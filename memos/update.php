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

$statement = $pdo->prepare('SELECT * FROM credit_memos WHERE application_id = ? LIMIT 1');
$statement->execute([$applicationId]);
$memo = $statement->fetch();
if (!$memo) {
    redirect(url('memos/create.php?application_id=' . $applicationId));
}

$values = [];
foreach ($textFields as $field) {
    $values[$field] = clean_input($_POST[$field] ?? '');
}
$values['recommended_decision'] = $decision;
$values['id'] = $memo['id'];

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
$statement->execute($values);

log_action($pdo, 'update_credit_memo', 'credit_memo', (int) $memo['id'], ['recommended_decision' => $memo['recommended_decision']], ['recommended_decision' => $decision]);

redirect(url('memos/view.php?application_id=' . $applicationId));
