<?php

declare(strict_types=1);

/** @param callable():void $body */
function render_page(string $title, callable $body, ?array $user = null): void
{
    $path = route_path();
    $flash = flash_take();
    $appName = config('app_name', 'Backline');
    $logoPath = '/uploads/branding/logo-light.png';
    $logoFile = __DIR__ . '/../uploads/branding/logo-light.png';
    $hasLogo = file_exists($logoFile);
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    <link href="/shared/assets/style.css" rel="stylesheet">
    <link href="/shared/assets/custom.css" rel="stylesheet">
</head>
<body>
<div id="global-preloader" class="preloader-backdrop">
    <div class="spinner-border text-primary" role="status"></div>
</div>
<div class="page">
    <header class="navbar navbar-expand-md d-print-none">
        <div class="container-xl">
            <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
                <a href="<?= $user ? '/admin/dash' : '/auth/login' ?>" class="d-flex align-items-center">
                    <?php if ($hasLogo): ?>
                        <img src="<?= e($logoPath) ?>" alt="logo" class="app-logo">
                    <?php else: ?>
                        <span class="app-wordmark"><?= e($appName) ?></span>
                    <?php endif; ?>
                </a>
            </h1>
            <?php if ($user): ?>
            <div class="navbar-nav flex-row order-md-last">
                <div class="nav-item dropdown">
                    <a href="#" class="nav-link d-flex lh-1 text-reset p-0" data-bs-toggle="dropdown" aria-label="Open user menu">
                        <span class="avatar avatar-sm rounded-3" style="background-image:url(https://api.dicebear.com/9.x/thumbs/svg?seed=<?= urlencode((string) $user['email']) ?>)"></span>
                        <div class="d-none d-xl-block ps-2">
                            <div><?= e($user['name']) ?></div>
                            <div class="mt-1 small text-secondary"><?= e($user['email']) ?></div>
                        </div>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end dropdown-menu-arrow">
                        <a href="/auth/logout" class="dropdown-item">Logout</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <?php if ($user): ?>
    <header class="navbar-expand-md">
        <div class="collapse navbar-collapse" id="navbar-menu">
            <div class="navbar">
                <div class="container-xl">
                    <ul class="navbar-nav">
                        <?php nav_item('/admin/dash', 'Dashboard', $path); ?>
                        <?php if (user_has_permission($user, 'admin.access')) nav_item('/admin/settings', 'Settings', $path); ?>
                        <?php if (user_has_permission($user, 'inventory.manage')) nav_item('/admin/inventory', 'Inventory', $path); ?>
                        <?php if (user_has_permission($user, 'categories.manage')) nav_item('/admin/categories', 'Categories', $path); ?>
                        <?php if (user_has_permission($user, 'users.manage')) nav_item('/admin/users', 'Users', $path); ?>
                        <?php nav_item('/lx', 'LX', $path); ?>
                        <?php nav_item('/snd', 'SND', $path); ?>
                        <?php nav_item('/resources', 'Resources', $path); ?>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>

    <div class="page-wrapper">
        <div class="page-body">
            <div class="container-xl">
                <?php foreach ($flash as $message): ?>
                    <div class="alert alert-<?= e($message['type']) ?>" role="alert"><?= e($message['message']) ?></div>
                <?php endforeach; ?>
                <?php $body(); ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
<script src="/shared/assets/preloader.js"></script>
</body>
</html>
<?php
}

function nav_item(string $href, string $label, string $current): void
{
    $active = $current === $href || path_starts_with($current, $href . '/');
    echo '<li class="nav-item"><a class="nav-link ' . ($active ? 'active' : '') . '" href="' . e($href) . '"><span class="nav-link-title">' . e($label) . '</span></a></li>';
}
