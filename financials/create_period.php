<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'financials';
$pageTitle = 'Create financial period';
$periodTypes = ['annual', 'quarterly', 'interim', 'management'];
$dataSources = ['official_financial_statements', 'management_accounts', 'tax_reports', 'manual_input', 'other'];
$applicationId = get_int_param('application_id');
$selectedApplication = null;
$applications = [];

if ($pdo instanceof PDO) {
    if ($applicationId) {
        $statement = $pdo->prepare('SELECT ca.id, ca.application_number, c.client_name FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.id = ? AND ca.deleted_at IS NULL');
        $statement->execute([$applicationId]);
        $selectedApplication = $statement->fetch();
    } else {
        $statement = $pdo->prepare('SELECT ca.id, ca.application_number, c.client_name FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.deleted_at IS NULL ORDER BY ca.application_date DESC, ca.id DESC');
        $statement->execute();
        $applications = $statement->fetchAll();
    }
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if ($applicationId && !$selectedApplication) {
    render_error_page('Application not found', 'The selected credit application does not exist or was archived.', url('financials/index.php'), 'Back to financial statements');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Financial statements</p>
    <h1 class="h2 mb-0">Create financial period</h1>
</section>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('financials/store_period.php')) ?>" class="row g-3">
            <div class="col-12">
                <label class="form-label" for="application_id">Credit application <span class="text-danger">*</span></label>
                <?php if ($selectedApplication): ?>
                    <input type="hidden" name="application_id" value="<?= e($selectedApplication['id']) ?>">
                    <div class="form-control bg-light">
                        <?= e($selectedApplication['application_number']) ?> · <?= e($selectedApplication['client_name']) ?>
                    </div>
                <?php else: ?>
                    <select class="form-select" id="application_id" name="application_id" required>
                        <option value="">Select application</option>
                        <?php foreach ($applications as $application): ?>
                            <option value="<?= e($application['id']) ?>"><?= e($application['application_number']) ?> · <?= e($application['client_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="period_type">Period type <span class="text-danger">*</span></label>
                <select class="form-select" id="period_type" name="period_type" required>
                    <?php foreach ($periodTypes as $type): ?>
                        <option value="<?= e($type) ?>"><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="period_end_date">Period end date <span class="text-danger">*</span></label>
                <input class="form-control" type="date" id="period_end_date" name="period_end_date" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="period_label">Period label <span class="text-danger">*</span></label>
                <input class="form-control" id="period_label" name="period_label" maxlength="100" required placeholder="FY 2025, Q1 2026, Management May 2026">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="data_source">Data source</label>
                <select class="form-select" id="data_source" name="data_source">
                    <?php foreach ($dataSources as $source): ?>
                        <option value="<?= e($source) ?>" <?= selected_attr($source, 'manual_input') ?>><?= e($source) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <div class="form-check mt-4">
                    <input class="form-check-input" type="checkbox" id="is_audited" name="is_audited" value="1">
                    <label class="form-check-label" for="is_audited">Audited financial statements</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea class="form-control" id="notes" name="notes" rows="4"></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Create period</button>
                <a class="btn btn-outline-secondary" href="<?= e($selectedApplication ? url('applications/view.php?id=' . $selectedApplication['id']) : url('financials/index.php')) ?>">Cancel</a>
            </div>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
