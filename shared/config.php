<?php

declare(strict_types=1);

function storage_path(string $path = ''): string
{
    $base = dirname(__DIR__) . '/storage';
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function settings_file(): string
{
    return storage_path('system_settings.php');
}

function default_settings(): array
{
    return [
        'app_name' => 'Backline',
        'branding_logo' => '',
        'branding_logo_dark' => '',
        'branding_footer_made_in' => '',
        'db' => [
            'host' => '127.0.0.1',
            'port' => '3306',
            'name' => 'backline',
            'user' => 'root',
            'pass' => '',
            'charset' => 'utf8mb4',
        ],
    ];
}

function app_settings(): array
{
    $settings = default_settings();
    $file = settings_file();
    if (is_file($file)) {
        $loaded = require $file;
        if (is_array($loaded)) {
            $settings = array_replace_recursive($settings, $loaded);
        }
    }

    return $settings;
}

function save_settings(array $settings): bool
{
    if (!is_dir(storage_path())) {
        mkdir(storage_path(), 0775, true);
    }

    $export = '<?php' . PHP_EOL . 'return ' . var_export($settings, true) . ';' . PHP_EOL;
    return file_put_contents(settings_file(), $export, LOCK_EX) !== false;
}

function app_url(string $path = ''): string
{
    $base = defined('APP_BASE_PATH')
        ? (string) APP_BASE_PATH
        : rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    $base = $base === '' ? '' : $base;
    return $base . '/' . ltrim($path, '/');
}
