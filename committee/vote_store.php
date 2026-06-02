<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect(url('committee/index.php'));
$decisionId = post_int('decision_id');
$vote = clean_input($_POST['vote'] ?? '');
$comment = nullable_input($_POST['comment'] ?? null);
if (!$decisionId || !$pdo instanceof PDO || !is_valid_committee_vote($vote)) redirect(url('committee/index.php'));
$statement = $pdo->prepare('SELECT * FROM committee_decisions WHERE id = ?');
$statement->execute([$decisionId]);
$decision = $statement->fetch();
if (!$decision) redirect(url('committee/index.php'));
$statement = $pdo->prepare('INSERT INTO committee_votes (committee_decision_id, vote, comment) VALUES (?, ?, ?)');
$statement->execute([$decisionId, $vote, $comment]);
$voteId = (int) $pdo->lastInsertId();
log_action($pdo, 'add_committee_vote', 'committee_vote', $voteId, null, ['committee_decision_id' => $decisionId, 'vote' => $vote, 'comment' => $comment]);
redirect(url('committee/view.php?application_id=' . $decision['application_id']));
