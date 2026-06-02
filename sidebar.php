<?php
$menuItems = [
    'dashboard' => ['label' => 'Dashboard', 'path' => 'index.php'],
    'clients' => ['label' => 'Clients', 'path' => 'clients/index.php'],
    'applications' => ['label' => 'Applications', 'path' => 'applications/index.php'],
    'committee' => ['label' => 'Committee', 'path' => 'committee/index.php'],
];
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <span class="brand-mark">SME</span>
        <span class="brand-title"><?= e(APP_NAME) ?></span>
    </div>
    <nav class="sidebar-nav" aria-label="Main navigation">
        <?php foreach ($menuItems as $section => $item): ?>
            <a class="nav-link <?= e(is_active_menu($section)) ?>" href="<?= e(url($item['path'])) ?>">
                <?= e($item['label']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
<main class="main-content">
    <div class="container-fluid py-4 py-lg-5">
        <?php if (!empty($dbConnectionError)): ?>
            <div class="alert alert-warning border-0 shadow-sm" role="alert">
                Database connection is not configured yet. Update <strong>config.php</strong> with real MySQL credentials before using database-backed modules.
            </div>
        <?php endif; ?>
