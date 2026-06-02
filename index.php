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
    'total_scoring_results' => 0,
    'low_risk_applications' => 0,
    'moderate_risk_applications' => 0,
    'medium_risk_applications' => 0,
    'high_risk_applications' => 0,
    'very_high_risk_applications' => 0,
    'expert_override_cases' => 0,
    'total_credit_memos' => 0,
    'applications_without_credit_memo' => 0,
    'memos_recommended_for_approval' => 0,
    'memos_recommended_for_approval_with_conditions' => 0,
    'memos_recommended_for_rejection' => 0,
    'memos_requiring_additional_information' => 0,
    'total_committee_decisions' => 0,
    'committee_approved_decisions' => 0,
    'committee_approved_with_conditions' => 0,
    'committee_rejected_decisions' => 0,
    'committee_postponed_decisions' => 0,
    'committee_returned_for_revision' => 0,
    'applications_without_committee_decision' => 0,
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
        'total_scoring_results' => "SELECT COUNT(*) FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id WHERE ca.deleted_at IS NULL",
        'low_risk_applications' => "SELECT COUNT(*) FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id WHERE ca.deleted_at IS NULL AND sr.risk_level = 'low'",
        'moderate_risk_applications' => "SELECT COUNT(*) FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id WHERE ca.deleted_at IS NULL AND sr.risk_level = 'moderate'",
        'medium_risk_applications' => "SELECT COUNT(*) FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id WHERE ca.deleted_at IS NULL AND sr.risk_level = 'medium'",
        'high_risk_applications' => "SELECT COUNT(*) FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id WHERE ca.deleted_at IS NULL AND sr.risk_level = 'high'",
        'very_high_risk_applications' => "SELECT COUNT(*) FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id WHERE ca.deleted_at IS NULL AND sr.risk_level = 'very_high'",
        'expert_override_cases' => "SELECT COUNT(*) FROM scoring_results sr INNER JOIN credit_applications ca ON ca.id = sr.application_id WHERE ca.deleted_at IS NULL AND sr.expert_override = 1",
        'total_credit_memos' => "SELECT COUNT(*) FROM credit_memos cm INNER JOIN credit_applications ca ON ca.id = cm.application_id WHERE ca.deleted_at IS NULL",
        'applications_without_credit_memo' => "SELECT COUNT(*) FROM credit_applications ca WHERE ca.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM credit_memos cm WHERE cm.application_id = ca.id)",
        'memos_recommended_for_approval' => "SELECT COUNT(*) FROM credit_memos cm INNER JOIN credit_applications ca ON ca.id = cm.application_id WHERE ca.deleted_at IS NULL AND cm.recommended_decision = 'approve'",
        'memos_recommended_for_approval_with_conditions' => "SELECT COUNT(*) FROM credit_memos cm INNER JOIN credit_applications ca ON ca.id = cm.application_id WHERE ca.deleted_at IS NULL AND cm.recommended_decision = 'approve_with_conditions'",
        'memos_recommended_for_rejection' => "SELECT COUNT(*) FROM credit_memos cm INNER JOIN credit_applications ca ON ca.id = cm.application_id WHERE ca.deleted_at IS NULL AND cm.recommended_decision = 'reject'",
        'memos_requiring_additional_information' => "SELECT COUNT(*) FROM credit_memos cm INNER JOIN credit_applications ca ON ca.id = cm.application_id WHERE ca.deleted_at IS NULL AND cm.recommended_decision = 'request_additional_information'",
        'total_committee_decisions' => "SELECT COUNT(*) FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id WHERE ca.deleted_at IS NULL",
        'committee_approved_decisions' => "SELECT COUNT(*) FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id WHERE ca.deleted_at IS NULL AND cd.decision = 'approved'",
        'committee_approved_with_conditions' => "SELECT COUNT(*) FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id WHERE ca.deleted_at IS NULL AND cd.decision = 'approved_with_conditions'",
        'committee_rejected_decisions' => "SELECT COUNT(*) FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id WHERE ca.deleted_at IS NULL AND cd.decision = 'rejected'",
        'committee_postponed_decisions' => "SELECT COUNT(*) FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id WHERE ca.deleted_at IS NULL AND cd.decision = 'postponed'",
        'committee_returned_for_revision' => "SELECT COUNT(*) FROM committee_decisions cd INNER JOIN credit_applications ca ON ca.id = cd.application_id WHERE ca.deleted_at IS NULL AND cd.decision = 'returned_for_revision'",
        'applications_without_committee_decision' => "SELECT COUNT(*) FROM credit_applications ca WHERE ca.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM committee_decisions cd WHERE cd.application_id = ca.id)",
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
    ['label' => 'Total scoring results', 'value' => $metricValues['total_scoring_results'], 'tone' => 'primary'],
    ['label' => 'Low risk applications', 'value' => $metricValues['low_risk_applications'], 'tone' => 'success'],
    ['label' => 'Moderate risk applications', 'value' => $metricValues['moderate_risk_applications'], 'tone' => 'info'],
    ['label' => 'Medium risk applications', 'value' => $metricValues['medium_risk_applications'], 'tone' => 'warning'],
    ['label' => 'High risk applications', 'value' => $metricValues['high_risk_applications'], 'tone' => 'danger'],
    ['label' => 'Very high risk applications', 'value' => $metricValues['very_high_risk_applications'], 'tone' => 'dark'],
    ['label' => 'Expert override cases', 'value' => $metricValues['expert_override_cases'], 'tone' => 'warning'],
    ['label' => 'Total credit memos', 'value' => $metricValues['total_credit_memos'], 'tone' => 'primary'],
    ['label' => 'Applications without credit memo', 'value' => $metricValues['applications_without_credit_memo'], 'tone' => 'warning'],
    ['label' => 'Memos recommended for approval', 'value' => $metricValues['memos_recommended_for_approval'], 'tone' => 'success'],
    ['label' => 'Memos recommended for approval with conditions', 'value' => $metricValues['memos_recommended_for_approval_with_conditions'], 'tone' => 'info'],
    ['label' => 'Memos recommended for rejection', 'value' => $metricValues['memos_recommended_for_rejection'], 'tone' => 'danger'],
    ['label' => 'Memos requiring additional information', 'value' => $metricValues['memos_requiring_additional_information'], 'tone' => 'secondary'],
    ['label' => 'Total committee decisions', 'value' => $metricValues['total_committee_decisions'], 'tone' => 'primary'],
    ['label' => 'Approved committee decisions', 'value' => $metricValues['committee_approved_decisions'], 'tone' => 'success'],
    ['label' => 'Approved with conditions', 'value' => $metricValues['committee_approved_with_conditions'], 'tone' => 'info'],
    ['label' => 'Rejected committee decisions', 'value' => $metricValues['committee_rejected_decisions'], 'tone' => 'danger'],
    ['label' => 'Postponed committee decisions', 'value' => $metricValues['committee_postponed_decisions'], 'tone' => 'warning'],
    ['label' => 'Returned for revision', 'value' => $metricValues['committee_returned_for_revision'], 'tone' => 'secondary'],
    ['label' => 'Applications without committee decision', 'value' => $metricValues['applications_without_committee_decision'], 'tone' => 'warning'],
];

$nextSteps = [
    'Refine financial ratio thresholds and prepare inputs for the future scoring model',
    'Enhance collateral valuation review and manual legal checklist',
    'Enhance credit memo conditions, document checklist, and analyst quality review',
    'Committee minutes printout, workflow controls, dashboards, and reports',
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
