<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('committee/index.php'));
$id = post_int('id');
$vote = clean_input($_POST['vote'] ?? '');
$comment = nullable_input($_POST['comment'] ?? null);
if (!$id || !$pdo instanceof PDO || !is_valid_committee_vote($vote)) redirect(url('committee/index.php'));
$statement = $pdo->prepare('SELECT cv.*, cd.application_id FROM committee_votes cv INNER JOIN committee_decisions cd ON cd.id = cv.committee_decision_id WHERE cv.id = ?');
$statement->execute([$id]);
$oldVote = $statement->fetch();
if (!$oldVote) redirect(url('committee/index.php'));
$statement = $pdo->prepare('UPDATE committee_votes SET vote = ?, comment = ? WHERE id = ?');
$statement->execute([$vote, $comment, $id]);
log_action($pdo, 'update_committee_vote', 'committee_vote', $id, $oldVote, ['vote' => $vote, 'comment' => $comment]);
redirect(url('committee/view.php?application_id=' . $oldVote['application_id']));
