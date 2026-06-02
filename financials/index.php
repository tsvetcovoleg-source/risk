<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'financials';
$pageTitle = 'Financial statements';
$periodTypes = ['annual', 'quarterly', 'interim', 'management'];
$search = clean_input($_GET['q'] ?? '');
$periodType = clean_input($_GET['period_type'] ?? '');
$periods = [];

if ($pdo instanceof PDO) {
    $sql = 'SELECT fp.*, ca.application_number, c.client_name, c.idno
            FROM financial_periods fp
            INNER JOIN credit_applications ca ON ca.id = fp.application_id
            INNER JOIN clients c ON c.id = ca.client_id
            WHERE fp.deleted_at IS NULL';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (ca.application_number LIKE :search OR c.client_name LIKE :search OR c.idno LIKE :search OR fp.period_label LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    if ($periodType !== '' && in_array($periodType, $periodTypes, true)) {
        $sql .= ' AND fp.period_type = :period_type';
        $params['period_type'] = $periodType;
    }

    $sql .= ' ORDER BY fp.period_end_date DESC, fp.id DESC';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $periods = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Manual SME financial data</p>
            <h1 class="h2 mb-0">Financial statements</h1>
        </div>
        <div class="align-self-lg-center">
            <a class="btn btn-primary" href="<?= e(url('financials/create_period.php')) ?>">Add financial period</a>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3" method="get" action="<?= e(url('financials/index.php')) ?>">
            <div class="col-md-7">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Application number, client, IDNO, period label">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="period_type">Period type</label>
                <select class="form-select" id="period_type" name="period_type">
                    <option value="">All period types</option>
                    <?php foreach ($periodTypes as $type): ?>
                        <option value="<?= e($type) ?>" <?= selected_attr($periodType, $type) ?>><?= e($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary w-100" type="submit">Search</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('financials/index.php')) ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Application number</th>
                        <th>Client name</th>
                        <th>Period label</th>
                        <th>Period type</th>
                        <th>Period end date</th>
                        <th>Is audited</th>
                        <th>Data source</th>
                        <th>Created date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$periods): ?>
                        <tr><td colspan="9" class="text-center text-secondary py-4">No financial statements found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($periods as $period): ?>
                        <tr>
                            <td><?= e($period['application_number']) ?></td>
                            <td><?= e($period['client_name']) ?></td>
                            <td><?= e($period['period_label']) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e($period['period_type']) ?></span></td>
                            <td><?= e(format_date($period['period_end_date'])) ?></td>
                            <td><?= e($period['is_audited'] ? 'Yes' : 'No') ?></td>
                            <td><?= e($period['data_source']) ?></td>
                            <td><?= e(format_date($period['created_at'], 'd.m.Y H:i')) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">View</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('financials/edit_period.php?id=' . $period['id'])) ?>">Edit period</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('financials/edit_balance.php?id=' . $period['id'])) ?>">Edit balance</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('financials/edit_income.php?id=' . $period['id'])) ?>">Edit income</a>
                                <form class="d-inline" method="post" action="<?= e(url('financials/delete_period.php')) ?>" onsubmit="return confirm('Delete this financial period?');">
                                    <input type="hidden" name="id" value="<?= e($period['id']) ?>">
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
