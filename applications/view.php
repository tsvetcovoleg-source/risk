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
$collateralItems = [];
$collateralSummary = null;
$scoringResult = null;
$creditMemo = null;
$committeeDecision = null;
$committeeVotesCount = 0;

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

        $statement = $pdo->prepare('SELECT * FROM collateral WHERE application_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, id DESC');
        $statement->execute([$id]);
        $collateralItems = $statement->fetchAll();
        $collateralSummary = calculate_collateral_summary($collateralItems, $application['requested_amount'], $application['currency']);

        $statement = $pdo->prepare('SELECT * FROM scoring_results WHERE application_id = ? ORDER BY id DESC LIMIT 1');
        $statement->execute([$id]);
        $scoringResult = $statement->fetch() ?: null;

        $statement = $pdo->prepare('SELECT * FROM credit_memos WHERE application_id = ? LIMIT 1');
        $statement->execute([$id]);
        $creditMemo = $statement->fetch() ?: null;

        $statement = $pdo->prepare('SELECT cd.*, COUNT(cv.id) AS votes_count FROM committee_decisions cd LEFT JOIN committee_votes cv ON cv.committee_decision_id = cd.id WHERE cd.application_id = ? GROUP BY cd.id LIMIT 1');
        $statement->execute([$id]);
        $committeeDecision = $statement->fetch() ?: null;
        $committeeVotesCount = $committeeDecision ? (int) $committeeDecision['votes_count'] : 0;
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
            <div>
                <h2 class="h5 mb-1">Collateral</h2>
                <p class="text-secondary small mb-0">Manual registry of pledged assets proposed for this credit application.</p>
            </div>
            <a class="btn btn-primary" href="<?= e(url('collateral/create.php?application_id=' . $application['id'])) ?>">Add collateral</a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light"><tr><th>Collateral type</th><th>Description</th><th>Owner name</th><th>Estimated market value</th><th>Accepted collateral value</th><th>Currency</th><th>Valuation date</th><th>Pledge status</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    <?php if (!$collateralItems): ?><tr><td colspan="9" class="text-center text-secondary py-3">No collateral added yet.</td></tr><?php endif; ?>
                    <?php foreach ($collateralItems as $collateralItem): ?>
                        <tr>
                            <td><?= e(ucwords(str_replace('_', ' ', $collateralItem['collateral_type']))) ?></td>
                            <td><?= e($collateralItem['description']) ?></td>
                            <td><?= e($collateralItem['owner_name'] ?: 'N/A') ?></td>
                            <td><?= e(format_currency_amount($collateralItem['estimated_market_value'], $collateralItem['currency'])) ?></td>
                            <td><?= e(format_currency_amount($collateralItem['accepted_collateral_value'], $collateralItem['currency'])) ?></td>
                            <td><?= e($collateralItem['currency']) ?></td>
                            <td><?= e($collateralItem['valuation_date'] ? format_date($collateralItem['valuation_date']) : 'N/A') ?></td>
                            <td><?= e(ucwords(str_replace('_', ' ', $collateralItem['pledge_status']))) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="<?= e(url('collateral/view.php?id=' . $collateralItem['id'])) ?>">View</a>
                                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('collateral/edit.php?id=' . $collateralItem['id'])) ?>">Edit</a>
                                <form class="d-inline" method="post" action="<?= e(url('collateral/delete.php')) ?>" onsubmit="return confirm('Delete this collateral record?');">
                                    <input type="hidden" name="id" value="<?= e($collateralItem['id']) ?>">
                                    <input type="hidden" name="application_id" value="<?= e($application['id']) ?>">
                                    <input type="hidden" name="return_to" value="application">
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
        <h2 class="h5 mb-3">Collateral summary</h2>
        <?php if ($collateralSummary): ?>
            <?php foreach ($collateralSummary['warnings'] as $warning): ?><div class="alert alert-warning"><?= e($warning) ?></div><?php endforeach; ?>
            <div class="row g-3 mb-4">
                <div class="col-md-3"><div class="text-secondary small">Requested amount</div><div class="fw-semibold"><?= e(format_currency_amount($collateralSummary['requested_amount'], $collateralSummary['application_currency'])) ?></div></div>
                <div class="col-md-3"><div class="text-secondary small">Total estimated market value (application currency)</div><div class="fw-semibold"><?= e(format_currency_amount($collateralSummary['total_estimated_application_currency'], $collateralSummary['application_currency'])) ?></div></div>
                <div class="col-md-3"><div class="text-secondary small">Total accepted collateral value (application currency)</div><div class="fw-semibold"><?= e(format_currency_amount($collateralSummary['total_accepted_application_currency'], $collateralSummary['application_currency'])) ?></div></div>
                <div class="col-md-3"><div class="text-secondary small">Collateral coverage ratio</div><div class="fw-semibold"><?= e(format_percent($collateralSummary['collateral_coverage_ratio'])) ?></div><div class="small text-secondary"><?= e(interpret_collateral_coverage($collateralSummary['collateral_coverage_ratio'])) ?></div></div>
                <div class="col-md-3"><div class="text-secondary small">LTV</div><div class="fw-semibold"><?= e(format_percent($collateralSummary['ltv'])) ?></div><div class="small text-secondary"><?= e(interpret_ltv($collateralSummary['ltv'])) ?></div></div>
            </div>
            <h3 class="h6 mb-2">Informational totals by currency</h3>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle">
                    <thead class="table-light"><tr><th>Currency</th><th>Items</th><th>Total estimated market value</th><th>Total accepted collateral value</th></tr></thead>
                    <tbody>
                        <?php if (!$collateralSummary['totals_by_currency']): ?><tr><td colspan="4" class="text-center text-secondary">No collateral totals.</td></tr><?php endif; ?>
                        <?php foreach ($collateralSummary['totals_by_currency'] as $currencyTotal): ?>
                            <tr><td><?= e($currencyTotal['currency']) ?></td><td><?= e($currencyTotal['items_count']) ?></td><td><?= e(format_currency_amount($currencyTotal['estimated_market_value'], $currencyTotal['currency'])) ?></td><td><?= e(format_currency_amount($currencyTotal['accepted_collateral_value'], $currencyTotal['currency'])) ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <p class="text-secondary small mb-0">Currencies are not converted or mixed in one coverage coefficient.</p>
        <?php else: ?>
            <div class="alert alert-warning mb-0">Collateral summary is not available.</div>
        <?php endif; ?>
    </div>
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

