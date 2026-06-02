<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'financials';
$pageTitle = 'Edit financial period';
$periodTypes = ['annual', 'quarterly', 'interim', 'management'];
$dataSources = ['official_financial_statements', 'management_accounts', 'tax_reports', 'manual_input', 'other'];
$id = get_int_param('id');
$period = null;

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT fp.*, ca.application_number, c.client_name FROM financial_periods fp INNER JOIN credit_applications ca ON ca.id = fp.application_id INNER JOIN clients c ON c.id = ca.client_id WHERE fp.id = ? AND fp.deleted_at IS NULL');
    $statement->execute([$id]);
    $period = $statement->fetch();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$period) {
    render_error_page('Financial period not found', 'The requested financial period does not exist or was deleted.', url('financials/index.php'), 'Back to financial statements');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4"><p class="eyebrow mb-2">Financial statements</p><h1 class="h2 mb-0">Edit financial period</h1></section>
<div class="card border-0 shadow-sm"><div class="card-body p-4">
    <div class="alert alert-light border">Application: <strong><?= e($period['application_number']) ?></strong> · <?= e($period['client_name']) ?></div>
    <form method="post" action="<?= e(url('financials/update_period.php')) ?>" class="row g-3">
        <input type="hidden" name="id" value="<?= e($period['id']) ?>">
        <div class="col-md-6"><label class="form-label" for="period_type">Period type</label><select class="form-select" id="period_type" name="period_type" required><?php foreach ($periodTypes as $type): ?><option value="<?= e($type) ?>" <?= selected_attr($period['period_type'], $type) ?>><?= e($type) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><label class="form-label" for="period_end_date">Period end date</label><input class="form-control" type="date" id="period_end_date" name="period_end_date" value="<?= e($period['period_end_date']) ?>" required></div>
        <div class="col-md-6"><label class="form-label" for="period_label">Period label</label><input class="form-control" id="period_label" name="period_label" value="<?= e($period['period_label']) ?>" maxlength="100" required></div>
        <div class="col-md-6"><label class="form-label" for="data_source">Data source</label><select class="form-select" id="data_source" name="data_source"><?php foreach ($dataSources as $source): ?><option value="<?= e($source) ?>" <?= selected_attr($period['data_source'], $source) ?>><?= e($source) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" id="is_audited" name="is_audited" value="1" <?= checked_attr($period['is_audited']) ?>><label class="form-check-label" for="is_audited">Audited financial statements</label></div></div>
        <div class="col-12"><label class="form-label" for="notes">Notes</label><textarea class="form-control" id="notes" name="notes" rows="4"><?= e($period['notes']) ?></textarea></div>
        <div class="col-12 d-flex gap-2"><button class="btn btn-primary" type="submit">Save changes</button><a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">Cancel</a></div>
    </form>
</div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
