<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'applications';
$pageTitle = 'Application card';
$id = get_int_param('id');
$application = null;
$client = null;
$comments = [];
$financialPeriods = [];
$ratioRows = [];

if ($id && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT ca.*, c.client_name, c.idno, c.legal_form, c.activity_sector, c.status AS client_status FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.id = ? AND ca.deleted_at IS NULL');
    $statement->execute([$id]);
    $application = $statement->fetch();

    if ($application) {
        $client = [
            'id' => $application['client_id'],
            'client_name' => $application['client_name'],
            'idno' => $application['idno'],
            'legal_form' => $application['legal_form'],
            'activity_sector' => $application['activity_sector'],
            'status' => $application['client_status'],
        ];

        $statement = $pdo->prepare('SELECT * FROM application_comments WHERE application_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, id DESC');
        $statement->execute([$id]);
        $comments = $statement->fetchAll();

        $statement = $pdo->prepare('SELECT * FROM financial_periods WHERE application_id = ? AND deleted_at IS NULL ORDER BY period_end_date ASC, id ASC');
        $statement->execute([$id]);
        $financialPeriods = $statement->fetchAll();

        $statement = $pdo->prepare('SELECT fr.* FROM financial_ratios fr INNER JOIN financial_periods fp ON fp.id = fr.financial_period_id WHERE fr.application_id = ? AND fp.deleted_at IS NULL ORDER BY fp.period_end_date ASC, fp.id ASC');
        $statement->execute([$id]);
        foreach ($statement->fetchAll() as $row) {
            $ratioRows[(int) $row['financial_period_id']] = $row;
        }
    }
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$application) {
    render_error_page('Application not found', 'The requested application does not exist or was archived.', url('applications/index.php'), 'Back to applications list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Application card</p>
            <h1 class="h2 mb-1"><?= e($application['application_number']) ?></h1>
            <p class="text-secondary mb-0"><?= e($application['client_name']) ?> · <?= e($application['status']) ?></p>
        </div>
        <div class="align-self-lg-center d-flex gap-2 flex-wrap">
            <a class="btn btn-primary" href="<?= e(url('applications/edit.php?id=' . $application['id'])) ?>">Edit application</a>
            <a class="btn btn-outline-secondary" href="<?= e(url('applications/index.php')) ?>">Back to applications list</a>
            <a class="btn btn-outline-primary" href="<?= e(url('clients/view.php?id=' . $application['client_id'])) ?>">Back to client card</a>
        </div>
    </div>
</section>

<div class="row g-4 mb-4">
    <div class="col-lg-8"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Main application data</h2><div class="row g-3"><?php foreach (['Application number' => $application['application_number'], 'Application date' => format_date($application['application_date']), 'Client name' => $application['client_name'], 'Requested amount' => format_amount($application['requested_amount'], $application['currency']), 'Currency' => $application['currency'], 'Requested term' => $application['requested_term_months'] . ' months', 'Credit product' => $application['credit_product'], 'Credit purpose' => $application['credit_purpose'], 'Repayment source' => $application['repayment_source'], 'Existing exposure amount' => format_amount($application['existing_exposure_amount'], $application['currency']), 'Proposed total exposure amount' => format_amount($application['proposed_total_exposure_amount'], $application['currency']), 'Status' => $application['status'], 'Priority' => $application['priority'], 'Notes' => $application['notes']] as $label => $value): ?><div class="col-md-6"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value ?: '-') ?></div></div><?php endforeach; ?></div></div></div></div>
    <div class="col-lg-4"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Client summary</h2><?php foreach (['Client name' => $client['client_name'], 'IDNO' => $client['idno'], 'Legal form' => $client['legal_form'], 'Activity sector' => $client['activity_sector'], 'Status' => $client['status']] as $label => $value): ?><div class="mb-3"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e($value ?: '-') ?></div></div><?php endforeach; ?><a class="btn btn-outline-primary" href="<?= e(url('clients/view.php?id=' . $client['id'])) ?>">Open client card</a></div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
            <h2 class="h5 mb-0">Financial statements</h2>
            <a class="btn btn-primary" href="<?= e(url('financials/create_period.php?application_id=' . $application['id'])) ?>">Add financial period</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Period label</th><th>Period type</th><th>Period end date</th><th>Is audited</th><th>Data source</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if (!$financialPeriods): ?><tr><td colspan="6" class="text-center text-secondary py-3">No financial statements added yet.</td></tr><?php endif; ?>
                    <?php foreach ($financialPeriods as $period): ?>
                        <tr>
                            <td><?= e($period['period_label']) ?></td>
                            <td><span class="badge text-bg-secondary"><?= e($period['period_type']) ?></span></td>
                            <td><?= e(format_date($period['period_end_date'])) ?></td>
                            <td><?= e($period['is_audited'] ? 'Yes' : 'No') ?></td>
                            <td><?= e($period['data_source']) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">View</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('financials/edit_period.php?id=' . $period['id'])) ?>">Edit period</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('financials/edit_balance.php?id=' . $period['id'])) ?>">Edit balance</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('financials/edit_income.php?id=' . $period['id'])) ?>">Edit income</a>
                                <form class="d-inline" method="post" action="<?= e(url('financials/delete_period.php')) ?>" onsubmit="return confirm('Delete this financial period?');">
                                    <input type="hidden" name="id" value="<?= e($period['id']) ?>">
                                    <input type="hidden" name="from_application" value="<?= e($application['id']) ?>">
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


