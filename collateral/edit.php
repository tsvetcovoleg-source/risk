<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'collateral';
$pageTitle = 'Edit collateral';
$collateralTypes = ['real_estate', 'vehicle', 'equipment', 'inventory', 'deposit', 'guarantee', 'suretyship', 'other'];
$pledgeStatuses = ['proposed', 'under_review', 'accepted', 'rejected', 'registered', 'released'];
$currencies = ['MDL', 'EUR', 'USD'];
$id = get_int_param('id');
$collateral = null;

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare(
        'SELECT co.*, ca.application_number, ca.requested_amount, ca.currency AS application_currency, c.client_name
         FROM collateral co
         INNER JOIN credit_applications ca ON ca.id = co.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE co.id = ? AND co.deleted_at IS NULL AND ca.deleted_at IS NULL AND c.deleted_at IS NULL'
    );
    $statement->execute([$id]);
    $collateral = $statement->fetch();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$collateral) {
    render_error_page('Collateral not found', 'The requested collateral record does not exist or was archived.', url('collateral/index.php'), 'Back to collateral list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Collateral</p>
    <h1 class="h2 mb-3">Edit collateral</h1>
    <p class="text-secondary mb-0"><?= e($collateral['application_number']) ?> · <?= e($collateral['client_name']) ?></p>
</section>

<div class="alert alert-info border-0 shadow-sm">
    <strong>Application:</strong> <?= e($collateral['application_number']) ?> ·
    <strong>Client:</strong> <?= e($collateral['client_name']) ?> ·
    <strong>Requested amount:</strong> <?= e(format_currency_amount($collateral['requested_amount'], $collateral['application_currency'])) ?>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('collateral/update.php')) ?>" class="row g-3">
            <input type="hidden" name="id" value="<?= e($collateral['id']) ?>">
            <div class="col-md-6">
                <label class="form-label">Credit application</label>
                <input class="form-control" value="<?= e($collateral['application_number'] . ' · ' . $collateral['client_name']) ?>" disabled>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="collateral_type">Collateral type <span class="text-danger">*</span></label>
                <select class="form-select" id="collateral_type" name="collateral_type" required>
                    <?php foreach ($collateralTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= selected_attr($collateral['collateral_type'], $type) ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="currency">Currency <span class="text-danger">*</span></label>
                <select class="form-select" id="currency" name="currency" required>
                    <?php foreach ($currencies as $currency): ?>
                        <option value="<?= e($currency) ?>" <?= selected_attr($collateral['currency'], $currency) ?>><?= e($currency) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="description">Description <span class="text-danger">*</span></label>
                <textarea class="form-control" id="description" name="description" rows="3" required><?= e($collateral['description']) ?></textarea>
            </div>
            <div class="col-md-6"><label class="form-label" for="owner_name">Owner name</label><input class="form-control" id="owner_name" name="owner_name" value="<?= e($collateral['owner_name']) ?>"></div>
            <div class="col-md-3"><label class="form-label" for="estimated_market_value">Estimated market value <span class="text-danger">*</span></label><input class="form-control" id="estimated_market_value" name="estimated_market_value" type="number" min="0" step="0.01" value="<?= e($collateral['estimated_market_value']) ?>" required></div>
            <div class="col-md-3"><label class="form-label" for="accepted_collateral_value">Accepted collateral value <span class="text-danger">*</span></label><input class="form-control" id="accepted_collateral_value" name="accepted_collateral_value" type="number" min="0" step="0.01" value="<?= e($collateral['accepted_collateral_value']) ?>" required></div>
            <div class="col-md-4"><label class="form-label" for="valuation_date">Valuation date</label><input class="form-control" id="valuation_date" name="valuation_date" type="date" value="<?= e($collateral['valuation_date']) ?>"></div>
            <div class="col-md-4"><label class="form-label" for="valuation_source">Valuation source</label><input class="form-control" id="valuation_source" name="valuation_source" value="<?= e($collateral['valuation_source']) ?>"></div>
            <div class="col-md-4">
                <label class="form-label" for="pledge_status">Pledge status <span class="text-danger">*</span></label>
                <select class="form-select" id="pledge_status" name="pledge_status" required>
                    <?php foreach ($pledgeStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected_attr($collateral['pledge_status'], $status) ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="3"><?= e($collateral['notes']) ?></textarea></div>
            <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Update collateral</button><a class="btn btn-outline-secondary" href="<?= e(url('collateral/view.php?id=' . $collateral['id'])) ?>">Cancel</a></div>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
