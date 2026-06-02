<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'clients';
$pageTitle = 'Edit client';
$statuses = ['active', 'inactive', 'watchlist', 'rejected'];
$errors = [];
$id = get_int_param('id');
$client = null;

if ($id !== null && $pdo instanceof PDO) {
    $statement = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND deleted_at IS NULL');
    $statement->execute([$id]);
    $client = $statement->fetch();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';

if (!$id || !$client) {
    render_error_page('Client not found', 'The requested client does not exist or was archived.', url('clients/index.php'), 'Back to clients list');
    require_once dirname(__DIR__) . '/footer.php';
    exit;
}
?>
<section class="page-heading mb-4">
    <p class="eyebrow mb-2">Client registry</p>
    <h1 class="h2 mb-0">Edit client</h1>
</section>

<div class="card border-0 shadow-sm">
    <div class="card-body p-4">
        <form method="post" action="<?= e(url('clients/update.php')) ?>" class="row g-3">
            <input type="hidden" name="id" value="<?= e($client['id']) ?>">
            <?php require __DIR__ . '/_form.php'; ?>
            <div class="col-12 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Update client</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('clients/view.php?id=' . $client['id'])) ?>">Back to client card</a>
            </div>
        </form>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
