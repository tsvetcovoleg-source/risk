<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$id = get_int_param('id');
if ($id && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT * FROM credit_applications WHERE id = ? AND deleted_at IS NULL'); $s->execute([$id]); $application = $s->fetch(); if ($application) { $s = $pdo->prepare('UPDATE credit_applications SET deleted_at = NOW() WHERE id = ?'); $s->execute([$id]); log_action($pdo, 'delete application', 'application', $id, $application, ['deleted_at' => date('c')]); } }
redirect(url('applications/index.php'));
