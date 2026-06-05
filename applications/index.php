<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'applications';
$pageTitle = 'Credit applications';
$statuses = ['draft', 'submitted', 'in_analysis', 'risk_review', 'committee_review', 'approved', 'approved_with_conditions', 'rejected', 'cancelled', 'disbursed'];
$search = clean_input($_GET['q'] ?? '');
$statusFilter = clean_input($_GET['status'] ?? '');
$applications = [];

if ($pdo instanceof PDO) {
    $sql = 'SELECT ca.*, c.client_name, c.idno FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.deleted_at IS NULL';
    $params = [];
    if ($search !== '') {
        $sql .= ' AND (ca.application_number LIKE :search OR c.client_name LIKE :search OR c.idno LIKE :search OR ca.credit_product LIKE :search OR ca.credit_purpose LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    if ($statusFilter !== '' && in_array($statusFilter, $statuses, true)) {
        $sql .= ' AND ca.status = :status';
        $params['status'] = $statusFilter;
    }
    $sql .= ' ORDER BY ca.application_date DESC, ca.id DESC';
    $s = $pdo->prepare($sql);
    $s->execute($params);
    $applications = $s->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4"><div class="d-flex flex-column flex-lg-row justify-content-between gap-3"><div><p class="eyebrow mb-2">Application registry</p><h1 class="h2 mb-0">Credit applications</h1></div><div class="align-self-lg-center"><a class="btn btn-primary" href="<?= e(url('applications/create.php')) ?>">Create new application</a></div></div></section>
<div class="card border-0 shadow-sm mb-4"><div class="card-body"><form class="row g-3" method="get" action="<?= e(url('applications/index.php')) ?>"><div class="col-md-7"><label class="form-label" for="q">Search</label><input class="form-control" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Application number, client, IDNO, product, purpose"></div><div class="col-md-3"><label class="form-label" for="status">Status</label><select class="form-select" id="status" name="status"><option value="">All statuses</option><?php foreach ($statuses as $status): ?><option value="<?= e($status) ?>" <?= selected_attr($statusFilter, $status) ?>><?= e($status) ?></option><?php endforeach; ?></select></div><div class="col-md-2 d-flex align-items-end gap-2"><button class="btn btn-outline-primary w-100" type="submit">Search</button><a class="btn btn-outline-secondary" href="<?= e(url('applications/index.php')) ?>">Reset</a></div></form></div></div>
<div class="card border-0 shadow-sm"><div class="card-body p-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Application number</th><th>Client name</th><th>Date</th><th>Requested amount</th><th>Interest rate</th><th>Annual debt service</th><th>Requested term</th><th>Credit product</th><th>Status</th><th>Priority</th><th class="text-end">Actions</th></tr></thead><tbody><?php if (!$applications): ?><tr><td colspan="11" class="text-center text-secondary py-4">No applications found.</td></tr><?php endif; ?><?php foreach ($applications as $application): ?><tr><td><?= e($application['application_number']) ?></td><td><?= e($application['client_name']) ?></td><td><?= e(format_date($application['application_date'])) ?></td><td><?= e(format_amount($application['requested_amount'], $application['currency'])) ?></td><td><?= e(format_interest_rate($application['interest_rate'] ?? null)) ?></td><td><?= e(($application['annual_debt_service_amount'] ?? null) === null || ($application['annual_debt_service_amount'] ?? '') === '' ? 'N/A' : format_amount($application['annual_debt_service_amount'], $application['currency'])) ?></td><td><?= e($application['requested_term_months']) ?> months</td><td><?= e($application['credit_product'] ?? '-') ?></td><td><span class="badge text-bg-secondary"><?= e($application['status']) ?></span></td><td><?= e($application['priority']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('applications/view.php?id=' . $application['id'])) ?>">View</a> <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('applications/edit.php?id=' . $application['id'])) ?>">Edit</a> <a class="btn btn-sm btn-outline-danger" href="<?= e(url('applications/delete.php?id=' . $application['id'])) ?>" onclick="return confirm('Archive this application?');">Archive</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
