<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'collateral';
$pageTitle = 'Collateral';
$collateralTypes = ['real_estate', 'vehicle', 'equipment', 'inventory', 'deposit', 'guarantee', 'suretyship', 'other'];
$pledgeStatuses = ['proposed', 'under_review', 'accepted', 'rejected', 'registered', 'released'];
$search = clean_input($_GET['q'] ?? '');
$typeFilter = clean_input($_GET['collateral_type'] ?? '');
$statusFilter = clean_input($_GET['pledge_status'] ?? '');
$items = [];

if (!in_array($typeFilter, $collateralTypes, true)) {
    $typeFilter = '';
}
if (!in_array($statusFilter, $pledgeStatuses, true)) {
    $statusFilter = '';
}

if ($pdo instanceof PDO) {
    $where = ['co.deleted_at IS NULL', 'ca.deleted_at IS NULL', 'c.deleted_at IS NULL'];
    $params = [];

    if ($search !== '') {
        $where[] = '(ca.application_number LIKE :search OR c.client_name LIKE :search OR c.idno LIKE :search OR co.description LIKE :search OR co.owner_name LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    if ($typeFilter !== '') {
        $where[] = 'co.collateral_type = :collateral_type';
        $params['collateral_type'] = $typeFilter;
    }
    if ($statusFilter !== '') {
        $where[] = 'co.pledge_status = :pledge_status';
        $params['pledge_status'] = $statusFilter;
    }

    $statement = $pdo->prepare(
        'SELECT co.*, ca.application_number, ca.client_id, c.client_name, c.idno
         FROM collateral co
         INNER JOIN credit_applications ca ON ca.id = co.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY co.created_at DESC, co.id DESC'
    );
    $statement->execute($params);
    $items = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Manual collateral registry</p>
            <h1 class="h2 mb-3">Collateral</h1>
            <p class="text-secondary mb-0">Track pledged assets, accepted values, and review status without external registry integrations.</p>
        </div>
        <div class="align-self-lg-center">
            <a class="btn btn-primary" href="<?= e(url('collateral/create.php')) ?>">Add collateral</a>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form class="row g-3" method="get" action="<?= e(url('collateral/index.php')) ?>">
            <div class="col-lg-5">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Application, client, IDNO, description, owner">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="collateral_type">Collateral type</label>
                <select class="form-select" id="collateral_type" name="collateral_type">
                    <option value="">All types</option>
                    <?php foreach ($collateralTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= selected_attr($typeFilter, $type) ?>><?= e(ucwords(str_replace('_', ' ', $type))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="pledge_status">Pledge status</label>
                <select class="form-select" id="pledge_status" name="pledge_status">
                    <option value="">All statuses</option>
                    <?php foreach ($pledgeStatuses as $status): ?>
                        <option value="<?= e($status) ?>" <?= selected_attr($statusFilter, $status) ?>><?= e(ucwords(str_replace('_', ' ', $status))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-1 d-flex align-items-end">
                <button class="btn btn-outline-primary w-100" type="submit">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Application number</th>
                        <th>Client name</th>
                        <th>Collateral type</th>
                        <th>Description</th>
                        <th>Owner name</th>
                        <th>Estimated market value</th>
                        <th>Accepted collateral value</th>
                        <th>Currency</th>
                        <th>Valuation date</th>
                        <th>Pledge status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$items): ?>
                    <tr><td colspan="11" class="text-center text-secondary py-4">No collateral records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?= e($item['application_number']) ?></td>
                        <td><?= e($item['client_name']) ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $item['collateral_type']))) ?></td>
                        <td><?= e($item['description']) ?></td>
                        <td><?= e($item['owner_name'] ?: 'N/A') ?></td>
                        <td><?= e(format_currency_amount($item['estimated_market_value'], $item['currency'])) ?></td>
                        <td><?= e(format_currency_amount($item['accepted_collateral_value'], $item['currency'])) ?></td>
                        <td><?= e($item['currency']) ?></td>
                        <td><?= e($item['valuation_date'] ? format_date($item['valuation_date']) : 'N/A') ?></td>
                        <td><?= e(ucwords(str_replace('_', ' ', $item['pledge_status']))) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm" role="group">
                                <a class="btn btn-outline-primary" href="<?= e(url('collateral/view.php?id=' . $item['id'])) ?>">View</a>
                                <a class="btn btn-outline-secondary" href="<?= e(url('collateral/edit.php?id=' . $item['id'])) ?>">Edit</a>
                                <a class="btn btn-outline-info" href="<?= e(url('applications/view.php?id=' . $item['application_id'])) ?>">Application</a>
                                <a class="btn btn-outline-info" href="<?= e(url('clients/view.php?id=' . $item['client_id'])) ?>">Client</a>
                            </div>
                            <form method="post" action="<?= e(url('collateral/delete.php')) ?>" class="d-inline" onsubmit="return confirm('Delete this collateral record?');">
                                <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                                <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
