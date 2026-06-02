<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'scoring';
$pageTitle = 'Calculate scoring';
$applicationId = $_SERVER['REQUEST_METHOD'] === 'POST' ? post_int('application_id') : get_int_param('application_id');
$errors = [];

function fetch_scoring_context(PDO $pdo, int $applicationId): array
{
    $statement = $pdo->prepare('SELECT ca.*, c.client_name, c.idno FROM credit_applications ca INNER JOIN clients c ON c.id = ca.client_id WHERE ca.id = ? AND ca.deleted_at IS NULL');
    $statement->execute([$applicationId]);
    $application = $statement->fetch();
    if (!$application) return [null, null, null, null, [], null];

    $statement = $pdo->prepare('SELECT * FROM financial_periods WHERE application_id = ? AND deleted_at IS NULL ORDER BY period_end_date DESC, id DESC LIMIT 1');
    $statement->execute([$applicationId]);
    $period = $statement->fetch() ?: null;

    $ratios = null;
    $balance = null;
    if ($period) {
        $statement = $pdo->prepare('SELECT * FROM financial_ratios WHERE application_id = ? AND financial_period_id = ?');
        $statement->execute([$applicationId, $period['id']]);
        $ratios = $statement->fetch() ?: null;

        $statement = $pdo->prepare('SELECT * FROM financial_balance_sheet WHERE financial_period_id = ?');
        $statement->execute([$period['id']]);
        $balance = $statement->fetch() ?: null;
    }

    $statement = $pdo->prepare('SELECT * FROM collateral WHERE application_id = ? AND deleted_at IS NULL ORDER BY created_at DESC, id DESC');
    $statement->execute([$applicationId]);
    $collateralItems = $statement->fetchAll();
    $collateralSummary = calculate_collateral_summary($collateralItems, $application['requested_amount'], $application['currency']);

    return [$application, $period, $ratios, $balance, $collateralItems, $collateralSummary];
}

$factors = [];
foreach (non_financial_factor_definitions() as $key => $label) {
    $factors[$key] = $_SERVER['REQUEST_METHOD'] === 'POST' ? normalize_non_financial_factor($_POST[$key] ?? null) : null;
}
$comments = nullable_input($_POST['comments'] ?? null);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    if (!$applicationId) {
        $errors[] = 'Application parameter is missing or invalid.';
    } else {
        [$application, $period, $ratios, $balance, $collateralItems, $collateralSummary] = fetch_scoring_context($pdo, $applicationId);
        if (!$application) $errors[] = 'Application not found.';
        if (!$period) $errors[] = 'No financial period is available for this application.';
        if ($period && !$ratios) $errors[] = 'Financial ratios are not calculated for the latest period. Please calculate ratios before scoring.';
    }

    if (!$errors) {
        $financialScore = calculate_financial_score($ratios, $balance);
        $collateralScore = calculate_collateral_score($collateralSummary);
        $nonFinancialScore = calculate_non_financial_score($factors);
        $finalScore = calculate_final_score($financialScore['score'], $nonFinancialScore, $collateralScore['score']);
        $riskLevel = determine_risk_level($finalScore);

        try {
            $pdo->beginTransaction();
            $statement = $pdo->prepare('SELECT * FROM scoring_results WHERE application_id = ? ORDER BY id DESC LIMIT 1');
            $statement->execute([$applicationId]);
            $existing = $statement->fetch() ?: null;

            if ($existing) {
                $storedRiskLevel = $existing['expert_override'] ? $existing['risk_level'] : $riskLevel;
                $statement = $pdo->prepare('UPDATE scoring_results SET financial_score = ?, non_financial_score = ?, collateral_score = ?, final_score = ?, risk_level = ?, model_version = ? WHERE id = ?');
                $statement->execute([$financialScore['score'], $nonFinancialScore, $collateralScore['score'], $finalScore, $storedRiskLevel, 'SME_SIMPLE_V1', $existing['id']]);
                $scoringResultId = (int) $existing['id'];
                $statement = $pdo->prepare('DELETE FROM scoring_non_financial_factors WHERE scoring_result_id = ?');
                $statement->execute([$scoringResultId]);
                $oldValue = $existing;
            } else {
                $statement = $pdo->prepare('INSERT INTO scoring_results (application_id, financial_score, non_financial_score, collateral_score, final_score, risk_level, model_version, expert_override, override_reason) VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL)');
                $statement->execute([$applicationId, $financialScore['score'], $nonFinancialScore, $collateralScore['score'], $finalScore, $riskLevel, 'SME_SIMPLE_V1']);
                $scoringResultId = (int) $pdo->lastInsertId();
                $oldValue = null;
            }

            $statement = $pdo->prepare('INSERT INTO scoring_non_financial_factors (scoring_result_id, business_reputation, management_quality, market_position, industry_risk, transparency_quality, relationship_history, comments) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            $statement->execute([$scoringResultId, $factors['business_reputation'], $factors['management_quality'], $factors['market_position'], $factors['industry_risk'], $factors['transparency_quality'], $factors['relationship_history'], $comments]);

            log_action($pdo, 'calculate_scoring', 'scoring', $scoringResultId, $oldValue, [
                'application_id' => $applicationId,
                'financial_score' => $financialScore['score'],
                'non_financial_score' => $nonFinancialScore,
                'collateral_score' => $collateralScore['score'],
                'final_score' => $finalScore,
                'calculated_risk_level' => $riskLevel,
                'effective_risk_level' => $existing && $existing['expert_override'] ? $existing['risk_level'] : $riskLevel,
            ]);
            $pdo->commit();
            redirect(url('scoring/view.php?application_id=' . $applicationId));
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = APP_DEBUG ? $exception->getMessage() : 'Scoring could not be saved.';
        }
    }
}

