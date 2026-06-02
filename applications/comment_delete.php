<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$id = get_int_param('id');
if ($id && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT * FROM application_comments WHERE id = ? AND deleted_at IS NULL'); $s->execute([$id]); $comment = $s->fetch(); if ($comment) { $s = $pdo->prepare('UPDATE application_comments SET deleted_at = NOW() WHERE id = ?'); $s->execute([$id]); log_action($pdo, 'delete comment', 'comment', $id, $comment, ['deleted_at' => date('c')]); redirect(url('applications/view.php?id=' . $comment['application_id'])); } }
redirect(url('applications/index.php'));
