<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'clients';
$pageTitle = 'Archive client';
$id = get_int_param('id');
$message = null;

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL');
    $statement->execute([$id]);
    $client = $statement->fetch();

    if ($client) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM credit_applications WHERE client_id = ? AND deleted_at IS NULL');
        $statement->execute([$id]);
        if ((int) $statement->fetchColumn() > 0) {
            $message = 'Client cannot be archived while it has active credit applications. Archive the applications first.';
        } else {
            $statement = $pdo->prepare('UPDATE clients SET deleted_at = NOW() WHERE id = ?');
            $statement->execute([$id]);
            log_action($pdo, 'delete client', 'client', $id, $client, ['deleted_at' => date('c')]);
            redirect(url('clients/index.php'));
        }
    } else {
        $message = 'Client not found.';
    }
} else {
    $message = 'Invalid client identifier.';
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
render_error_page('Archive client', $message ?? 'Client cannot be archived.', url('clients/index.php'), 'Back to clients list');
require_once dirname(__DIR__) . '/footer.php';
