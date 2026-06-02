<?php
require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/functions.php';
require_once dirname(__DIR__) . '/db.php';

$currentSection = 'clients';
$pageTitle = 'Clients';

$search = clean_input($_GET['q'] ?? '');
$clients = [];

if ($pdo instanceof PDO) {
    $sql = 'SELECT id, client_name, idno, legal_form, activity_sector, status, created_at
            FROM clients
            WHERE deleted_at IS NULL';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (client_name LIKE :search OR idno LIKE :search OR activity_sector LIKE :search)';
        $params['search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY created_at DESC, id DESC';
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $clients = $statement->fetchAll();
}

require_once dirname(__DIR__) . '/header.php';
require_once dirname(__DIR__) . '/sidebar.php';
?>
<section class="page-heading mb-4">
    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
        <div>
            <p class="eyebrow mb-2">Client registry</p>
            <h1 class="h2 mb-0">Clients</h1>
        </div>
        <div class="align-self-lg-center">
            <a class="btn btn-primary" href="<?= e(url('clients/create.php')) ?>">Create new client</a>
        </div>
    </div>
</section>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-3" method="get" action="<?= e(url('clients/index.php')) ?>">
            <div class="col-md-10">
                <label class="form-label" for="q">Search</label>
                <input class="form-control" type="search" id="q" name="q" value="<?= e($search) ?>" placeholder="Client name, IDNO, activity sector">
            </div>
            <div class="col-md-2 d-flex align-items-end gap-2">
                <button class="btn btn-outline-primary w-100" type="submit">Search</button>
                <a class="btn btn-outline-secondary" href="<?= e(url('clients/index.php')) ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>Client name</th>
                    <th>IDNO</th>
                    <th>Legal form</th>
                    <th>Activity sector</th>
                    <th>Status</th>
                    <th>Created date</th>
                    <th class="text-end">Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php if (empty($clients)): ?>
                    <tr><td colspan="8" class="text-center text-secondary py-4">No clients found.</td></tr>
                <?php endif; ?>
                <?php foreach ($clients as $client): ?>
                    <tr>
                        <td><?= e($client['id']) ?></td>
                        <td><?= e($client['client_name']) ?></td>
                        <td><?= e($client['idno'] ?? '-') ?></td>
                        <td><?= e($client['legal_form'] ?? '-') ?></td>
                        <td><?= e($client['activity_sector'] ?? '-') ?></td>
                        <td><span class="badge text-bg-secondary"><?= e($client['status']) ?></span></td>
                        <td><?= e(format_date($client['created_at'])) ?></td>
                        <td class="text-end">
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(url('clients/view.php?id=' . $client['id'])) ?>">View</a>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('clients/edit.php?id=' . $client['id'])) ?>">Edit</a>
                            <a class="btn btn-sm btn-outline-danger" href="<?= e(url('clients/delete.php?id=' . $client['id'])) ?>" onclick="return confirm('Archive this client?');">Archive</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once dirname(__DIR__) . '/footer.php'; ?>
