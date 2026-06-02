<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('applications/index.php'));
}

$statuses = ['draft', 'submitted', 'in_analysis', 'risk_review', 'committee_review', 'approved', 'approved_with_conditions', 'rejected', 'cancelled', 'disbursed'];
$currencies = ['MDL', 'EUR', 'USD'];
$priorities = ['normal', 'high', 'urgent'];
$errors = [];
$clients = [];
$fixedClient = null;
$id = post_int('id');
$oldApplication = null;

if ($id && $pdo instanceof PDO) {
    $s = $pdo->prepare('SELECT * FROM credit_applications WHERE id = ? AND deleted_at IS NULL');
    $s->execute([$id]);
    $oldApplication = $s->fetch();
}

if (!$pdo instanceof PDO) {
    $errors[] = 'Database connection is not available.';
}

$clientId = post_int('client_id');
$client = null;
if ($clientId && $pdo instanceof PDO) {
    $s = $pdo->prepare('SELECT id, client_name, idno FROM clients WHERE id = ? AND deleted_at IS NULL');
    $s->execute([$clientId]);
    $client = $s->fetch();
}

$rawInterestRate = clean_input(post_value('interest_rate'));
$interestRate = $rawInterestRate === '' ? null : $rawInterestRate;
$application = [
    'id' => $id,
    'application_number' => $oldApplication['application_number'] ?? '',
    'client_id' => $clientId,
    'application_date' => clean_input(post_value('application_date')),
    'requested_amount' => clean_input(post_value('requested_amount')),
    'currency' => clean_input(post_value('currency', 'MDL')),
    'requested_term_months' => clean_input(post_value('requested_term_months')),
    'interest_rate' => $interestRate,
    'credit_product' => nullable_input(post_value('credit_product')),
    'credit_purpose' => clean_input(post_value('credit_purpose')),
    'repayment_source' => nullable_input(post_value('repayment_source')),
    'existing_exposure_amount' => clean_input(post_value('existing_exposure_amount', '0')),
    'proposed_total_exposure_amount' => clean_input(post_value('proposed_total_exposure_amount', '0')),
    'status' => clean_input(post_value('status', 'draft')),
    'priority' => clean_input(post_value('priority', 'normal')),
    'notes' => nullable_input(post_value('notes')),
];

if (!$oldApplication) {
    $errors[] = 'Application not found.';
}
if (!$client) {
    $errors[] = 'Client is required and must exist.';
}
if (!is_valid_date($application['application_date']) || $application['application_date'] === '') {
    $errors[] = 'Application date is required and must use YYYY-MM-DD format.';
}
if (!is_numeric($application['requested_amount']) || (float) $application['requested_amount'] <= 0) {
    $errors[] = 'Requested amount must be greater than 0.';
}
if (!in_array($application['currency'], $currencies, true)) {
    $errors[] = 'Invalid currency.';
}
if (!ctype_digit((string) $application['requested_term_months']) || (int) $application['requested_term_months'] <= 0) {
    $errors[] = 'Requested term must be greater than 0.';
}
if ($application['interest_rate'] !== null && (!is_numeric($application['interest_rate']) || (float) $application['interest_rate'] < 0 || (float) $application['interest_rate'] > 100)) {
    $errors[] = 'Annual interest rate must be a number between 0 and 100.';
}
if ($application['credit_purpose'] === '') {
    $errors[] = 'Credit purpose is required.';
}
if (!is_numeric($application['existing_exposure_amount']) || (float) $application['existing_exposure_amount'] < 0) {
    $errors[] = 'Existing exposure amount cannot be negative.';
}
if (!is_numeric($application['proposed_total_exposure_amount']) || (float) $application['proposed_total_exposure_amount'] < 0) {
    $errors[] = 'Proposed total exposure amount cannot be negative.';
}
if (!in_array($application['status'], $statuses, true)) {
    $errors[] = 'Invalid application status.';
}
if (!in_array($application['priority'], $priorities, true)) {
    $errors[] = 'Invalid priority.';
}

if ($errors) {
    $currentSection = 'applications';
    $pageTitle = 'Edit application';
    if ($pdo instanceof PDO) {
        $s = $pdo->prepare('SELECT id, client_name, idno FROM clients WHERE deleted_at IS NULL ORDER BY client_name');
        $s->execute();
        $clients = $s->fetchAll();
    }
    require_once dirname(__DIR__) . '/header.php';
    require_once dirname(__DIR__) . '/sidebar.php';
    ?>
    <section class="page-heading mb-4"><p class="eyebrow mb-2">Application registry</p><h1 class="h2 mb-0">Edit application</h1></section>
    <div class="card border-0 shadow-sm"><div class="card-body p-4"><form method="post" action="<?= e(url('applications/update.php')) ?>" class="row g-3"><input type="hidden" name="id" value="<?= e($id) ?>"><?php require __DIR__ . '/_form.php'; ?><div class="col-12"><button class="btn btn-primary" type="submit">Update application</button></div></form></div></div>
    <?php
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}

$application['requested_amount'] = (float) $application['requested_amount'];
$application['requested_term_months'] = (int) $application['requested_term_months'];
$application['interest_rate'] = $application['interest_rate'] === null ? null : round((float) $application['interest_rate'], 4);
$application['annual_debt_service_amount'] = calculate_annual_debt_service_amount($application['requested_amount'], $application['interest_rate'], $application['requested_term_months']);
$application['existing_exposure_amount'] = (float) $application['existing_exposure_amount'];
$application['proposed_total_exposure_amount'] = (float) $application['proposed_total_exposure_amount'];

$s = $pdo->prepare(
    'UPDATE credit_applications
     SET client_id = :client_id,
         application_date = :application_date,
         requested_amount = :requested_amount,
         currency = :currency,
         requested_term_months = :requested_term_months,
         interest_rate = :interest_rate,
         annual_debt_service_amount = :annual_debt_service_amount,
         credit_product = :credit_product,
         credit_purpose = :credit_purpose,
         repayment_source = :repayment_source,
         existing_exposure_amount = :existing_exposure_amount,
         proposed_total_exposure_amount = :proposed_total_exposure_amount,
         status = :status,
         priority = :priority,
         notes = :notes
     WHERE id = :id'
);
$data = $application;
unset($data['application_number']);
$s->execute($data);
log_action($pdo, 'update application', 'application', $id, $oldApplication, $application);
redirect(url('applications/view.php?id=' . $id));
