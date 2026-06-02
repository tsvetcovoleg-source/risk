<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'ratios';
$pageTitle = 'Financial ratios';
$search = clean_input($_GET['q'] ?? '');
$status = clean_input($_GET['status'] ?? '');
$statuses = ['draft','submitted','in_analysis','risk_review','committee_review','approved','approved_with_conditions','rejected','cancelled','disbursed'];
$rows = [];

if ($pdo instanceof PDO) {
    $where = ['fp.deleted_at IS NULL', 'ca.deleted_at IS NULL'];
    $params = [];
    if ($search !== '') {
        $where[] = '(ca.application_number LIKE :search OR c.client_name LIKE :search OR c.idno LIKE :search OR fp.period_label LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    if ($status !== '' && in_array($status, $statuses, true)) {
        $where[] = 'ca.status = :status';
        $params['status'] = $status;
    }

    $statement = $pdo->prepare(
        'SELECT fr.*, fp.period_label, fp.period_end_date, ca.application_number, ca.status, c.client_name, c.idno
         FROM financial_ratios fr
         INNER JOIN financial_periods fp ON fp.id = fr.financial_period_id
         INNER JOIN credit_applications ca ON ca.id = fr.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY fr.calculated_at DESC, fp.period_end_date DESC'
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Analysis layer</p>
    <h1 class="h2 mb-3">Financial ratios</h1>
    <p class="text-secondary mb-0">Calculated liquidity, leverage, profitability, coverage, and growth indicators for SME credit applications.</p>
</section>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form class="row g-3 align-items-end" method="get">
            <div class="col-lg-6">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Application, client, IDNO, period label">
            </div>
            <div class="col-lg-3">
                <label class="form-label" for="status">Application status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All statuses</option>
                    <?php foreach ($statuses as $option): ?>
                        <option value="<?= e($option) ?>" <?= selected_attr($status, $option) ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('ratios/index.php')) ?>">Reset</a>
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
                        <th>Application number</th><th>Client name</th><th>Period label</th><th>Period end date</th>
                        <th>Current ratio</th><th>Debt to equity</th><th>Debt to assets</th><th>EBITDA margin</th><th>Net profit margin</th><th>Interest coverage</th><th>Simplified DSCR</th><th>Calculated at</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td colspan="13" class="text-center text-secondary py-3">No calculated ratio records found.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['application_number']) ?></td><td><?= e($row['client_name']) ?></td><td><?= e($row['period_label']) ?></td><td><?= e(format_date($row['period_end_date'])) ?></td>
                            <td><?= e(format_ratio($row['current_ratio'])) ?></td><td><?= e(format_ratio($row['debt_to_equity'])) ?></td><td><?= e(format_ratio($row['debt_to_assets'])) ?></td><td><?= e(format_ratio($row['ebitda_margin'], true)) ?></td><td><?= e(format_ratio($row['net_profit_margin'], true)) ?></td><td><?= e(format_ratio($row['interest_coverage_ratio'])) ?></td><td><?= e(format_ratio($row['debt_service_coverage_ratio'])) ?></td><td><?= e(format_date($row['calculated_at'], 'd.m.Y H:i')) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('ratios/view.php?financial_period_id=' . $row['financial_period_id'])) ?>">View ratios</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $row['financial_period_id'])) ?>">View period</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $row['application_id'])) ?>">Application</a>
                                <form class="d-inline" method="post" action="<?= e(url('ratios/calculate.php')) ?>"><input type="hidden" name="financial_period_id" value="<?= e($row['financial_period_id']) ?>"><button class="btn btn-sm btn-outline-warning" type="submit">Recalculate</button></form>
                                <form class="d-inline" method="post" action="<?= e(url('ratios/delete.php')) ?>" onsubmit="return confirm('Delete this ratio result?');"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><input type="hidden" name="financial_period_id" value="<?= e($row['financial_period_id']) ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
