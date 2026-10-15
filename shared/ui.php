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
    $logoLxLightFile = first_existing_brand_asset($brandingDir, ['logo-lx-light.*', 'logo-lx.*']);
    $logoLxDarkFile = first_existing_brand_asset($brandingDir, ['logo-lx-dark.*']);
    $logoSndLightFile = first_existing_brand_asset($brandingDir, ['logo-snd-light.*', 'logo-snd.*']);
    $logoSndDarkFile = first_existing_brand_asset($brandingDir, ['logo-snd-dark.*']);
    $faviconFile = first_existing_brand_asset($brandingDir, ['favicon.*']);
    $loginBackgroundLightFile = first_existing_brand_asset($brandingDir, ['login-bg-light.*', 'login-bg.*']);
    $loginBackgroundDarkFile = first_existing_brand_asset($brandingDir, ['login-bg-dark.*']);
    $logoLightPath = $logoLightFile ? '/uploads/branding/' . basename($logoLightFile) : '';
    $logoDarkPath = $logoDarkFile ? '/uploads/branding/' . basename($logoDarkFile) : '';
    $faviconPath = $faviconFile ? '/uploads/branding/' . basename($faviconFile) : '';
    $hasLightLogo = $logoLightFile !== null;
    $hasDarkLogo = $logoDarkFile !== null;
    $hasFavicon = $faviconFile !== null;
    $theme = ($_COOKIE['theme_preference'] ?? 'dark') === 'dark' ? 'dark' : 'light';
    $desktopMode = desktop_mode_enabled($user);
    $tablerLocalDistDir = __DIR__ . '/assets/Tabler/dist';
    $hasLocalTablerCss = file_exists($tablerLocalDistDir . '/css/tabler.min.css');
    $hasLocalTablerThemesCss = file_exists($tablerLocalDistDir . '/css/tabler-themes.min.css');
    $hasLocalTablerVendorsCss = file_exists($tablerLocalDistDir . '/css/tabler-vendors.min.css');
    $hasLocalTablerFlagsCss = file_exists($tablerLocalDistDir . '/css/tabler-flags.min.css');
    $hasLocalTablerSocialsCss = file_exists($tablerLocalDistDir . '/css/tabler-socials.min.css');
    $hasLocalTablerPaymentsCss = file_exists($tablerLocalDistDir . '/css/tabler-payments.min.css');
    $hasLocalTablerMarketingCss = file_exists($tablerLocalDistDir . '/css/tabler-marketing.min.css');
    $hasLocalTablerJs = file_exists($tablerLocalDistDir . '/js/tabler.min.js');
    $hasLocalTablerThemeJs = file_exists($tablerLocalDistDir . '/js/tabler-theme.min.js');
    $hasLocalSortableJs = file_exists($tablerLocalDistDir . '/libs/sortablejs/Sortable.min.js');
    $tablerCssHref = $hasLocalTablerCss ? '/shared/assets/Tabler/dist/css/tabler.min.css' : 'https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/css/tabler.min.css';
    $tablerThemesCssHref = $hasLocalTablerThemesCss ? '/shared/assets/Tabler/dist/css/tabler-themes.min.css' : '';
    $tablerVendorsCssHref = $hasLocalTablerVendorsCss ? '/shared/assets/Tabler/dist/css/tabler-vendors.min.css' : '';
    $tablerFlagsCssHref = $hasLocalTablerFlagsCss ? '/shared/assets/Tabler/dist/css/tabler-flags.min.css' : '';
    $tablerSocialsCssHref = $hasLocalTablerSocialsCss ? '/shared/assets/Tabler/dist/css/tabler-socials.min.css' : '';
    $tablerPaymentsCssHref = $hasLocalTablerPaymentsCss ? '/shared/assets/Tabler/dist/css/tabler-payments.min.css' : '';
    $tablerMarketingCssHref = $hasLocalTablerMarketingCss ? '/shared/assets/Tabler/dist/css/tabler-marketing.min.css' : '';
    $tablerJsSrc = $hasLocalTablerJs ? '/shared/assets/Tabler/dist/js/tabler.min.js' : 'https://cdn.jsdelivr.net/npm/@tabler/core@latest/dist/js/tabler.min.js';
    $tablerThemeJsSrc = $hasLocalTablerThemeJs ? '/shared/assets/Tabler/dist/js/tabler-theme.min.js' : '';
    $sortableJsSrc = $hasLocalSortableJs ? '/shared/assets/Tabler/dist/libs/sortablejs/Sortable.min.js' : 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js';
    $bodyRouteClass = 'route-' . trim(str_replace('/', '-', $path), '-');
    if ($bodyRouteClass === 'route-') {
        $bodyRouteClass = 'route-root';
    }
    $loginBackgroundLightPath = $path === '/auth/login' && $loginBackgroundLightFile ? '/uploads/branding/' . basename($loginBackgroundLightFile) : '';
    $loginBackgroundDarkPath = $path === '/auth/login' && $loginBackgroundDarkFile ? '/uploads/branding/' . basename($loginBackgroundDarkFile) : '';
    $isLxContext = path_starts_with($path, '/lx') || path_starts_with($path, '/dash/lx');
    $isSndContext = path_starts_with($path, '/snd') || path_starts_with($path, '/dash/sound');
    $isPlainAuthPage = path_starts_with($path, '/auth/') || $path === '/setup';
    $isLxAppContext = path_starts_with($path, '/lx/app') || path_starts_with($path, '/dash/lx');
    $isSndAppContext = path_starts_with($path, '/snd/app') || path_starts_with($path, '/dash/sound');
    $isShopAppContext = $isLxAppContext || $isSndAppContext;
    $selectedShopShowId = $isShopAppContext ? (int) ($_GET['show'] ?? 0) : 0;
    $selectedShopTab = $isShopAppContext ? (string) ($_GET['tab'] ?? 'info') : 'info';
    if (!in_array($selectedShopTab, ['info', 'initial', 'revisions', 'paperwork', 'exports', 'cables'], true)) {
        $selectedShopTab = 'info';
    }
    if ($selectedShopShowId > 0 && $user && $isShopAppContext) {
        if (user_has_permission($user, 'admin.access')) {
            $shopShowAccess = db()->prepare('SELECT id FROM shows WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $shopShowAccess->execute([$selectedShopShowId]);
        } else {
            if (show_user_access_table_exists()) {
                $shopShowAccess = db()->prepare('SELECT id
                    FROM shows s
                    WHERE s.id = ?
                      AND s.deleted_at IS NULL
                      AND (
                        s.owner_user_id = ?
                        OR EXISTS (
                            SELECT 1
                            FROM show_user_access sua
                            WHERE sua.show_id = s.id AND sua.user_id = ?
                        )
                      )
                    LIMIT 1');
                $shopShowAccess->execute([$selectedShopShowId, (int) $user['id'], (int) $user['id']]);
            } else {
                $shopShowAccess = db()->prepare('SELECT id FROM shows WHERE id = ? AND owner_user_id = ? AND deleted_at IS NULL LIMIT 1');
                $shopShowAccess->execute([$selectedShopShowId, (int) $user['id']]);
            }
        }
        if (!$shopShowAccess->fetchColumn()) {
            $selectedShopShowId = 0;
        }
    }
    $activeLogoLightPath = $logoLightPath;
    $activeLogoDarkPath = $logoDarkPath;
    if ($isLxContext) {
        $activeLogoLightPath = $logoLxLightFile ? '/uploads/branding/' . basename($logoLxLightFile) : $logoLightPath;
        $activeLogoDarkPath = $logoLxDarkFile ? '/uploads/branding/' . basename($logoLxDarkFile) : $logoDarkPath;
    } elseif ($isSndContext) {
        $activeLogoLightPath = $logoSndLightFile ? '/uploads/branding/' . basename($logoSndLightFile) : $logoLightPath;
        $activeLogoDarkPath = $logoSndDarkFile ? '/uploads/branding/' . basename($logoSndDarkFile) : $logoDarkPath;
    }
    $activeHasLightLogo = $activeLogoLightPath !== '';
    $activeHasDarkLogo = $activeLogoDarkPath !== '';
    $appContextLabel = $isLxContext ? 'Backline LX App' : ($isSndContext ? 'Backline SND App' : '');
    $brandAltText = $isLxContext ? 'Backline LX logo' : ($isSndContext ? 'Backline SND logo' : $appName . ' logo');
    ?>
<!doctype html>
<html lang="en" data-bs-theme="<?= e($theme) ?>" data-bs-theme-base="slate" data-bs-theme-primary="blue" data-has-dark-logo="<?= $activeHasDarkLogo ? '1' : '0' ?>" data-has-light-logo="<?= $activeHasLightLogo ? '1' : '0' ?>" data-desktop-mode="<?= $desktopMode ? '1' : '0' ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> · <?= e($appName) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/core@1.6.0/dist/css/tabler-payments.min.css" integrity="sha384-UAjeDaIjCHhwML5gnhuab2m5f1czCNpfi/x1H11P/lJtAtBNH2wTHzCp5q2xj6n5" crossorigin="anonymous" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght@400&display=swap" rel="stylesheet">
    <link href="<?= e($tablerCssHref) ?>" rel="stylesheet">
    <?php if ($tablerThemesCssHref !== ''): ?><link href="<?= e($tablerThemesCssHref) ?>" rel="stylesheet"><?php endif; ?>
    <?php if ($tablerVendorsCssHref !== ''): ?><link href="<?= e($tablerVendorsCssHref) ?>" rel="stylesheet"><?php endif; ?>
    <?php if ($tablerFlagsCssHref !== ''): ?><link href="<?= e($tablerFlagsCssHref) ?>" rel="stylesheet"><?php endif; ?>
    <?php if ($tablerSocialsCssHref !== ''): ?><link href="<?= e($tablerSocialsCssHref) ?>" rel="stylesheet"><?php endif; ?>
    <?php if ($tablerPaymentsCssHref !== ''): ?><link href="<?= e($tablerPaymentsCssHref) ?>" rel="stylesheet"><?php endif; ?>
    <?php if ($tablerMarketingCssHref !== ''): ?><link href="<?= e($tablerMarketingCssHref) ?>" rel="stylesheet"><?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">
    <?php if ($hasFavicon): ?><link rel="icon" href="<?= e($faviconPath) ?>"><?php endif; ?>
    <link href="/shared/assets/style.css" rel="stylesheet">
    <link href="/shared/assets/custom.css" rel="stylesheet">
    <?php if ($desktopMode): ?><link href="/shared/assets/desktop-mode.css" rel="stylesheet"><?php endif; ?>
</head>
<body class="<?= e($bodyRouteClass . ($desktopMode ? ' desktop-mode' : '')) ?>">
<?php if ($tablerThemeJsSrc !== ''): ?><script src="<?= e($tablerThemeJsSrc) ?>"></script><?php endif; ?>
<div id="global-preloader" class="preloader-backdrop">
    <div class="preloader-spinner" role="status" aria-label="Loading"></div>
    <div id="global-preloader-text" class="preloader-text">Loading…</div>
</div>
<?php if ($isPlainAuthPage): ?>
<div class="page page-center">
    <div class="container container-tight py-4">
        <?php foreach ($flash as $message): ?>
            <div class="alert alert-<?= e($message['type']) ?>" role="alert"><?= e($message['message']) ?></div>
        <?php endforeach; ?>
        <?php $body(); ?>
    </div>
</div>
<?php else: ?>
<div class="page">
    <header class="navbar navbar-expand-md d-print-none">
        <div class="container-xl">
            <h1 class="navbar-brand navbar-brand-autodark pe-0 pe-md-3">
                <a href="<?= $user ? '/dash/home' : '/' ?>" class="d-flex align-items-center">
                    <?php if ($activeHasLightLogo || $activeHasDarkLogo): ?>
                        <?php if ($activeHasLightLogo): ?><img src="<?= e($activeLogoLightPath) ?>" alt="<?= e($brandAltText) ?>" class="app-logo logo-light"><?php endif; ?>
                        <?php if ($activeHasDarkLogo): ?><img src="<?= e($activeLogoDarkPath) ?>" alt="<?= e($brandAltText) ?>" class="app-logo logo-dark"><?php endif; ?>
                    <?php else: ?>
                        <span class="app-wordmark"><?= e($isLxContext ? 'Backline LX' : ($isSndContext ? 'Backline SND' : $appName)) ?></span>
                    <?php endif; ?>
                </a>
            </h1>
            <?php if ($user): ?>
            <div class="navbar-nav flex-row order-md-last align-items-center gap-2">
                <?php if ($appContextLabel !== '' && !$isShopAppContext): ?>
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
                <form method="post" action="/auth/logout" class="m-0">
                    <?= csrf_input() ?>
                    <button class="btn btn-outline-secondary" type="submit">Logout</button>
                </form>
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
    <?php $showWorkspaceOwnTabs = ($isLxAppContext || $isSndAppContext) && $selectedShopShowId > 0; ?>
    <?php if (!$showWorkspaceOwnTabs): ?>
    <header class="navbar-expand-md">
        <div class="collapse navbar-collapse" id="navbar-menu">
            <div class="navbar">
                <div class="container-xl">
                    <ul class="navbar-nav">
                        <?php if ($isLxAppContext): ?>
                            <?php if ($selectedShopShowId > 0): ?>
                                <?php foreach (['info' => 'Show Information', 'initial' => 'Initial Order', 'revisions' => 'Revisions', 'paperwork' => 'Paperwork'] as $tabKey => $tabLabel): ?>
                                    <?php nav_item('/dash/lx?show=' . $selectedShopShowId . '&tab=' . $tabKey, $tabLabel, '/dash/lx?show=' . $selectedShopShowId . '&tab=' . $selectedShopTab, nav_icon_for_tab($tabKey, $tabLabel)); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php nav_item('/dash/lx', 'LX Home', $path); ?>
                                <?php nav_item('/dash/home', 'Back Home', $path); ?>
                            <?php endif; ?>
                        <?php elseif ($isSndAppContext): ?>
                            <?php if ($selectedShopShowId > 0): ?>
                                <?php foreach (['info' => 'Show Information', 'initial' => 'Initial Order', 'revisions' => 'Revisions', 'paperwork' => 'Paperwork'] as $tabKey => $tabLabel): ?>
                                    <?php nav_item('/dash/sound?show=' . $selectedShopShowId . '&tab=' . $tabKey, $tabLabel, '/dash/sound?show=' . $selectedShopShowId . '&tab=' . $selectedShopTab, nav_icon_for_tab($tabKey, $tabLabel)); ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php nav_item('/dash/sound', 'SND Home', $path); ?>
                                <?php nav_item('/dash/home', 'Back Home', $path); ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <?php nav_item('/dash/home', 'Dashboard', $path); ?>
                            <?php nav_item('/dash/shows', 'Shows', $path); ?>
                            <?php if (user_has_permission($user, 'admin.access')) nav_item('/admin/settings', 'Settings', $path); ?>
                            <?php if (user_has_permission($user, 'inventory.manage')) nav_item('/admin/inventory', 'Inventory', $path); ?>
                            <?php if (user_has_permission($user, 'categories.manage')) nav_item('/admin/categories', 'Categories', $path); ?>
                            <?php if (user_has_permission($user, 'users.manage')) nav_item('/admin/users', 'Users', $path); ?>
                            <?php nav_item('/resources', 'Resources', $path); ?>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
        </div>
    </header>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($user): ?>
    <div class="workspace-tabs-wrap d-print-none" id="workspace-tabs-wrap" style="display:none;">
        <div class="container-xl">
            <div class="workspace-tabs" id="workspace-tabs"></div>
        </div>
    </div>
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
<?php endif; ?>
<script src="<?= e($sortableJsSrc) ?>"></script>
<script src="<?= e($tablerJsSrc) ?>"></script>
<script src="/shared/assets/preloader.js"></script>
<script src="/shared/assets/password-strength.js"></script>
<?php if ($desktopMode): ?><script src="/shared/assets/desktop-mode.js"></script><?php endif; ?>
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
  const html = document.documentElement;
  const loginBgLightPath = <?= json_encode($loginBackgroundLightPath, JSON_UNESCAPED_SLASHES) ?>;
  const loginBgDarkPath = <?= json_encode($loginBackgroundDarkPath, JSON_UNESCAPED_SLASHES) ?>;
  if (!document.body.classList.contains('route-auth-login')) return;
  const applyBackground = () => {
    const isDark = html.getAttribute('data-bs-theme') === 'dark';
    const backgroundPath = isDark && loginBgDarkPath ? loginBgDarkPath : loginBgLightPath;
    if (backgroundPath) {
      document.body.style.backgroundImage = 'url("' + backgroundPath + '")';
      document.body.style.backgroundSize = 'cover';
      document.body.style.backgroundPosition = 'center';
      document.body.style.backgroundRepeat = 'no-repeat';
      document.body.style.backgroundAttachment = 'fixed';
    } else {
      document.body.style.backgroundImage = '';
      document.body.style.backgroundSize = '';
      document.body.style.backgroundPosition = '';
      document.body.style.backgroundRepeat = '';
      document.body.style.backgroundAttachment = '';
    }
  };
  applyBackground();
  const themeToggle = document.getElementById('theme-toggle');
  themeToggle?.addEventListener('click', () => {
    window.setTimeout(applyBackground, 0);
  });
})();

