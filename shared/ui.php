<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

function nav_items(): array
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        return [];
    }

    $items = ['Resources' => 'resources'];
    if (($user['role'] ?? '') === 'admin') {
        $items = ['Admin' => 'admin/dash'] + $items;
    }
    if (in_array('lx', $user['concentrations'] ?? [], true)) {
        $items['Lighting'] = 'lx';
    }
    if (in_array('snd', $user['concentrations'] ?? [], true)) {
        $items['Sound'] = 'snd';
    }

    return $items;
}

function safe_logo_src(string $logo): string
{
    if ($logo === '') {
        return '';
    }
    if (str_starts_with($logo, '/')) {
        return $logo;
    }
    $scheme = parse_url($logo, PHP_URL_SCHEME);
    if (in_array(strtolower((string) $scheme), ['https'], true)) {
        return $logo;
    }

    return '';
}

function render_page(string $title, callable $content): void
{
    $settings = app_settings();
    $user = $_SESSION['user'] ?? null;
    $pathRaw = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $basePath = defined('APP_BASE_PATH') ? (string) APP_BASE_PATH : '';
    if ($basePath !== '' && $basePath !== '/' && str_starts_with($pathRaw, $basePath)) {
        $pathRaw = substr($pathRaw, strlen($basePath)) ?: '/';
    }
    $path = trim($pathRaw, '/');
    $path = $path === '' ? 'auth/login' : $path;
    $logo = safe_logo_src(trim((string) ($settings['branding_logo'] ?? '')));

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . htmlspecialchars($title) . ' · Backline</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>';
    echo '<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@500;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">';
    echo '<style>
    :root{--bg:#0b1020;--card:#121a32;--line:#2d3a66;--text:#eef2ff;--muted:#9fb0e8;--accent:#5aa8ff}
    *{box-sizing:border-box}body{margin:0;font:500 14px/1.45 "Montserrat","Inter","Segoe UI",Roboto,Helvetica,Arial,sans-serif;background:linear-gradient(180deg,#070b18,#0b1020);color:var(--text)}
    h1,h2,h3,.brand{font-family:"JetBrains Mono",ui-monospace,monospace}
    .preloader{position:fixed;inset:0;display:grid;place-items:center;background:#050914;z-index:9999;transition:.2s opacity}
    .spinner{width:52px;height:52px;border:4px solid #23305b;border-top-color:var(--accent);border-radius:50%;animation:spin .9s linear infinite}
    @keyframes spin{to{transform:rotate(360deg)}}
    header{display:flex;align-items:center;justify-content:space-between;padding:16px 24px;border-bottom:1px solid var(--line);background:#0a1228;position:sticky;top:0;z-index:1000}
    nav a{color:var(--muted);text-decoration:none;margin-right:14px}nav a.active,nav a:hover{color:var(--text)}
    a:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
    .brand{font-size:18px;font-weight:600}.brand img{height:34px;display:block}
    main{max-width:1100px;margin:22px auto;padding:0 18px 36px}
    .panel{background:var(--card);border:1px solid var(--line);border-radius:12px;padding:16px}
    input:not([type="checkbox"]):not([type="radio"]),select,textarea{width:100%;padding:9px 10px;border-radius:8px;border:1px solid var(--line);background:#0a1228;color:var(--text)}
    button{cursor:pointer;background:#173063;padding:9px 10px;border-radius:8px;border:1px solid var(--line);color:var(--text)}
    .btn{display:inline-block;width:auto;padding:9px 14px;border-radius:8px;border:1px solid var(--line);background:#173063;color:var(--text);text-decoration:none}
    .btn.secondary{background:#0f1f44}.btn.ghost{background:transparent}
    .grid{display:grid;gap:12px}.grid.two{grid-template-columns:repeat(2,minmax(0,1fr))}.grid.three{grid-template-columns:repeat(3,minmax(0,1fr))}
    table{width:100%;border-collapse:collapse}th,td{padding:8px;border-bottom:1px solid var(--line);text-align:left}
    .muted{color:var(--muted);font-size:12px}
    .badge{display:inline-block;padding:2px 8px;border-radius:999px;font-size:11px;border:1px solid var(--line);background:#13244d}
    .badge.success{background:#123d2a}.badge.warning{background:#4d3e12}.badge.danger{background:#4d1c1c}
    .alert{padding:10px 12px;border-radius:10px;border:1px solid var(--line);background:#0f1d3f}
    .alert.success{border-color:#2c7a57;background:#103225}.alert.warning{border-color:#927120;background:#3b310f}.alert.error{border-color:#8a2f2f;background:#3c1515}
    .tabs{display:flex;gap:8px;flex-wrap:wrap}.tabs a,.tabs span{padding:7px 10px;border-radius:8px;border:1px solid var(--line);text-decoration:none;color:var(--muted);display:inline-block}.tabs a.active,.tabs span.active{color:var(--text);background:#13244d}
    .kpi{padding:12px;border-radius:10px;border:1px solid var(--line);background:#0d1735}.kpi .value{font:600 22px/1 "JetBrains Mono",ui-monospace,monospace}
    .stack{display:flex;flex-wrap:wrap;gap:8px}.list-reset{list-style:none;padding:0;margin:0}
    .divider{height:1px;background:var(--line);margin:12px 0}
    @media(max-width:860px){.grid.two,.grid.three{grid-template-columns:1fr}}
    </style></head><body>';
    echo '<div id="preloader" class="preloader" aria-hidden="true"><div class="spinner" aria-hidden="true"></div></div>';
    echo '<header><div class="brand">';
    if ($logo !== '') {
        echo '<img src="' . htmlspecialchars($logo) . '" alt="' . htmlspecialchars((string) ($settings['app_name'] ?? 'Backline')) . '">';
    } else {
        echo htmlspecialchars((string) ($settings['app_name'] ?? 'Backline'));
    }

    echo '</div><nav aria-label="Primary">';
    foreach (nav_items() as $label => $href) {
        $isActive = ($path === $href || str_starts_with($path, $href . '/'));
        $active = $isActive ? 'active' : '';
        $ariaCurrent = $isActive ? ' aria-current="page"' : '';
        echo '<a class="' . $active . '"' . $ariaCurrent . ' href="' . htmlspecialchars(app_url($href)) . '">' . htmlspecialchars($label) . '</a>';
    }
    if ($user) {
        $destination = user_home_route($user);
        $isAccountActive = ($path === $destination || str_starts_with($path, $destination . '/'));
        $accountClass = $isAccountActive ? 'active' : '';
        $accountAria = $isAccountActive ? ' aria-current="page"' : '';
        echo '<a class="' . $accountClass . '"' . $accountAria . ' href="' . htmlspecialchars(app_url($destination)) . '">' . htmlspecialchars((string) ($user['email'] ?? 'Account')) . '</a>';
    } else {
        echo '<a href="' . htmlspecialchars(app_url('auth/login')) . '">Login</a>';
    }
    echo '</nav></header><main>';
    $content();
    echo '</main><script>window.addEventListener("load",()=>{const p=document.getElementById("preloader");if(p){p.style.opacity="0";setTimeout(()=>p.remove(),220);}});</script></body></html>';
}
