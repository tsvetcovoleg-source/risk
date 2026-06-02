<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

$currentSection = 'dashboard';
$pageTitle = 'Dashboard';

$metricValues = [
    'total_clients' => 0,
    'active_clients' => 0,
    'total_applications' => 0,
    'applications_in_analysis' => 0,
    'applications_sent_to_committee' => 0,
    'approved_applications' => 0,
    'rejected_applications' => 0,
    'total_financial_periods' => 0,
    'applications_with_financial_statements' => 0,
    'audited_financial_periods' => 0,
    'management_account_periods' => 0,
    'total_ratio_records' => 0,
    'applications_with_calculated_ratios' => 0,
    'periods_without_calculated_ratios' => 0,
    'total_collateral_items' => 0,
    'applications_with_collateral' => 0,
    'accepted_collateral_items' => 0,
    'registered_collateral_items' => 0,
    'applications_without_collateral' => 0,
];

if ($pdo instanceof PDO) {
    $queries = [
        'total_clients' => "SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL",
        'active_clients' => "SELECT COUNT(*) FROM clients WHERE deleted_at IS NULL AND status = 'active'",
        'total_applications' => "SELECT COUNT(*) FROM credit_applications WHERE deleted_at IS NULL",
        'applications_in_analysis' => "SELECT COUNT(*) FROM credit_applications WHERE deleted_at IS NULL AND status IN ('in_analysis', 'risk_review')",
        'applications_sent_to_committee' => "SELECT COUNT(*) FROM credit_applications WHERE deleted_at IS NULL AND status = 'committee_review'",
        'approved_applications' => "SELECT COUNT(*) FROM credit_applications WHERE deleted_at IS NULL AND status IN ('approved', 'approved_with_conditions', 'disbursed')",
        'rejected_applications' => "SELECT COUNT(*) FROM credit_applications WHERE deleted_at IS NULL AND status = 'rejected'",
        'total_financial_periods' => "SELECT COUNT(*) FROM financial_periods WHERE deleted_at IS NULL",
        'applications_with_financial_statements' => "SELECT COUNT(DISTINCT application_id) FROM financial_periods WHERE deleted_at IS NULL",
        'audited_financial_periods' => "SELECT COUNT(*) FROM financial_periods WHERE deleted_at IS NULL AND is_audited = 1",
        'management_account_periods' => "SELECT COUNT(*) FROM financial_periods WHERE deleted_at IS NULL AND period_type = 'management'",
        'total_ratio_records' => "SELECT COUNT(*) FROM financial_ratios fr INNER JOIN financial_periods fp ON fp.id = fr.financial_period_id WHERE fp.deleted_at IS NULL",
        'applications_with_calculated_ratios' => "SELECT COUNT(DISTINCT fr.application_id) FROM financial_ratios fr INNER JOIN financial_periods fp ON fp.id = fr.financial_period_id WHERE fp.deleted_at IS NULL",
        'periods_without_calculated_ratios' => "SELECT COUNT(*) FROM financial_periods fp LEFT JOIN financial_ratios fr ON fr.financial_period_id = fp.id WHERE fp.deleted_at IS NULL AND fr.id IS NULL",
        'total_collateral_items' => "SELECT COUNT(*) FROM collateral WHERE deleted_at IS NULL",
        'applications_with_collateral' => "SELECT COUNT(DISTINCT application_id) FROM collateral WHERE deleted_at IS NULL",
        'accepted_collateral_items' => "SELECT COUNT(*) FROM collateral WHERE deleted_at IS NULL AND pledge_status = 'accepted'",
        'registered_collateral_items' => "SELECT COUNT(*) FROM collateral WHERE deleted_at IS NULL AND pledge_status = 'registered'",
        'applications_without_collateral' => "SELECT COUNT(*) FROM credit_applications ca WHERE ca.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM collateral co WHERE co.application_id = ca.id AND co.deleted_at IS NULL)",
    ];

    foreach ($queries as $key => $query) {
        $statement = $pdo->prepare($query);
        $statement->execute();
        $metricValues[$key] = (int) $statement->fetchColumn();
    }
}

$metrics = [
    ['label' => 'Total clients', 'value' => $metricValues['total_clients'], 'tone' => 'primary'],
    ['label' => 'Active clients', 'value' => $metricValues['active_clients'], 'tone' => 'success'],
    ['label' => 'Total applications', 'value' => $metricValues['total_applications'], 'tone' => 'primary'],
    ['label' => 'Applications in analysis', 'value' => $metricValues['applications_in_analysis'], 'tone' => 'info'],
    ['label' => 'Applications sent to committee', 'value' => $metricValues['applications_sent_to_committee'], 'tone' => 'warning'],
    ['label' => 'Approved applications', 'value' => $metricValues['approved_applications'], 'tone' => 'success'],
    ['label' => 'Rejected applications', 'value' => $metricValues['rejected_applications'], 'tone' => 'danger'],
    ['label' => 'Total financial periods', 'value' => $metricValues['total_financial_periods'], 'tone' => 'primary'],
    ['label' => 'Applications with financial statements', 'value' => $metricValues['applications_with_financial_statements'], 'tone' => 'info'],
    ['label' => 'Audited financial periods', 'value' => $metricValues['audited_financial_periods'], 'tone' => 'success'],
    ['label' => 'Management account periods', 'value' => $metricValues['management_account_periods'], 'tone' => 'warning'],
    ['label' => 'Total ratio records', 'value' => $metricValues['total_ratio_records'], 'tone' => 'primary'],
    ['label' => 'Applications with calculated ratios', 'value' => $metricValues['applications_with_calculated_ratios'], 'tone' => 'success'],
    ['label' => 'Periods without calculated ratios', 'value' => $metricValues['periods_without_calculated_ratios'], 'tone' => 'warning'],
    ['label' => 'Total collateral items', 'value' => $metricValues['total_collateral_items'], 'tone' => 'primary'],
    ['label' => 'Applications with collateral', 'value' => $metricValues['applications_with_collateral'], 'tone' => 'success'],
    ['label' => 'Accepted collateral items', 'value' => $metricValues['accepted_collateral_items'], 'tone' => 'info'],
    ['label' => 'Registered collateral items', 'value' => $metricValues['registered_collateral_items'], 'tone' => 'success'],
    ['label' => 'Applications without collateral', 'value' => $metricValues['applications_without_collateral'], 'tone' => 'warning'],
];

$nextSteps = [
    'Refine financial ratio thresholds and prepare inputs for the future scoring model',
    'Enhance collateral valuation review and manual legal checklist',
    'SME scoring model and credit opinion templates',
    'Credit committee workflow, audit logs, dashboards, and reports',
];

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Internal banking platform</p>
    <h1 class="h2 mb-3"><?= e(APP_NAME) ?></h1>
    <p class="text-secondary mb-0">
        Dashboard with live MVP metrics for SME clients and credit applications in the Republic of Moldova.
    </p>
</section>

<div class="metric-grid mb-4">
    <?php foreach ($metrics as $metric): ?>
        <div class="metric-grid-item">
            <div class="card metric-card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <span class="metric-dot bg-<?= e($metric['tone']) ?>"></span>
                    <p class="text-secondary small mb-2"><?= e($metric['label']) ?></p>
                    <div class="display-6 fw-semibold text-dark"><?= e($metric['value']) ?></div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="card next-steps-card border-0 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Next development steps</h2>
        <div class="next-steps-grid">
            <?php foreach ($nextSteps as $step): ?>
                <div>
                    <div class="next-step-item">
                        <span class="check-marker">✓</span>
                        <span><?= e($step) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php
require_once __DIR__ . '/footer.php';