(() => {
  const pickTabIcon = (label) => {
    const text = (label || '').toLowerCase();
    if (text.includes('dash') || text.includes('home')) return 'layout-dashboard';
    if (text.includes('show')) return 'clipboard-list';
    if (text.includes('initial')) return 'file-plus';
    if (text.includes('revision')) return 'git-branch';
    if (text.includes('paperwork')) return 'file-description';
    if (text.includes('export')) return 'file-export';
    if (text.includes('cable')) return 'cable';
    if (text.includes('inventory')) return 'package';
    if (text.includes('categor')) return 'category';
    if (text.includes('user')) return 'users';
    if (text.includes('setting')) return 'settings';
    if (text.includes('resource')) return 'folder';
    if (text.includes('sound') || text.includes('snd')) return 'wave-sine';
    if (text.includes('light') || text.includes('lx')) return 'bulb';
    return 'chevron-right';
  };
  document.querySelectorAll('.nav.nav-tabs .nav-link').forEach((link) => {
    if (link.querySelector('.nav-tab-icon')) return;
    const labelEl = link.querySelector('.nav-link-title');
    const label = (labelEl?.textContent || link.textContent || '').trim();
    const icon = document.createElement('i');
    icon.className = 'ti ti-' + pickTabIcon(label) + ' nav-tab-icon';
    icon.setAttribute('aria-hidden', 'true');
    if (labelEl) {
      link.insertBefore(icon, labelEl);
    } else {
      link.prepend(icon);
      const textNode = document.createElement('span');
      textNode.className = 'nav-link-title';
      textNode.textContent = label;
      link.append(textNode);
      link.childNodes.forEach((node) => {
        if (node !== icon && node !== textNode) link.removeChild(node);
      });
    }
    link.classList.add('nav-tab-link');
  });
})();

