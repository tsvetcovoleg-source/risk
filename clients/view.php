<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'clients';
$pageTitle = 'Client card';
$id = get_int_param('id');
$client = null;
$relatedParties = [];
$applications = [];
$finDataRows = [];

if ($id !== null && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL');
    $statement->execute([$id]);
    $client = $statement->fetch();

    if ($client) {
        $statement = $pdo->prepare('SELECT * FROM client_related_parties WHERE client_id = ? AND deleted_at IS NULL ORDER BY id DESC');
        $statement->execute([$id]);
        $relatedParties = $statement->fetchAll();

        $statement = $pdo->prepare('SELECT * FROM credit_applications WHERE client_id = ? AND deleted_at IS NULL ORDER BY application_date DESC, id DESC');
        $statement->execute([$id]);
        $applications = $statement->fetchAll();

        try {
            $statement = $pdo->prepare('SELECT IDNO, REPORT_KEY, created_at, LENGTH(META_CSV) AS meta_size, LENGTH(BIL_CSV) AS bil_size, LENGTH(PNL_CSV) AS pnl_size, LENGTH(EQT_CSV) AS eqt_size, LENGTH(CF_CSV) AS cf_size FROM fin_data WHERE client_id = ? ORDER BY REPORT_KEY DESC');
            $statement->execute([$id]);
            $finDataRows = $statement->fetchAll();
        } catch (PDOException) {
            $finDataRows = [];
        }
    }
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';

if (!$id || !$client) {
    render_error_page('Client not found', 'The requested client does not exist or was archived.', url('clients/index.php'), 'Back to clients list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Client card</p>
            <h1 class="h2 mb-1"><?= e($client['client_name']) ?></h1>
            <p class="text-secondary mb-0">IDNO: <?= e($client['idno'] ?? '-') ?> · Status: <?= e($client['status']) ?></p>
        </div>
        <div class="align-self-lg-center d-flex gap-2">
            <a class="btn btn-primary" href="<?= e(url('clients/edit.php?id=' . $client['id'])) ?>">Edit client</a>
            <a class="btn btn-outline-primary" href="<?= e(url('applications/create.php?client_id=' . $client['id'])) ?>">Create application</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('clients/index.php')) ?>">Back to clients list</a>
        </div>
    </div>
</section>

<?php if (($_GET['fin_status'] ?? '') === 'loaded'): ?>
    <div class="alert alert-success border-0 shadow-sm">Financial data loaded into fin_data: <?= e((int) ($_GET['fin_count'] ?? 0)) ?> report(s).</div>
<?php elseif (($_GET['fin_status'] ?? '') === 'empty'): ?>
    <div class="alert alert-warning border-0 shadow-sm">Client was created, but no public financial reports were found for this IDNO.</div>
<?php elseif (!empty($_GET['fin_error'])): ?>
    <div class="alert alert-danger border-0 shadow-sm">
        <div><?= e($_GET['fin_error']) ?></div>
        <?php if (!empty($_GET['fin_debug'])): ?>
            <details class="mt-3">
                <summary class="fw-semibold">Debug details</summary>
                <pre class="bg-light border rounded p-3 mt-2 small text-break" style="white-space: pre-wrap;"><?= e($_GET['fin_debug']) ?></pre>
            </details>
        <?php endif; ?>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Main client data</h2>
        <div class="row g-3">
            <?php foreach ([
                'Client name' => $client['client_name'], 'IDNO' => $client['idno'], 'Legal form' => $client['legal_form'],
                'Registration date' => format_date($client['registration_date']), 'Activity sector' => $client['activity_sector'], 'CAEM code' => $client['caem_code'],
                'Address' => $client['address'], 'Phone' => $client['phone'], 'Email' => $client['email'], 'Website' => $client['website'], 'Status' => $client['status'], 'Notes' => $client['notes'],
            ] as $label => $value): ?>
                <div class="col-md-4">
                    <div class="text-secondary small"><?= e($label) ?></div>
                    <div class="fw-semibold"><?= e($value ?: '-') ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Financial data from Depozitar</h2>
            <span class="badge text-bg-secondary"><?= e(count($finDataRows)) ?> report(s)</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>IDNO</th><th>Report key</th><th>META</th><th>BIL</th><th>PNL</th><th>EQT</th><th>CF</th><th>Loaded at</th></tr></thead>
                <tbody>
                <?php if (empty($finDataRows)): ?><tr><td colspan="8" class="text-center text-secondary py-3">No fetched financial data in fin_data.</td></tr><?php endif; ?>
                <?php foreach ($finDataRows as $row): ?>
                    <tr>
                        <td><?= e($row['IDNO'] ?? '-') ?></td>
                        <td><?= e($row['REPORT_KEY'] ?? '-') ?></td>
                        <td><?= e((int) ($row['meta_size'] ?? 0)) ?> bytes</td>
                        <td><?= e((int) ($row['bil_size'] ?? 0)) ?> bytes</td>
                        <td><?= e((int) ($row['pnl_size'] ?? 0)) ?> bytes</td>
                        <td><?= e((int) ($row['eqt_size'] ?? 0)) ?> bytes</td>
                        <td><?= e((int) ($row['cf_size'] ?? 0)) ?> bytes</td>
                        <td><?= e(format_date($row['created_at'] ?? null, 'd.m.Y H:i')) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Related parties</h2>
            <a class="btn btn-sm btn-primary" href="<?= e(url('clients/related_party_create.php?client_id=' . $client['id'])) ?>">Add related party</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Party name</th><th>Party type</th><th>IDNO / IDNP</th><th>Relationship</th><th>Ownership</th><th>Beneficiary</th><th>Notes</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($relatedParties)): ?><tr><td colspan="8" class="text-center text-secondary py-3">No related parties.</td></tr><?php endif; ?>
                <?php foreach ($relatedParties as $party): ?>
                    <tr>
                        <td><?= e($party['party_name']) ?></td><td><?= e($party['party_type']) ?></td><td><?= e($party['idno_or_idnp'] ?? '-') ?></td><td><?= e($party['relationship_type']) ?></td><td><?= e($party['ownership_percent'] !== null ? $party['ownership_percent'] . '%' : '-') ?></td><td><?= e($party['is_beneficiary'] ? 'Yes' : 'No') ?></td><td><?= e($party['notes'] ?? '-') ?></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('clients/related_party_edit.php?id=' . $party['id'])) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= e(url('clients/related_party_delete.php?id=' . $party['id'])) ?>" onclick="return confirm('Archive this related party?');">Archive</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h5 mb-0">Credit applications</h2>
            <a class="btn btn-sm btn-primary" href="<?= e(url('applications/create.php?client_id=' . $client['id'])) ?>">Create new application for this client</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Application number</th><th>Date</th><th>Requested amount</th><th>Currency</th><th>Term</th><th>Product</th><th>Purpose</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if (empty($applications)): ?><tr><td colspan="9" class="text-center text-secondary py-3">No applications.</td></tr><?php endif; ?>
                <?php foreach ($applications as $application): ?>
                    <tr>
                        <td><?= e($application['application_number']) ?></td><td><?= e(format_date($application['application_date'])) ?></td><td><?= e(format_amount($application['requested_amount'], '')) ?></td><td><?= e($application['currency']) ?></td><td><?= e($application['requested_term_months']) ?> months</td><td><?= e($application['credit_product'] ?? '-') ?></td><td><?= e($application['credit_purpose'] ?? '-') ?></td><td><span class="badge text-bg-secondary"><?= e($application['status']) ?></span></td>
                        <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('applications/view.php?id=' . $application['id'])) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('applications/edit.php?id=' . $application['id'])) ?>">Edit</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
