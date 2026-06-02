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
];

$nextSteps = [
    'Financial statements and ratio analysis',
    'Collateral records and valuation support',
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
