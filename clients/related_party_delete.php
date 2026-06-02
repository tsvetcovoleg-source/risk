<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
$id = get_int_param('id');
if ($id && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT * FROM client_related_parties WHERE id = ? AND deleted_at IS NULL'); $s->execute([$id]); $party = $s->fetch(); if ($party) { $s = $pdo->prepare('UPDATE client_related_parties SET deleted_at = NOW() WHERE id = ?'); $s->execute([$id]); log_action($pdo, 'delete related party', 'related_party', $id, $party, ['deleted_at' => date('c')]); redirect(url('clients/view.php?id=' . $party['client_id'])); } }
redirect(url('clients/index.php'));
