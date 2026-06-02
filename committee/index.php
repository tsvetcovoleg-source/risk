<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'committee';
$pageTitle = 'Credit Committee';
$search = clean_input($_GET['search'] ?? '');
$decisionFilter = clean_input($_GET['decision'] ?? '');
$dateFilter = clean_input($_GET['committee_date'] ?? '');
$decisions = [];

if ($pdo instanceof PDO) {
    $where = ['ca.deleted_at IS NULL'];
    $params = [];
    if ($search !== '') {
        $where[] = '(ca.application_number LIKE :search OR c.client_name LIKE :search OR c.idno LIKE :search OR ca.credit_purpose LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }
    if (is_valid_committee_decision($decisionFilter)) {
        $where[] = 'cd.decision = :decision';
        $params['decision'] = $decisionFilter;
    }
    if ($dateFilter !== '' && is_valid_date($dateFilter)) {
        $where[] = 'cd.committee_date = :committee_date';
        $params['committee_date'] = $dateFilter;
    }

    $statement = $pdo->prepare(
        'SELECT cd.*, ca.application_number, ca.requested_amount, ca.currency AS requested_currency, ca.status AS application_status,
                ca.credit_purpose, c.client_name, c.idno, c.id AS client_id, COUNT(cv.id) AS votes_count,
                cm.id AS memo_id
         FROM committee_decisions cd
         INNER JOIN credit_applications ca ON ca.id = cd.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         LEFT JOIN committee_votes cv ON cv.committee_decision_id = cd.id
         LEFT JOIN credit_memos cm ON cm.application_id = ca.id
         WHERE ' . implode(' AND ', $where) . '
         GROUP BY cd.id, ca.application_number, ca.requested_amount, ca.currency, ca.status, ca.credit_purpose, c.client_name, c.idno, c.id, cm.id
         ORDER BY cd.committee_date DESC, cd.created_at DESC, cd.id DESC'
    );
    $statement->execute($params);
    $decisions = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Credit committee</p>
    <h1 class="h2 mb-3">Credit Committee</h1>
    <p class="text-secondary mb-0">Recorded committee decisions, approved terms, voting records, and related application links.</p>
</section>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4">
    <form class="row g-3" method="get">
        <div class="col-lg-5"><label class="form-label" for="search">Search</label><input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Application number, client, IDNO, purpose"></div>
        <div class="col-lg-3"><label class="form-label" for="decision">Decision</label><select class="form-select" id="decision" name="decision"><option value="">All decisions</option><?php foreach (committee_decision_options() as $value => $label): ?><option value="<?= e($value) ?>" <?= selected_attr($decisionFilter, $value) ?>><?= e($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-lg-2"><label class="form-label" for="committee_date">Committee date</label><input class="form-control" type="date" id="committee_date" name="committee_date" value="<?= e($dateFilter) ?>"></div>
        <div class="col-lg-2 d-flex align-items-end gap-2"><button class="btn btn-primary" type="submit">Filter</button><a class="btn btn-outline-secondary" href="<?= e(url('committee/index.php')) ?>">Reset</a></div>
    </form>
</div></div>

<div class="card border-0 shadow-sm"><div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Committee date</th><th>Application number</th><th>Client name</th><th>IDNO</th><th>Requested amount</th><th>Requested currency</th><th>Application status</th><th>Decision</th><th>Approved amount</th><th>Approved currency</th><th>Approved term</th><th>Votes</th><th>Created date</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
                <?php if (!$decisions): ?><tr><td colspan="14" class="text-center text-secondary py-4">No committee decisions found.</td></tr><?php endif; ?>
                <?php foreach ($decisions as $row): ?>
                    <tr>
                        <td><?= e(format_date($row['committee_date'])) ?></td>
                        <td><?= e($row['application_number']) ?></td>
                        <td><?= e($row['client_name']) ?></td>
                        <td><?= e($row['idno']) ?></td>
                        <td><?= e(format_amount($row['requested_amount'], $row['requested_currency'])) ?></td>
                        <td><?= e($row['requested_currency']) ?></td>
                        <td><span class="badge text-bg-secondary"><?= e($row['application_status']) ?></span></td>
                        <td><span class="badge text-bg-<?= e(committee_decision_badge_class($row['decision'])) ?>"><?= e(format_decision_label($row['decision'])) ?></span></td>
                        <td><?= e(format_amount($row['approved_amount'], $row['approved_currency'] ?: '')) ?></td>
                        <td><?= e($row['approved_currency'] ?: '-') ?></td>
                        <td><?= e($row['approved_term_months'] ? $row['approved_term_months'] . ' months' : '-') ?></td>
                        <td><?= e($row['votes_count']) ?></td>
                        <td><?= e(format_date($row['created_at'], 'd.m.Y H:i')) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('committee/view.php?application_id=' . $row['application_id'])) ?>">View</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('committee/edit.php?application_id=' . $row['application_id'])) ?>">Edit</a>
                            <form class="d-inline" method="post" action="<?= e(url('committee/delete.php')) ?>" onsubmit="return confirm('Delete this committee decision?');"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-sm btn-outline-danger" type="submit">Delete</button></form>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $row['application_id'])) ?>">Application</a>
                            <?php if ($row['memo_id']): ?><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('memos/view.php?application_id=' . $row['application_id'])) ?>">Memo</a><?php endif; ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('clients/view.php?id=' . $row['client_id'])) ?>">Client</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
