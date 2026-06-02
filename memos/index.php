<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'memos';
$pageTitle = 'Credit memos';
$search = clean_input($_GET['search'] ?? '');
$decision = clean_input($_GET['recommended_decision'] ?? '');
$memos = [];

if ($decision !== '' && !is_valid_memo_decision($decision)) {
    $decision = '';
}

if ($pdo instanceof PDO) {
    $conditions = ['ca.deleted_at IS NULL'];
    $params = [];

    if ($search !== '') {
        $conditions[] = '(ca.application_number LIKE :search OR c.client_name LIKE :search OR c.idno LIKE :search OR ca.credit_purpose LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    if ($decision !== '') {
        $conditions[] = 'cm.recommended_decision = :decision';
        $params['decision'] = $decision;
    }

    $statement = $pdo->prepare(
        'SELECT cm.*, ca.application_number, ca.requested_amount, ca.currency, ca.status AS application_status,
                ca.credit_purpose, c.client_name, c.idno, c.id AS client_id
         FROM credit_memos cm
         INNER JOIN credit_applications ca ON ca.id = cm.application_id
         INNER JOIN clients c ON c.id = ca.client_id
         WHERE ' . implode(' AND ', $conditions) . '
         ORDER BY cm.updated_at DESC, cm.id DESC'
    );
    $statement->execute($params);
    $memos = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Credit analysis</p>
    <h1 class="h2 mb-3">Credit memos</h1>
    <p class="text-secondary mb-0">Structured analytical conclusions prepared for SME credit applications.</p>
</section>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <form class="row g-3 align-items-end" method="get" action="<?= e(url('memos/index.php')) ?>">
            <div class="col-md-7">
                <label class="form-label" for="search">Search</label>
                <input class="form-control" id="search" name="search" value="<?= e($search) ?>" placeholder="Application number, client name, IDNO, purpose">
            </div>
            <div class="col-md-3">
                <label class="form-label" for="recommended_decision">Recommended decision</label>
                <select class="form-select" id="recommended_decision" name="recommended_decision">
                    <option value="">All decisions</option>
                    <?php foreach (memo_decision_options() as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= selected_attr($decision, $value) ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit">Filter</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('memos/index.php')) ?>">Reset</a>
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
                        <th>IDNO</th>
                        <th class="text-end">Requested amount</th>
                        <th>Currency</th>
                        <th>Application status</th>
                        <th>Recommended decision</th>
                        <th>Prepared at</th>
                        <th>Updated at</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$memos): ?>
                        <tr><td colspan="10" class="text-center text-secondary py-4">No credit memos found.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($memos as $memo): ?>
                        <tr>
                            <td><?= e($memo['application_number']) ?></td>
                            <td><?= e($memo['client_name']) ?></td>
                            <td><?= e($memo['idno']) ?></td>
                            <td class="text-end"><?= e(format_amount($memo['requested_amount'], $memo['currency'])) ?></td>
                            <td><?= e($memo['currency']) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e($memo['application_status']) ?></span></td>
                            <td><span class="badge text-bg-<?= e(memo_decision_badge_class($memo['recommended_decision'])) ?>"><?= e(memo_decision_label($memo['recommended_decision'])) ?></span></td>
                            <td><?= e(format_date($memo['prepared_at'], 'd.m.Y H:i')) ?></td>
                            <td><?= e(format_date($memo['updated_at'], 'd.m.Y H:i')) ?></td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <a class="btn btn-outline-primary" href="<?= e(url('memos/view.php?application_id=' . $memo['application_id'])) ?>">View</a>
                                    <a class="btn btn-outline-secondary" href="<?= e(url('memos/edit.php?application_id=' . $memo['application_id'])) ?>">Edit</a>
                                    <a class="btn btn-outline-secondary" href="<?= e(url('memos/print.php?application_id=' . $memo['application_id'])) ?>">Print</a>
                                    <a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $memo['application_id'])) ?>">Application</a>
                                    <a class="btn btn-outline-secondary" href="<?= e(url('clients/view.php?id=' . $memo['client_id'])) ?>">Client</a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
