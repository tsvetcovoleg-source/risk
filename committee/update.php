<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('committee/index.php'));
$id = post_int('id');
$applicationId = post_int('application_id');
if (!$id || !$applicationId || !$pdo instanceof PDO) redirect(url('committee/index.php'));
$statement = $pdo->prepare('SELECT cd.*, ca.requested_amount FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id WHERE cd.id = ? AND cd.application_id = ? AND ca.deleted_at IS NULL');
$statement->execute([$id, $applicationId]);
$oldDecision = $statement->fetch();
if (!$oldDecision) redirect(url('committee/index.php'));
$application = ['requested_amount' => $oldDecision['requested_amount']];
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
if ($validation['errors']) redirect(url('committee/edit.php?application_id=' . $applicationId));
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('UPDATE committee_decisions SET committee_date = :committee_date, decision = :decision, approved_amount = :approved_amount, approved_currency = :approved_currency, approved_term_months = :approved_term_months, conditions = :conditions, rejection_reason = :rejection_reason, decision_notes = :decision_notes WHERE id = :id');
    $statement->execute($data + ['id' => $id]);
    $status = map_committee_decision_to_application_status($data['decision']);
    $statement = $pdo->prepare('UPDATE credit_applications SET status = ? WHERE id = ?');
    $statement->execute([$status, $applicationId]);
    log_action($pdo, 'update_committee_decision', 'committee_decision', $id, $oldDecision, $data + ['application_id' => $applicationId, 'status' => $status]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
redirect(url('committee/view.php?application_id=' . $applicationId));
