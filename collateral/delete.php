<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('collateral/index.php'));
}

$id = post_int('id');
$returnTo = clean_input(post_value('return_to', 'list'));
$applicationId = post_int('application_id');

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT * FROM collateral WHERE id = ? AND deleted_at IS NULL');
    $statement->execute([$id]);
    $collateral = $statement->fetch();
    if ($collateral) {
        $statement = $pdo->prepare('UPDATE collateral SET deleted_at = NOW() WHERE id = ?');
        $statement->execute([$id]);
        log_action($pdo, 'delete collateral', 'collateral', $id, $collateral, ['deleted_at' => date('c')]);
        $applicationId = $applicationId ?: (int) $collateral['application_id'];
    }
}

if ($returnTo === 'application' && $applicationId) {
    redirect(url('applications/view.php?id=' . $applicationId));
}

redirect(url('collateral/index.php'));
