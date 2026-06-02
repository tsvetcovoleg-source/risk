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
