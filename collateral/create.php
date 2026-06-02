<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'collateral';
$pageTitle = 'Create collateral';
$collateralTypes = ['real_estate', 'vehicle', 'equipment', 'inventory', 'deposit', 'guarantee', 'suretyship', 'other'];
$pledgeStatuses = ['proposed', 'under_review', 'accepted', 'rejected', 'registered', 'released'];
$currencies = ['MDL', 'EUR', 'USD'];
$applicationId = get_int_param('application_id');
$fixedApplication = null;
$applications = [];
$collateral = [
    'application_id' => $applicationId,
    'collateral_type' => 'real_estate',
    'description' => '',
    'owner_name' => '',
    'estimated_market_value' => '',
    'accepted_collateral_value' => '',
    'currency' => 'MDL',
    'valuation_date' => '',
    'valuation_source' => '',
    'pledge_status' => 'proposed',
    'notes' => '',
];

if ($pdo instanceof PDO) {
    if ($applicationId) {
        $statement = $pdo->prepare('SELECT ca.id, ca.application_number, ca.requested_amount, ca.currency, c.client_name FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.id = ? AND ca.deleted_at IS NULL AND c.deleted_at IS NULL');
        $statement->execute([$applicationId]);
        $fixedApplication = $statement->fetch();
        if ($fixedApplication) {
            $collateral['currency'] = $fixedApplication['currency'];
        }
    }

    $statement = $pdo->prepare('SELECT ca.id, ca.application_number, ca.requested_amount, ca.currency, c.client_name FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.deleted_at IS NULL AND c.deleted_at IS NULL ORDER BY ca.application_date DESC, ca.id DESC');
    $statement->execute();
    $applications = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if ($applicationId && !$fixedApplication) {
    render_error_page('Application not found', 'The selected credit application does not exist or was archived.', url('collateral/create.php'), 'Choose another application');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Collateral</p>
    <h1 class="h2 mb-3">Create collateral</h1>
    <p class="text-secondary mb-0">Register proposed collateral manually for an SME credit application.</p>
</section>

<?php if ($fixedApplication): ?>
    <div class="alert alert-info border-0 shadow-sm">
        <strong>Application:</strong> <?= e($fixedApplication['application_number']) ?> ·
        <strong>Client:</strong> <?= e($fixedApplication['client_name']) ?> ·
        <strong>Requested amount:</strong> <?= e(format_currency_amount($fixedApplication['requested_amount'], $fixedApplication['currency'])) ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('collateral/store.php')) ?>" class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="application_id">Credit application <span class="text-danger">*</span></label>
                <?php if ($fixedApplication): ?>
                    <input type="hidden" name="application_id" value="<?= e($fixedApplication['id']) ?>">
                    <input class="form-control" value="<?= e($fixedApplication['application_number'] . ' · ' . $fixedApplication['client_name']) ?>" disabled>
                <?php else: ?>
                    <select class="form-select" id="application_id" name="application_id" required>
                        <option value="">Select application</option>
                        <?php foreach ($applications as $applicationOption): ?>
                            <option value="<?= e($applicationOption['id']) ?>" <?= selected_attr($collateral['application_id'], $applicationOption['id']) ?>>
                                <?= e($applicationOption['application_number'] . ' · ' . $applicationOption['client_name'] . ' · ' . format_currency_amount($applicationOption['requested_amount'], $applicationOption['currency'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
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
            <div class="col-md-6">
                <label class="form-label" for="owner_name">Owner name</label>
                <input class="form-control" id="owner_name" name="owner_name" value="<?= e($collateral['owner_name']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="estimated_market_value">Estimated market value <span class="text-danger">*</span></label>
                <input class="form-control" id="estimated_market_value" name="estimated_market_value" type="number" min="0" step="0.01" value="<?= e($collateral['estimated_market_value']) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label" for="accepted_collateral_value">Accepted collateral value <span class="text-danger">*</span></label>
                <input class="form-control" id="accepted_collateral_value" name="accepted_collateral_value" type="number" min="0" step="0.01" value="<?= e($collateral['accepted_collateral_value']) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="valuation_date">Valuation date</label>
                <input class="form-control" id="valuation_date" name="valuation_date" type="date" value="<?= e($collateral['valuation_date']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="valuation_source">Valuation source</label>
                <input class="form-control" id="valuation_source" name="valuation_source" value="<?= e($collateral['valuation_source']) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="pledge_status">Pledge status <span class="text-danger">*</span></label>
                <select class="form-select" id="pledge_status" name="pledge_status" required>
                    <?php foreach ($pledgeStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected_attr($collateral['pledge_status'], $status) ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="3"><?= e($collateral['notes']) ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save collateral</button>
                <a class="btn btn-outline-secondary" href="<?= e($fixedApplication ? url('applications/view.php?id=' . $fixedApplication['id']) : url('collateral/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
