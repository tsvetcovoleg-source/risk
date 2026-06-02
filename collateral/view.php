<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'collateral';
$pageTitle = 'Collateral card';
$id = get_int_param('id');
$collateral = null;
$applicationCollateral = [];
$summary = null;

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare(
        'SELECT co.*, ca.application_number, ca.requested_amount, ca.currency AS application_currency, ca.status AS application_status, ca.client_id, c.client_name, c.idno
         FROM collateral co
         INNER JOIN credit_applications ca ON ca.id = co.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE co.id = ? AND co.deleted_at IS NULL AND ca.deleted_at IS NULL AND c.deleted_at IS NULL'
    );
    $statement->execute([$id]);
    $collateral = $statement->fetch();

    if ($collateral) {
        $statement = $pdo->prepare('SELECT * FROM collateral WHERE application_id = ? AND deleted_at IS NULL ORDER BY id ASC');
        $statement->execute([$collateral['application_id']]);
        $applicationCollateral = $statement->fetchAll();
        $summary = calculate_collateral_summary($applicationCollateral, $collateral['requested_amount'], $collateral['application_currency']);
    }
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
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Collateral card</p>
            <h1 class="h2 mb-1"><?= e(ucwords(str_replace('_', ' ', $collateral['collateral_type']))) ?></h1>
            <p class="text-secondary mb-0"><?= e($collateral['application_number']) ?> · <?= e($collateral['client_name']) ?></p>
        </div>
        <div class="align-self-lg-center d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="<?= e(url('collateral/edit.php?id=' . $collateral['id'])) ?>">Edit collateral</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $collateral['application_id'])) ?>">Back to application</a>
            <a class="btn btn-outline-primary" href="<?= e(url('collateral/index.php')) ?>">Back to collateral list</a>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <h2 class="h5 mb-3">Application information</h2>
            <div class="row g-3">
                <?php foreach ([
                    'Application number' => $collateral['application_number'],
                    'Client name' => $collateral['client_name'],
                    'IDNO' => $collateral['idno'] ?: 'N/A',
                    'Requested amount' => format_currency_amount($collateral['requested_amount'], $collateral['application_currency']),
                    'Currency' => $collateral['application_currency'],
                    'Application status' => $collateral['application_status'],
                ] as $label => $value): ?>
                    <div class="col-md-6"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value) ?></div></div>
                <?php endforeach; ?>
            </div>
            <div class="mt-3 d-flex gap-2 flex-wrap">
                <a class="btn btn-outline-primary" href="<?= e(url('applications/view.php?id=' . $collateral['application_id'])) ?>">Open application</a>
                <a class="btn btn-outline-primary" href="<?= e(url('clients/view.php?id=' . $collateral['client_id'])) ?>">Open client</a>
            </div>
        </div></div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100"><div class="card-body p-4">
            <h2 class="h5 mb-3">Collateral information</h2>
            <div class="row g-3">
                <?php foreach ([
                    'Collateral type' => ucwords(str_replace('_', ' ', $collateral['collateral_type'])),
                    'Description' => $collateral['description'],
                    'Owner name' => $collateral['owner_name'] ?: 'N/A',
                    'Estimated market value' => format_currency_amount($collateral['estimated_market_value'], $collateral['currency']),
                    'Accepted collateral value' => format_currency_amount($collateral['accepted_collateral_value'], $collateral['currency']),
                    'Currency' => $collateral['currency'],
                    'Valuation date' => $collateral['valuation_date'] ? format_date($collateral['valuation_date']) : 'N/A',
                    'Valuation source' => $collateral['valuation_source'] ?: 'N/A',
                    'Pledge status' => ucwords(str_replace('_', ' ', $collateral['pledge_status'])),
                    'Notes' => $collateral['notes'] ?: 'N/A',
                ] as $label => $value): ?>
                    <div class="col-md-6"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value) ?></div></div>
                <?php endforeach; ?>
            </div>
        </div></div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Collateral coverage analytics</h2>
        <?php foreach ($summary['warnings'] as $warning): ?>
            <div class="alert alert-warning"><?= e($warning) ?></div>
        <?php endforeach; ?>
        <div class="row g-3">
            <div class="col-md-3"><div class="text-secondary small">Requested amount</div><div class="fw-semibold"><?= e(format_currency_amount($summary['requested_amount'], $summary['application_currency'])) ?></div></div>
            <div class="col-md-3"><div class="text-secondary small">Total accepted collateral value</div><div class="fw-semibold"><?= e(format_currency_amount($summary['total_accepted_application_currency'], $summary['application_currency'])) ?></div></div>
            <div class="col-md-3"><div class="text-secondary small">Collateral coverage ratio</div><div class="fw-semibold"><?= e(format_percent($summary['collateral_coverage_ratio'])) ?></div><div class="small text-secondary"><?= e(interpret_collateral_coverage($summary['collateral_coverage_ratio'])) ?></div></div>
            <div class="col-md-3"><div class="text-secondary small">LTV</div><div class="fw-semibold"><?= e(format_percent($summary['ltv'])) ?></div><div class="small text-secondary"><?= e(interpret_ltv($summary['ltv'])) ?></div></div>
        </div>
        <p class="text-secondary small mt-3 mb-0">Coverage interpretation is analytical support only and is not an automated credit decision.</p>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