(() => {
  const userIsSignedIn = <?= $user ? 'true' : 'false' ?>;
  if (!userIsSignedIn) return;
  const tabsWrap = document.getElementById('workspace-tabs-wrap');
  const tabsEl = document.getElementById('workspace-tabs');
  const bodyEl = document.body;
  if (!tabsWrap || !tabsEl) return;
  const workspaceUserId = <?= (int) ($user['id'] ?? 0) ?>;
  const workspaceSessionId = <?= json_encode((string) session_id(), JSON_UNESCAPED_SLASHES) ?>;
  const STORAGE_KEY = `backline.workspace_tabs.v2.u${workspaceUserId}.s${workspaceSessionId}`;
  const storage = window.sessionStorage;
  const currentPath = window.location.pathname + window.location.search;

  const readTabs = () => {
    try {
      const raw = storage.getItem(STORAGE_KEY);
      const parsed = raw ? JSON.parse(raw) : [];
      return Array.isArray(parsed) ? parsed.filter((tab) => tab && typeof tab.url === 'string' && typeof tab.label === 'string') : [];
    } catch (_) {
      return [];
    }
  };

  const writeTabs = (tabs) => {
    storage.setItem(STORAGE_KEY, JSON.stringify(tabs.slice(0, 12)));
  };

  const uniqueTabs = (tabs) => {
    const seen = new Set();
    return tabs.filter((tab) => {
      const key = tab.url.trim();
      if (!key || seen.has(key)) return false;
      seen.add(key);
      return true;
    });
  };

  const addWorkspaceTab = (url, label) => {
    const cleanUrl = (url || '').trim();
    const cleanLabel = (label || '').trim();
    if (!cleanUrl || !cleanLabel) return;
    const tabs = uniqueTabs([{ url: cleanUrl, label: cleanLabel }, ...readTabs()]);
    writeTabs(tabs);
    renderTabs();
  };

  const removeWorkspaceTab = (url) => {
    const next = readTabs().filter((tab) => tab.url !== url);
    writeTabs(next);
    renderTabs();
  };

  const renderTabs = () => {
    const tabs = readTabs();
    tabsEl.innerHTML = '';
    if (!tabs.length) {
      tabsWrap.classList.remove('workspace-tabs-wrap-active');
      bodyEl.classList.remove('workspace-tabs-active');
      tabsWrap.style.display = 'none';
      return;
    }
    const hasActiveTab = tabs.some((tab) => tab.url === currentPath);
    tabsWrap.classList.toggle('workspace-tabs-wrap-active', hasActiveTab);
    bodyEl.classList.toggle('workspace-tabs-active', hasActiveTab);
    tabsWrap.style.display = '';
    tabs.forEach((tab) => {
      const item = document.createElement('div');
      item.className = 'workspace-tab-item' + (tab.url === currentPath ? ' active' : '');

      const link = document.createElement('a');
      link.className = 'workspace-tab-link';
      link.href = tab.url;
      link.textContent = tab.label;
      item.appendChild(link);

      const closeButton = document.createElement('button');
      closeButton.className = 'workspace-tab-close';
      closeButton.type = 'button';
      closeButton.setAttribute('aria-label', 'Close workspace tab');
      closeButton.textContent = '×';
      closeButton.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const wasActive = tab.url === currentPath;
        removeWorkspaceTab(tab.url);
        if (wasActive) {
          window.location.href = '/dash/home';
        }
      });
      item.appendChild(closeButton);
      tabsEl.appendChild(item);
    });
  };

  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[data-workspace-open="1"]');
    if (!link) return;
    const shopType = ((link.getAttribute('data-shop-type') || '').toUpperCase() === 'SND') ? 'SND' : 'LX';
    const showName = (link.getAttribute('data-show-name') || 'Show').trim() || 'Show';
    addWorkspaceTab(link.getAttribute('href') || '', `${showName} - ${shopType}`);
  });

  const isWorkspaceRoute = window.location.pathname === '/dash/lx' || window.location.pathname === '/dash/sound' || window.location.pathname === '/lx/app' || window.location.pathname === '/snd/app';
  if (isWorkspaceRoute) {
    const type = (window.location.pathname.includes('sound') || window.location.pathname.includes('/snd')) ? 'SND' : 'LX';
    let showName = '';
    const banner = document.querySelector('.shop-workspace-banner .h1');
    if (banner) {
      const raw = (banner.textContent || '').trim();
      showName = raw.replace(/\s*-\s*(LX|SND)\s*$/i, '').trim();
    }
    if (!showName) {
      const params = new URLSearchParams(window.location.search);
      const showId = (params.get('show') || '').trim();
      showName = showId ? `Show ${showId}` : 'Show';
    }
    addWorkspaceTab(currentPath, `${showName} - ${type}`);
  } else {
    renderTabs();
  }
})();

