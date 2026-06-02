<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('committee/index.php'));
}
$applicationId = post_int('application_id');
if (!$applicationId || !$pdo instanceof PDO) {
    redirect(url('applications/index.php'));
}
$statement = $pdo->prepare('SELECT * FROM credit_applications WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$applicationId]);
$application = $statement->fetch();
if (!$application) {
    redirect(url('applications/index.php'));
}
$data = [
    'committee_date' => clean_input($_POST['committee_date'] ?? ''),
    'decision' => clean_input($_POST['decision'] ?? ''),
    'approved_amount' => nullable_input($_POST['approved_amount'] ?? null),
    'approved_currency' => nullable_input($_POST['approved_currency'] ?? null),
    'approved_term_months' => nullable_input($_POST['approved_term_months'] ?? null),
    'conditions' => nullable_input($_POST['conditions'] ?? null),
    'rejection_reason' => nullable_input($_POST['rejection_reason'] ?? null),
    'decision_notes' => nullable_input($_POST['decision_notes'] ?? null),
];
$validation = validate_committee_decision($data, $application);
if ($validation['errors']) {
    $query = http_build_query(['application_id' => $applicationId, 'error' => implode(' ', $validation['errors'])]);
    redirect(url('committee/create.php?' . $query));
}
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('SELECT id FROM committee_decisions WHERE application_id = ? LIMIT 1 FOR UPDATE');
    $statement->execute([$applicationId]);
    if ($existing = $statement->fetch()) {
        $pdo->rollBack();
        redirect(url('committee/view.php?application_id=' . $applicationId));
    }
    $statement = $pdo->prepare('INSERT INTO committee_decisions (application_id, committee_date, decision, approved_amount, approved_currency, approved_term_months, conditions, rejection_reason, decision_notes) VALUES (:application_id, :committee_date, :decision, :approved_amount, :approved_currency, :approved_term_months, :conditions, :rejection_reason, :decision_notes)');
    $statement->execute(['application_id' => $applicationId] + $data);
    $decisionId = (int) $pdo->lastInsertId();
    $status = map_committee_decision_to_application_status($data['decision']);
    $statement = $pdo->prepare('UPDATE credit_applications SET status = ? WHERE id = ?');
    $statement->execute([$status, $applicationId]);
    log_action($pdo, 'create_committee_decision', 'committee_decision', $decisionId, null, ['application_id' => $applicationId, 'decision' => $data['decision'], 'status' => $status]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
redirect(url('committee/view.php?application_id=' . $applicationId));
