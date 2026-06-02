<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('committee/index.php'));
$id = post_int('id');
if (!$id || !$pdo instanceof PDO) redirect(url('committee/index.php'));
$statement = $pdo->prepare('SELECT cv.*, cd.application_id FROM committee_votes cv INNER JOIN committee_decisions cd ON cd.id = cv.committee_decision_id WHERE cv.id = ?');
$statement->execute([$id]);
$vote = $statement->fetch();
if (!$vote) redirect(url('committee/index.php'));
$statement = $pdo->prepare('DELETE FROM committee_votes WHERE id = ?');
$statement->execute([$id]);
log_action($pdo, 'delete_committee_vote', 'committee_vote', $id, $vote, null);
redirect(url('committee/view.php?application_id=' . $vote['application_id']));
