<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'clients';
$pageTitle = 'Create client';
$statuses = ['active', 'inactive', 'watchlist', 'rejected'];
$errors = [];
$client = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('clients/create.php'));
}

$client = [
    'client_name' => clean_input(post_value('client_name')),
    'idno' => nullable_input(post_value('idno')),
    'legal_form' => nullable_input(post_value('legal_form')),
    'registration_date' => nullable_input(post_value('registration_date')),
    'activity_sector' => nullable_input(post_value('activity_sector')),
    'caem_code' => nullable_input(post_value('caem_code')),
    'address' => nullable_input(post_value('address')),
    'phone' => nullable_input(post_value('phone')),
    'email' => nullable_input(post_value('email')),
    'website' => nullable_input(post_value('website')),
    'status' => clean_input(post_value('status', 'active')),
    'notes' => nullable_input(post_value('notes')),
];

if ($client['client_name'] === '') {
    $errors[] = 'Client name is required.';
}
if (!in_array($client['status'], $statuses, true)) {
    $errors[] = 'Invalid client status.';
}
if (!is_valid_date($client['registration_date'])) {
    $errors[] = 'Registration date must use YYYY-MM-DD format.';
}
if ($client['email'] !== null && filter_var($client['email'], FILTER_VALIDATE_EMAIL) === false) {
    $errors[] = 'Email must be valid.';
}
if ($client['website'] !== null && filter_var($client['website'], FILTER_VALIDATE_URL) === false) {
    $errors[] = 'Website must be a valid URL.';
}

if (!empty($errors) || !$pdo instanceof PDO) {
    require_once dirname(__DIR__) . '/header.php';
    require_once dirname(__DIR__) . '/sidebar.php';
    ?>
    <section class="page-heading mb-4"><p class="eyebrow mb-2">Client registry</p><h1 class="h2 mb-0">Create new client</h1></section>
    <div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="post" action="<?= e(url('clients/store.php')) ?>" class="row g-3"><?php require __DIR__ . '/_form.php'; ?><div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save client</button><a class="btn btn-outline-secondary" href="<?= e(url('clients/index.php')) ?>">Back</a></div></form></div></div>
    <?php require_once dirname(__DIR__) . '/footer.php'; exit;
}

$statement = $pdo->prepare('INSERT INTO clients (client_name, idno, legal_form, registration_date, activity_sector, caem_code, address, phone, email, website, status, notes) VALUES (:client_name, :idno, :legal_form, :registration_date, :activity_sector, :caem_code, :address, :phone, :email, :website, :status, :notes)');
$statement->execute($client);
$id = (int) $pdo->lastInsertId();
log_action($pdo, 'create client', 'client', $id, null, $client);
redirect(url('clients/view.php?id=' . $id));
