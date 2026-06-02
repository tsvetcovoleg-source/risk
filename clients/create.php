<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'clients';
$pageTitle = 'Create client';
$statuses = ['active', 'inactive', 'watchlist', 'rejected'];
$client = ['status' => 'active'];
$errors = [];

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Client registry</p>
    <h1 class="h2 mb-0">Create new client</h1>
</section>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('clients/store.php')) ?>" class="row g-3">
            <?php require __DIR__ . '/_form.php'; ?>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Save client</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('clients/index.php')) ?>">Back to clients list</a>
            </div>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