(() => {
  const hexToRgb = (hex) => {
    const normalized = hex.replace('#', '');
    if (normalized.length !== 6) return null;
    const num = Number.parseInt(normalized, 16);
    if (Number.isNaN(num)) return null;
    return [(num >> 16) & 255, (num >> 8) & 255, num & 255].join(', ');
  };

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
    const rgb = hexToRgb(color);
    const iconName = isIconValid ? customIcon : pickIcon(titleText);
    card.style.setProperty('--card-accent-color', color);
    if (rgb) card.style.setProperty('--card-accent-rgb', rgb);
    card.classList.add('card-title-enhanced');
    title.classList.add('card-title-pill');
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

function nav_item(string $href, string $label, string $current, ?string $icon = null): void
{
    $active = $current === $href || path_starts_with($current, $href . '/');
    $iconName = trim((string) ($icon ?? nav_icon_for_tab($href, $label)));
    echo '<li class="nav-item"><a class="nav-link nav-tab-link ' . ($active ? 'active' : '') . '" href="' . e($href) . '">';
    if ($iconName !== '') {
        echo '<i class="ti ti-' . e($iconName) . ' nav-tab-icon" aria-hidden="true"></i>';
    }
    echo '<span class="nav-link-title">' . e($label) . '</span></a></li>';
}

function nav_icon_for_tab(string $key, string $label): string
{
    $normalized = strtolower(trim($key . ' ' . $label));
    if (str_contains($normalized, 'dash') || str_contains($normalized, 'home')) {
        return 'layout-dashboard';
    }
    if (str_contains($normalized, 'show information') || str_contains($normalized, 'show')) {
        return 'clipboard-list';
    }
    if (str_contains($normalized, 'initial')) {
        return 'file-plus';
    }
    if (str_contains($normalized, 'revision')) {
        return 'git-branch';
    }
    if (str_contains($normalized, 'paperwork')) {
        return 'file-description';
    }
    if (str_contains($normalized, 'setting')) {
        return 'settings';
    }
    if (str_contains($normalized, 'inventory')) {
        return 'package';
    }
    if (str_contains($normalized, 'categor')) {
        return 'category';
    }
    if (str_contains($normalized, 'user')) {
        return 'users';
    }
    if (str_contains($normalized, 'resource')) {
        return 'folder';
    }
    if (str_contains($normalized, 'lx')) {
        return 'bulb';
    }
    if (str_contains($normalized, 'snd') || str_contains($normalized, 'sound')) {
        return 'wave-sine';
    }
    return 'chevron-right';
}
