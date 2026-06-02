<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$id = get_int_param('id');
$applicationId = get_int_param('application_id');
if (!$id || !($pdo instanceof PDO)) {
    redirect($applicationId ? url('applications/view.php?id=' . $applicationId) : url('scoring/index.php'));
}
$statement = $pdo->prepare('SELECT * FROM scoring_results WHERE id = ?');
$statement->execute([$id]);
$result = $statement->fetch();
if ($result) {
    $statement = $pdo->prepare('DELETE FROM scoring_results WHERE id = ?');
    $statement->execute([$id]);
    log_action($pdo, 'delete_scoring', 'scoring', (int) $id, $result, null);
    $applicationId = $applicationId ?: (int) $result['application_id'];
}
redirect($applicationId ? url('applications/view.php?id=' . $applicationId) : url('scoring/index.php'));
