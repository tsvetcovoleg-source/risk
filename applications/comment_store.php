<?php
require_once dirname(__DIR__) . '/config.php'; require_once dirname(__DIR__) . '/functions.php'; require_once dirname(__DIR__) . '/db.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(url('applications/index.php')); }
$applicationId = post_int('application_id'); $commentText = clean_input(post_value('comment_text')); $application = null;
if ($applicationId && $pdo instanceof PDO) { $s = $pdo->prepare('SELECT * FROM credit_applications WHERE id = ? AND deleted_at IS NULL'); $s->execute([$applicationId]); $application = $s->fetch(); }
if (!$application || $commentText === '') { $currentSection = 'applications'; $pageTitle = 'Add comment'; require_once dirname(__DIR__) . '/header.php'; require_once dirname(__DIR__) . '/sidebar.php'; render_error_page('Cannot add comment', !$application ? 'Application not found.' : 'Comment text is required.', url('applications/index.php'), 'Back to applications list'); require_once dirname(__DIR__) . '/footer.php'; exit; }
$s = $pdo->prepare('INSERT INTO application_comments (application_id, comment_text) VALUES (?, ?)'); $s->execute([$applicationId, $commentText]); $id = (int) $pdo->lastInsertId(); log_action($pdo, 'add comment', 'comment', $id, null, ['application_id' => $applicationId, 'comment_text' => $commentText]); redirect(url('applications/view.php?id=' . $applicationId));