<div class="row g-3">
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h3 class="h6 mb-1">Scoring</h3>
                        <p class="text-secondary small mb-0">SME scoring model result for this application.</p>
                    </div>
                    <a class="btn btn-sm btn-primary" href="<?= e(url('scoring/calculate.php?application_id=' . $application['id'])) ?>">Calculate scoring</a>
                </div>
                <?php if ($scoringResult): ?>
                    <div class="row g-2 mb-3">
                        <?php foreach (['Financial score' => $scoringResult['financial_score'], 'Non-financial score' => $scoringResult['non_financial_score'], 'Collateral score' => $scoringResult['collateral_score'], 'Final score' => $scoringResult['final_score']] as $label => $value): ?>
                            <div class="col-6"><div class="border rounded p-2 h-100"><div class="text-secondary small"><?= e($label) ?></div><div class="fw-semibold"><?= e(format_score($value)) ?></div></div></div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mb-3"><span class="text-secondary small">Risk level</span><br><span class="badge text-bg-<?= e(risk_level_badge_class($scoringResult['risk_level'])) ?>"><?= e($scoringResult['risk_level']) ?></span></div>
                    <div class="mb-3"><span class="text-secondary small">Expert override</span><div class="fw-semibold"><?= e($scoringResult['expert_override'] ? 'Yes' : 'No') ?></div></div>
                    <?php if ($scoringResult['expert_override']): ?><div class="alert alert-warning py-2">Risk level was manually overridden.</div><?php endif; ?>
                    <a class="btn btn-outline-primary" href="<?= e(url('scoring/view.php?application_id=' . $application['id'])) ?>">Open scoring</a>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">Scoring has not been calculated yet.</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-2">Credit memo</h3>
                <p class="text-secondary small mb-3">Central analytical memo prepared for this credit application.</p>
                <?php if ($creditMemo): ?>
                    <div class="mb-3">
                        <span class="text-secondary small">Recommended decision</span><br>
                        <span class="badge text-bg-<?= e(memo_decision_badge_class($creditMemo['recommended_decision'])) ?>"><?= e(memo_decision_label($creditMemo['recommended_decision'])) ?></span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><div class="border rounded p-2 h-100"><div class="text-secondary small">Prepared at</div><div class="fw-semibold"><?= e(format_date($creditMemo['prepared_at'], 'd.m.Y H:i')) ?></div></div></div>
                        <div class="col-6"><div class="border rounded p-2 h-100"><div class="text-secondary small">Updated at</div><div class="fw-semibold"><?= e(format_date($creditMemo['updated_at'], 'd.m.Y H:i')) ?></div></div></div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(url('memos/view.php?application_id=' . $application['id'])) ?>">Open memo</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('memos/edit.php?application_id=' . $application['id'])) ?>">Edit memo</a>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('memos/print.php?application_id=' . $application['id'])) ?>">Print memo</a>
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">Credit memo has not been created yet.</div>
                    <a class="btn btn-primary" href="<?= e(url('memos/create.php?application_id=' . $application['id'])) ?>">Create credit memo</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h3 class="h6 mb-2">Committee decision</h3>
                <p class="text-secondary small mb-3">Final committee decision and voting record for this application.</p>
                <?php if ($committeeDecision): ?>
                    <div class="mb-3"><span class="text-secondary small">Committee date</span><div class="fw-semibold"><?= e(format_date($committeeDecision['committee_date'])) ?></div></div>
                    <div class="mb-3"><span class="text-secondary small">Decision</span><br><span class="badge text-bg-<?= e(committee_decision_badge_class($committeeDecision['decision'])) ?>"><?= e(format_decision_label($committeeDecision['decision'])) ?></span></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><div class="border rounded p-2 h-100"><div class="text-secondary small">Approved amount</div><div class="fw-semibold"><?= e(format_amount($committeeDecision['approved_amount'], $committeeDecision['approved_currency'] ?: '')) ?></div></div></div>
                        <div class="col-6"><div class="border rounded p-2 h-100"><div class="text-secondary small">Approved term</div><div class="fw-semibold"><?= e($committeeDecision['approved_term_months'] ? $committeeDecision['approved_term_months'] . ' months' : '-') ?></div></div></div>
                        <div class="col-6"><div class="border rounded p-2 h-100"><div class="text-secondary small">Approved currency</div><div class="fw-semibold"><?= e($committeeDecision['approved_currency'] ?: '-') ?></div></div></div>
                        <div class="col-6"><div class="border rounded p-2 h-100"><div class="text-secondary small">Votes</div><div class="fw-semibold"><?= e($committeeVotesCount) ?></div></div></div>
                    </div>
                    <div class="d-flex gap-2 flex-wrap"><a class="btn btn-sm btn-outline-primary" href="<?= e(url('committee/view.php?application_id=' . $application['id'])) ?>">Open decision</a><a class="btn btn-sm btn-outline-secondary" href="<?= e(url('committee/edit.php?application_id=' . $application['id'])) ?>">Edit decision</a></div>
                <?php else: ?>
                    <div class="alert alert-warning mb-3">Committee decision has not been recorded yet.</div>
                    <a class="btn btn-primary" href="<?= e(url('committee/create.php?application_id=' . $application['id'])) ?>">Record committee decision</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
