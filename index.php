<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/db.php';

$currentSection = 'dashboard';
$pageTitle = 'Dashboard';

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/sidebar.php';

$metrics = [
    ['label' => 'Active applications', 'value' => 0, 'tone' => 'primary'],
    ['label' => 'Applications in analysis', 'value' => 0, 'tone' => 'info'],
    ['label' => 'Applications sent to committee', 'value' => 0, 'tone' => 'warning'],
    ['label' => 'Approved applications', 'value' => 0, 'tone' => 'success'],
    ['label' => 'Rejected applications', 'value' => 0, 'tone' => 'danger'],
];

$nextSteps = [
    'Client and credit application registry',
    'Financial statements and ratio analysis',
    'Collateral records and valuation support',
    'SME scoring model and credit opinion templates',
    'Credit committee workflow, audit logs, dashboards, and reports',
];
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Internal banking platform</p>
    <h1 class="h2 mb-3"><?= e(APP_NAME) ?></h1>
    <p class="text-secondary mb-0">
        A foundation for supporting SME credit decision processes in the Republic of Moldova.
        Business modules will be added incrementally in later development stages.
    </p>
</section>

<div class="row g-3 g-xl-4 mb-4">
    <?php foreach ($metrics as $metric): ?>
        <div class="col-12 col-md-6 col-xl">
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

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Next development steps</h2>
        <div class="row g-3">
            <?php foreach ($nextSteps as $step): ?>
                <div class="col-12 col-lg-6">
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
