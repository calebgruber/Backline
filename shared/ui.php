<?php

declare(strict_types=1);

/** @param callable():void $body */
function render_page(string $title, callable $body, ?array $user = null): void
{
    $path = route_path();
    $flash = flash_take();
    $appName = (string) app_setting('branding.app_name', config('app_name', 'Backline'));
    $brandingDir = __DIR__ . '/../uploads/branding';
    $logoLightFile = first_existing_brand_asset($brandingDir, ['logo-light.*', 'logo.*']);
    $logoDarkFile = first_existing_brand_asset($brandingDir, ['logo-dark.*']);
    $logoLxFile = first_existing_brand_asset($brandingDir, ['logo-lx.*']);
    $logoSndFile = first_existing_brand_asset($brandingDir, ['logo-snd.*']);
    $faviconFile = first_existing_brand_asset($brandingDir, ['favicon.*']);
    $loginBackgroundFile = first_existing_brand_asset($brandingDir, ['login-bg.*']);
    $logoLightPath = $logoLightFile ? '/uploads/branding/' . basename($logoLightFile) : '';
    $logoDarkPath = $logoDarkFile ? '/uploads/branding/' . basename($logoDarkFile) : '';
    $faviconPath = $faviconFile ? '/uploads/branding/' . basename($faviconFile) : '';
    $hasLightLogo = $logoLightFile !== null;
    $hasDarkLogo = $logoDarkFile !== null;
    $hasFavicon = $faviconFile !== null;
    $theme = ($_COOKIE['theme_preference'] ?? 'light') === 'dark' ? 'dark' : 'light';
    $bodyRouteClass = 'route-' . trim(str_replace('/', '-', $path), '-');
    if ($bodyRouteClass === 'route-') {
        $bodyRouteClass = 'route-root';
    }
    $loginBackgroundPath = $path === '/auth/login' && $loginBackgroundFile ? '/uploads/branding/' . basename($loginBackgroundFile) : '';
    $isLxContext = path_starts_with($path, '/lx');
    $isSndContext = path_starts_with($path, '/snd');
    $contextLogoFile = $isLxContext ? $logoLxFile : ($isSndContext ? $logoSndFile : null);
    $contextLogoPath = $contextLogoFile ? '/uploads/branding/' . basename($contextLogoFile) : '';
    $appContextLabel = $isLxContext ? 'Backline LX App' : ($isSndContext ? 'Backline SND App' : '');
    ?>
<!doctype html>
<html lang="en" data-bs-theme="<?= e($theme) ?>" data-has-dark-logo="<?= $hasDarkLogo ? '1' : '0' ?>" data-has-light-logo="<?= $hasLightLogo ? '1' : '0' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    <?php if ($hasFavicon): ?><link rel="icon" href="<?= e($faviconPath) ?>"><?php endif; ?>
    <link href="/shared/assets/style.css" rel="stylesheet">
    <link href="/shared/assets/custom.css" rel="stylesheet">
</head>
<body class="<?= e($bodyRouteClass) ?>">
<div id="global-preloader" class="preloader-backdrop">
    <div class="spinner-border text-primary" role="status"></div>
</div>
<div class="page">
    <header class="navbar navbar-expand-md d-print-none">
        <div class="container-xl">
            <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
                <a href="<?= $user ? '/dash/home' : '/' ?>" class="d-flex align-items-center">
                    <?php if ($contextLogoPath !== ''): ?>
                        <img src="<?= e($contextLogoPath) ?>" alt="logo" class="app-logo">
                    <?php elseif ($hasLightLogo || $hasDarkLogo): ?>
                        <?php if ($hasLightLogo): ?><img src="<?= e($logoLightPath) ?>" alt="logo" class="app-logo logo-light"><?php endif; ?>
                        <?php if ($hasDarkLogo): ?><img src="<?= e($logoDarkPath) ?>" alt="logo" class="app-logo logo-dark"><?php endif; ?>
                    <?php else: ?>
                        <span class="app-wordmark"><?= e($isLxContext ? 'Backline LX' : ($isSndContext ? 'Backline SND' : $appName)) ?></span>
                    <?php endif; ?>
                </a>
            </h1>
            <?php if ($user): ?>
            <div class="navbar-nav flex-row order-md-last align-items-center gap-2">
                <?php if ($appContextLabel !== ''): ?>
                <span class="badge bg-blue-lt app-context-badge"><?= e($appContextLabel) ?></span>
                <a href="/dash/home" class="btn btn-outline-secondary btn-sm"><i class="ti ti-arrow-left me-1"></i>Back to Dash</a>
                <?php endif; ?>
                <button type="button" class="btn btn-icon theme-toggle-btn" id="theme-toggle" aria-label="Toggle theme">
                    <i class="ti ti-sun"></i>
                </button>
                <div class="nav-item">
                    <a href="/profile" class="nav-link d-flex lh-1 text-reset p-0" aria-label="Open profile page">
                        <span class="avatar avatar-sm rounded-3" style="background-image:url(https://api.dicebear.com/9.x/thumbs/svg?seed=<?= urlencode((string) $user['email']) ?>)"></span>
                        <div class="d-none d-xl-block ps-2">
                            <div><?= e($user['name']) ?></div>
                            <div class="mt-1 small text-secondary"><?= e($user['email']) ?></div>
                        </div>
                    </a>
                </div>
                <a href="/auth/logout" class="btn btn-outline-secondary">Logout</a>
            </div>
            <?php else: ?>
            <div class="navbar-nav flex-row order-md-last align-items-center gap-2">
                <button type="button" class="btn btn-icon theme-toggle-btn" id="theme-toggle" aria-label="Toggle theme">
                    <i class="ti ti-sun"></i>
                </button>
                <a href="/auth/login" class="btn btn-primary">Sign in</a>
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
                        <?php nav_item('/dash/home', 'Dashboard', $path); ?>
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
<script>
(() => {
  const html = document.documentElement;
  const button = document.getElementById('theme-toggle');
  const update = () => {
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    if (button) button.innerHTML = isDark ? '<i class="ti ti-moon"></i>' : '<i class="ti ti-sun"></i>';
  };
  button?.addEventListener('click', () => {
    const nextTheme = html.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-bs-theme', nextTheme);
    document.cookie = 'theme_preference=' + nextTheme + '; path=/; max-age=31536000; SameSite=Lax';
    update();
  });
  update();
})();

(() => {
  const loginBgPath = <?= json_encode($loginBackgroundPath, JSON_UNESCAPED_SLASHES) ?>;
  if (document.body.classList.contains('route-auth-login') && typeof loginBgPath === 'string' && loginBgPath.trim() !== '') {
    document.body.style.backgroundImage = 'url("' + loginBgPath + '")';
    document.body.style.backgroundSize = 'cover';
    document.body.style.backgroundPosition = 'center';
    document.body.style.backgroundRepeat = 'no-repeat';
    document.body.style.backgroundAttachment = 'fixed';
  }
})();

(() => {
  const palette = ['#206bc4', '#2fb344', '#f76707', '#e03131', '#7950f2', '#0ca678', '#d63384', '#5f3dc4', '#15aabf', '#be4bdb'];
  const iconMap = [
    { match: /(inventory|item|stock|shop)/i, icon: 'inventory_2' },
    { match: /(category|categories|folder|resource)/i, icon: 'folder' },
    { match: /(user|users|profile|account)/i, icon: 'person' },
    { match: /(setting|config|branding)/i, icon: 'settings' },
    { match: /(show|dash|home|launch)/i, icon: 'dashboard' },
    { match: /(sound|snd)/i, icon: 'graphic_eq' },
    { match: /(light|lx)/i, icon: 'light_mode' },
    { match: /(migration|database)/i, icon: 'database' }
  ];
  const pickIcon = (title) => {
    const found = iconMap.find((row) => row.match.test(title));
    return found ? found.icon : 'widgets';
  };
  const hash = (text) => {
    let value = 0;
    for (let i = 0; i < text.length; i += 1) value = ((value << 5) - value) + text.charCodeAt(i);
    return Math.abs(value);
  };

  document.querySelectorAll('.card').forEach((card) => {
    const title = card.querySelector('.card-title');
    if (!title) return;
    const titleText = (title.textContent || '').trim();
    if (!titleText) return;
    const customColor = card.getAttribute('data-card-color') || '';
    const customIcon = card.getAttribute('data-card-icon') || '';
    const isColorValid = /^#[0-9a-fA-F]{6}$/.test(customColor);
    const isIconValid = /^[a-z0-9_]{1,48}$/i.test(customIcon);
    const color = isColorValid ? customColor : palette[hash(titleText) % palette.length];
    const iconName = isIconValid ? customIcon : pickIcon(titleText);
    card.style.setProperty('--card-accent-color', color);
    card.classList.add('card-title-enhanced');
    if (!title.querySelector('.card-title-icon')) {
      title.classList.add('d-flex', 'align-items-center', 'gap-2');
      const icon = document.createElement('span');
      icon.className = 'card-title-icon material-symbols-outlined';
      icon.textContent = iconName;
      title.prepend(icon);
    }
  });
})();
</script>
</body>
</html>
<?php
}

function first_existing_brand_asset(string $dir, array $patterns): ?string
{
    foreach ($patterns as $pattern) {
        $matches = glob($dir . '/' . $pattern) ?: [];
        if (!empty($matches)) {
            sort($matches);
            return $matches[0];
        }
    }
    return null;
}

function nav_item(string $href, string $label, string $current): void
{
    $active = $current === $href || path_starts_with($current, $href . '/');
    echo '<li class="nav-item"><a class="nav-link ' . ($active ? 'active' : '') . '" href="' . e($href) . '"><span class="nav-link-title">' . e($label) . '</span></a></li>';
}