<div class="card border-0 shadow-sm mb-4">
    <div class="card-body p-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
            <div><h2 class="h5 mb-1">Financial ratios summary</h2><p class="text-secondary small mb-0">Comparative ratio table across all financial periods for this application.</p></div>
            <form method="post" action="<?= e(url('ratios/calculate_application.php')) ?>"><input type="hidden" name="application_id" value="<?= e($application['id']) ?>"><button class="btn btn-primary" type="submit">Calculate ratios for all periods</button></form>
        </div>
        <?php if (!$ratioRows): ?>
            <div class="alert alert-warning mb-0">Financial ratios have not been calculated yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light"><tr><th>Ratio name</th><?php foreach ($financialPeriods as $period): ?><th class="text-end"><?= e($period['period_label']) ?><br><span class="fw-normal small"><?= e(format_date($period['period_end_date'])) ?></span></th><?php endforeach; ?></tr></thead>
                    <tbody>
                        <?php foreach (ratio_definitions() as $group): ?>
                            <tr class="table-secondary"><th colspan="<?= e(count($financialPeriods) + 1) ?>"><?= e($group['label']) ?></th></tr>
                            <?php foreach ($group['ratios'] as $key => $definition): ?>
                                <tr>
                                    <td><?= e($definition['label']) ?></td>
                                    <?php foreach ($financialPeriods as $period): ?>
                                        <?php $ratioRow = $ratioRows[(int) $period['id']] ?? null; ?>
                                        <td class="text-end"><?= e(format_ratio($ratioRow[$key] ?? null, $definition['percent'])) ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Application comments</h2><form method="post" action="<?= e(url('applications/comment_store.php')) ?>" class="mb-4"><input type="hidden" name="application_id" value="<?= e($application['id']) ?>"><label class="form-label" for="comment_text">Comment text</label><textarea class="form-control mb-2" id="comment_text" name="comment_text" rows="3" required></textarea><button class="btn btn-primary" type="submit">Add comment</button></form><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-light"><tr><th>Comment text</th><th>Created date</th><th class="text-end">Action</th></tr></thead><tbody><?php if (!$comments): ?><tr><td colspan="3" class="text-center text-secondary py-3">No comments.</td></tr><?php endif; ?><?php foreach ($comments as $comment): ?><tr><td><?= e($comment['comment_text']) ?></td><td><?= e(format_date($comment['created_at'], 'd.m.Y H:i')) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-danger" href="<?= e(url('applications/comment_delete.php?id=' . $comment['id'])) ?>" onclick="return confirm('Delete this comment?');">Delete</a></td></tr><?php endforeach; ?></tbody></table></div></div></div>

<div class="row g-3"><?php foreach (['Collateral', 'Scoring', 'Credit memo', 'Committee decision'] as $module): ?><div class="col-md-4"><div class="card module-placeholder border-0 shadow-sm h-100"><div class="card-body"><h3 class="h6 mb-2"><?= e($module) ?></h3><p class="text-secondary mb-0">This module will be implemented in the next development stages.</p></div></div></div><?php endforeach; ?></div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