$application = $period = $ratios = $balance = $collateralSummary = null;
$collateralItems = [];
if ($applicationId && $pdo instanceof PDO) {
    [$application, $period, $ratios, $balance, $collateralItems, $collateralSummary] = fetch_scoring_context($pdo, $applicationId);
}
$financialPreview = $ratios ? calculate_financial_score($ratios, $balance) : null;
$collateralPreview = $collateralSummary ? calculate_collateral_score($collateralSummary) : null;

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
if (!$application) {
    render_error_page('Application not found', 'The requested application does not exist or was archived.', url('applications/index.php'), 'Back to applications list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">SME scoring</p>
    <h1 class="h2 mb-1">Calculate scoring</h1>
    <p class="text-secondary mb-0"><?= e($application['application_number']) ?> · <?= e($application['client_name']) ?></p>
</section>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endforeach; ?>

<div class="row g-4 mb-4">
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Application information</h2>
        <?php foreach (['Application number' => $application['application_number'], 'Client name' => $application['client_name'], 'Requested amount' => format_amount($application['requested_amount'], $application['currency']), 'Currency' => $application['currency'], 'Application status' => $application['status']] as $label => $value): ?>
            <div class="mb-2"><span class="text-secondary small"><?= e($label) ?></span><div class="fw-semibold"><?= e($value ?: 'N/A') ?></div></div>
        <?php endforeach; ?>
    </div></div></div>
    <div class="col-lg-6"><div class="card border-0 shadow-sm h-100"><div class="card-body p-4"><h2 class="h5 mb-3">Collateral summary</h2>
        <div class="mb-2"><span class="text-secondary small">Accepted value in application currency</span><div class="fw-semibold"><?= e(format_amount($collateralSummary['total_accepted_application_currency'] ?? 0, $application['currency'])) ?></div></div>
        <div class="mb-2"><span class="text-secondary small">Coverage ratio</span><div class="fw-semibold"><?= e(format_ratio($collateralPreview['coverage_ratio'] ?? null, true)) ?></div></div>
        <div class="mb-2"><span class="text-secondary small">LTV</span><div class="fw-semibold"><?= e(format_ratio($collateralPreview['ltv'] ?? null, true)) ?></div></div>
        <?php foreach (($collateralPreview['warnings'] ?? []) as $warning): ?><div class="alert alert-warning py-2 mb-2"><?= e($warning) ?></div><?php endforeach; ?>
    </div></div></div>
</div>

<div class="card border-0 shadow-sm mb-4"><div class="card-body p-4"><h2 class="h5 mb-3">Financial ratios</h2>
    <?php if (!$period): ?>
        <div class="alert alert-warning mb-0">No financial period is available for this application.</div>
    <?php elseif (!$ratios): ?>
        <div class="alert alert-warning">Financial ratios are not calculated for the latest period. Please calculate ratios before scoring.</div>
        <a class="btn btn-outline-primary" href="<?= e(url('applications/view.php?id=' . $application['id'])) ?>">Open application</a>
        <a class="btn btn-outline-secondary" href="<?= e(url('financials/view_period.php?id=' . $period['id'])) ?>">Open latest financial period</a>
    <?php else: ?>
        <p class="text-secondary">Latest period: <strong><?= e($period['period_label']) ?></strong> (<?= e(format_date($period['period_end_date'])) ?>)</p>
        <div class="table-responsive"><table class="table table-sm align-middle"><thead class="table-light"><tr><th>Ratio</th><th class="text-end">Value</th><th class="text-end">Points</th><th>Comment</th></tr></thead><tbody>
        <?php foreach ($financialPreview['details'] as $detail): ?><tr><td><?= e($detail['label']) ?></td><td class="text-end"><?= e(format_ratio($detail['value'], $detail['percent'])) ?></td><td class="text-end"><?= e($detail['points']) ?></td><td><?= e($detail['comment']) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <?php foreach ($financialPreview['warnings'] as $warning): ?><div class="alert alert-warning py-2 mb-2"><?= e($warning) ?></div><?php endforeach; ?>
    <?php endif; ?>
</div></div>

<form method="post" class="card border-0 shadow-sm"><div class="card-body p-4">
    <input type="hidden" name="application_id" value="<?= e($application['id']) ?>">
    <h2 class="h5 mb-3">Non-financial factors</h2>
    <div class="row g-3">
        <?php foreach (non_financial_factor_definitions() as $key => $label): ?>
            <div class="col-md-4"><label class="form-label" for="<?= e($key) ?>"><?= e($label) ?></label><select class="form-select" id="<?= e($key) ?>" name="<?= e($key) ?>"><option value="">Not assessed</option><?php for ($i = 1; $i <= 5; $i++): ?><option value="<?= e($i) ?>" <?= selected_attr($factors[$key], $i) ?>><?= e($i . ' — ' . interpret_score_point($i)) ?></option><?php endfor; ?></select></div>
        <?php endforeach; ?>
        <div class="col-12"><label class="form-label" for="comments">Comments</label><textarea class="form-control" id="comments" name="comments" rows="3"><?= e($comments) ?></textarea></div>
    </div>
    <div class="mt-4 d-flex gap-2"><button class="btn btn-primary" type="submit" <?= (!$period || !$ratios) ? 'disabled' : '' ?>>Calculate scoring</button><a class="btn btn-outline-secondary" href="<?= e(url('applications/view.php?id=' . $application['id'])) ?>">Back to application</a></div>
</div></form>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
