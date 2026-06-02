<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'reports';
$pageTitle = 'Reports';
$moduleDescription = 'Future dashboard and reporting area for portfolio monitoring, pipeline analysis, and management summaries.';

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Future module</p>
    <h1 class="h2 mb-3"><?= e($pageTitle) ?></h1>
    <p class="text-secondary mb-0"><?= e($moduleDescription) ?></p>
</section>

<div class="card module-placeholder border-0 shadow-sm">
    <div class="card-body p-4">
        <h2 class="h5 mb-3">Module placeholder</h2>
        <p class="mb-0 text-secondary">
            This section is part of the base architecture. Business functionality, database tables,
            forms, validation, and workflows will be implemented in the next development stage.
        </p>
    </div>
</div>
<?php
require_once dirname(__DIR__) . '/footer.php';