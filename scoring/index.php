<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'scoring';
$pageTitle = 'Scoring results';
$search = clean_input($_GET['q'] ?? '');
$riskLevel = clean_input($_GET['risk_level'] ?? '');
$allowedRiskLevels = ['low', 'moderate', 'medium', 'high', 'very_high'];
if (!in_array($riskLevel, $allowedRiskLevels, true)) {
    $riskLevel = '';
}
$rows = [];

if ($pdo instanceof PDO) {
    $where = ['ca.deleted_at IS NULL'];
    $params = [];
    if ($search !== '') {
        $where[] = '(ca.application_number LIKE :search OR c.client_name LIKE :search OR c.idno LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    if ($riskLevel !== '') {
        $where[] = 'sr.risk_level = :risk_level';
        $params['risk_level'] = $riskLevel;
    }
    $statement = $pdo->prepare(
        'SELECT sr.*, ca.application_number, ca.requested_amount, ca.currency, ca.status AS application_status,
                c.id AS client_id, c.client_name, c.idno
         FROM scoring_results sr
         INNER JOIN credit_applications ca ON ca.id = sr.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY sr.created_at DESC, sr.id DESC'
    );
    $statement->execute($params);
    $rows = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">SME risk model</p>
            <h1 class="h2 mb-1">Scoring results</h1>
            <p class="text-secondary mb-0">Transparent weighted scoring results used as credit analysis support.</p>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form class="row g-3" method="get">
            <div class="col-md-6">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" id="q" name="q" value="<?= e($search) ?>" placeholder="Application number, client name, IDNO">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="risk_level">Risk level</label>
                <select class="form-select" id="risk_level" name="risk_level">
                    <option value="">All risk levels</option>
                    <?php foreach ($allowedRiskLevels as $level): ?>
                        <option value="<?= e($level) ?>" <?= selected_attr($riskLevel, $level) ?>><?= e($level) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button class="btn btn-primary" type="submit">Apply filters</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('scoring/index.php')) ?>">Reset</a>
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
                        <th>Application number</th><th>Client name</th><th>Requested amount</th><th>Currency</th><th>Application status</th>
                        <th class="text-end">Financial</th><th class="text-end">Non-financial</th><th class="text-end">Collateral</th><th class="text-end">Final</th>
                        <th>Risk level</th><th>Expert override</th><th>Created date</th><th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?><tr><td colspan="13" class="text-center text-secondary py-4">No scoring results found.</td></tr><?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?= e($row['application_number']) ?></td>
                            <td><?= e($row['client_name']) ?><br><span class="text-secondary small">IDNO: <?= e($row['idno'] ?: 'N/A') ?></span></td>
                            <td class="text-nowrap"><?= e(format_amount($row['requested_amount'], $row['currency'])) ?></td>
                            <td><?= e($row['currency']) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e($row['application_status']) ?></span></td>
                            <td class="text-end"><?= e(format_score($row['financial_score'])) ?></td>
                            <td class="text-end"><?= e(format_score($row['non_financial_score'])) ?></td>
                            <td class="text-end"><?= e(format_score($row['collateral_score'])) ?></td>
                            <td class="text-end fw-semibold"><?= e(format_score($row['final_score'])) ?></td>
                            <td><span class="badge text-bg-<?= e(risk_level_badge_class($row['risk_level'])) ?>"><?= e($row['risk_level']) ?></span></td>
                            <td><?= e($row['expert_override'] ? 'Yes' : 'No') ?></td>
                            <td><?= e(format_date($row['created_at'], 'd.m.Y H:i')) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('scoring/view.php?application_id=' . $row['application_id'])) ?>">View scoring</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('scoring/calculate.php?application_id=' . $row['application_id'])) ?>">Recalculate</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $row['application_id'])) ?>">Open application</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('clients/view.php?id=' . $row['client_id'])) ?>">Open client</a>
                                <a class="btn btn-sm btn-outline-danger" href="<?= e(url('scoring/delete.php?id=' . $row['id'] . '&application_id=' . $row['application_id'])) ?>" onclick="return confirm('Delete this scoring result?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
