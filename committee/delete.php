<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('committee/index.php'));
$id = post_int('id');
if (!$id || !$pdo instanceof PDO) redirect(url('committee/index.php'));
$statement = $pdo->prepare('SELECT * FROM committee_decisions WHERE id = ?');
$statement->execute([$id]);
$decision = $statement->fetch();
if (!$decision) redirect(url('committee/index.php'));
$applicationId = (int) $decision['application_id'];
try {
    $pdo->beginTransaction();
    $statement = $pdo->prepare('DELETE FROM committee_decisions WHERE id = ?');
    $statement->execute([$id]);
    $statement = $pdo->prepare("UPDATE credit_applications SET status = 'committee_review' WHERE id = ?");
    $statement->execute([$applicationId]);
    log_action($pdo, 'delete_committee_decision', 'committee_decision', $id, $decision, null);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}
redirect(url('applications/view.php?id=' . $applicationId));
