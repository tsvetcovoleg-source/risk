<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

function collateral_update_error(array $errors, ?int $id = null): never
{
    global $currentSection, $pageTitle;
    $currentSection = 'collateral';
    $pageTitle = 'Collateral validation error';
    require dirname(__DIR__) . '/header.php';
    require dirname(__DIR__) . '/sidebar.php';
    ?>
    <section class="page-heading mb-4"><p class="eyebrow mb-2">Collateral</p><h1 class="h2 mb-3">Cannot update collateral</h1></section>
    <div class="alert alert-danger border-0 shadow-sm"><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?></ul></div>
    <a class="btn btn-outline-secondary" href="<?= e($id ? url('collateral/edit.php?id=' . $id) : url('collateral/index.php')) ?>">Back</a>
    <?php
    require dirname(__DIR__) . '/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('collateral/index.php'));
}
if (!$pdo instanceof PDO) {
    collateral_update_error(['Database connection is not available.']);
}

$id = post_int('id');
if (!$id) {
    collateral_update_error(['Collateral id is required.']);
}

$statement = $pdo->prepare('SELECT * FROM collateral WHERE id = ? AND deleted_at IS NULL');
$statement->execute([$id]);
$oldCollateral = $statement->fetch();
if (!$oldCollateral) {
    collateral_update_error(['The requested collateral record was not found.']);
}

$collateralTypes = ['real_estate', 'vehicle', 'equipment', 'inventory', 'deposit', 'guarantee', 'suretyship', 'other'];
$pledgeStatuses = ['proposed', 'under_review', 'accepted', 'rejected', 'registered', 'released'];
$currencies = ['MDL', 'EUR', 'USD'];
$data = [
    'id' => $id,
    'collateral_type' => clean_input(post_value('collateral_type')),
    'description' => clean_input(post_value('description')),
    'owner_name' => nullable_input(post_value('owner_name')),
    'estimated_market_value' => parse_decimal(post_value('estimated_market_value')),
    'accepted_collateral_value' => parse_decimal(post_value('accepted_collateral_value')),
    'currency' => clean_input(post_value('currency')),
    'valuation_date' => nullable_input(post_value('valuation_date')),
    'valuation_source' => nullable_input(post_value('valuation_source')),
    'pledge_status' => clean_input(post_value('pledge_status', 'proposed')),
    'notes' => nullable_input(post_value('notes')),
];
$errors = [];

if (!in_array($data['collateral_type'], $collateralTypes, true)) {
    $errors[] = 'Invalid collateral type.';
}
if ($data['description'] === '') {
    $errors[] = 'Description is required.';
}
if ($data['estimated_market_value'] === null || (float) $data['estimated_market_value'] < 0) {
    $errors[] = 'Estimated market value must be a number not less than 0.';
}
if ($data['accepted_collateral_value'] === null || (float) $data['accepted_collateral_value'] < 0) {
    $errors[] = 'Accepted collateral value must be a number not less than 0.';
}
if ($data['estimated_market_value'] !== null && $data['accepted_collateral_value'] !== null && (float) $data['accepted_collateral_value'] > (float) $data['estimated_market_value']) {
    $errors[] = 'Accepted collateral value cannot be greater than estimated market value.';
}
if (!in_array($data['currency'], $currencies, true)) {
    $errors[] = 'Invalid currency.';
}
if (!is_valid_date($data['valuation_date'])) {
    $errors[] = 'Valuation date must use YYYY-MM-DD format.';
}
if (!in_array($data['pledge_status'], $pledgeStatuses, true)) {
    $errors[] = 'Invalid pledge status.';
}

if ($errors) {
    collateral_update_error($errors, $id);
}

$statement = $pdo->prepare('UPDATE collateral SET collateral_type = :collateral_type, description = :description, owner_name = :owner_name, estimated_market_value = :estimated_market_value, accepted_collateral_value = :accepted_collateral_value, currency = :currency, valuation_date = :valuation_date, valuation_source = :valuation_source, pledge_status = :pledge_status, notes = :notes WHERE id = :id');
$statement->execute($data);
log_action($pdo, 'update collateral', 'collateral', $id, $oldCollateral, $data);

redirect(url('collateral/view.php?id=' . $id));
