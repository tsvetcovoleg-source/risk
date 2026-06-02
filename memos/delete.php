<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('memos/index.php'));
}

$applicationId = post_int('application_id');
$memoId = post_int('id');

if (!$memoId || !$pdo instanceof PDO) {
    redirect(url('memos/index.php'));
}

$statement = $pdo->prepare('SELECT * FROM credit_memos WHERE id = ? LIMIT 1');
$statement->execute([$memoId]);
$memo = $statement->fetch();

if ($memo) {
    $statement = $pdo->prepare('DELETE FROM credit_memos WHERE id = ?');
    $statement->execute([$memoId]);
    log_action($pdo, 'delete_credit_memo', 'credit_memo', $memoId, ['application_id' => $memo['application_id'], 'recommended_decision' => $memo['recommended_decision']], null);
    $applicationId = $applicationId ?: (int) $memo['application_id'];
}

redirect($applicationId ? url('applications/view.php?id=' . $applicationId) : url('memos/index.php'));
