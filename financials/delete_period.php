<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('financials/index.php'));
}
if (!$pdo instanceof PDO) {
    redirect(url('financials/index.php'));
}
$id = post_int('id');
$fromApplication = post_int('from_application');
if (!$id) {
    redirect(url('financials/index.php'));
}
$statement = $pdo->prepare('SELECT * FROM financial_periods WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$id]);
$period = $statement->fetch();
if ($period) {
    $statement = $pdo->prepare('UPDATE financial_periods SET deleted_at = NOW() WHERE id = ?');
    $statement->execute([$id]);
    log_action($pdo, 'delete financial period', 'financial_period', $id, $period, ['deleted_at' => date('Y-m-d H:i:s')]);
}
if ($fromApplication) {
    redirect(url('applications/view.php?id=' . $fromApplication));
}
redirect(url('financials/index.php'));
