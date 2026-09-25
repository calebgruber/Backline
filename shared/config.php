<?php

declare(strict_types=1);

function storage_path(string $path = ''): string
{
    $configured = trim((string) getenv('BACKLINE_STORAGE_PATH'));
    $base = $configured !== '' ? $configured : dirname(__DIR__) . '/storage';
    return $path === '' ? $base : $base . '/' . ltrim($path, '/');
}

function settings_file(): string
{
    return storage_path('system_settings.php');
}

function setup_state_file(): string
{
    return storage_path('setup_complete.php');
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
    if (!storage_path_is_safe()) {
        return false;
    }
    if (!is_dir(storage_path()) && !mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
        return false;
    }

    $export = '<?php' . PHP_EOL . 'return ' . var_export($settings, true) . ';' . PHP_EOL;
    return file_put_contents(settings_file(), $export, LOCK_EX) !== false;
}

function storage_path_is_safe(): bool
{
    $documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($documentRoot === '') {
        return true;
    }

    $docRootReal = realpath($documentRoot);
    $storageDir = storage_path();
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    $storageDirReal = realpath($storageDir);
    if ($docRootReal === false) {
        return true;
    }
    if ($storageDirReal === false) {
        return false;
    }
    $docRoot = rtrim($docRootReal, DIRECTORY_SEPARATOR);
    return !($storageDirReal === $docRoot || str_starts_with($storageDirReal, $docRoot . DIRECTORY_SEPARATOR));
}

function app_url(string $path = ''): string
{
    $base = defined('APP_BASE_PATH')
        ? (string) APP_BASE_PATH
        : rtrim(trim((string) getenv('BACKLINE_BASE_PATH')), '/');
    $base = ($base === '' || $base === '/') ? '' : $base;
    return $base . '/' . ltrim($path, '/');
}

function is_setup_complete(): bool
{
    $file = setup_state_file();
    if (!is_file($file)) {
        return false;
    }

    $state = require $file;
    return is_array($state) && !empty($state['completed']);
}

function mark_setup_complete(): bool
{
    if (!storage_path_is_safe()) {
        return false;
    }
    if (!is_dir(storage_path()) && !mkdir(storage_path(), 0775, true) && !is_dir(storage_path())) {
        return false;
    }

    $export = '<?php' . PHP_EOL . 'return ' . var_export([
        'completed' => true,
        'completed_at' => date(DATE_ATOM),
    ], true) . ';' . PHP_EOL;

    return file_put_contents(setup_state_file(), $export, LOCK_EX) !== false;
}
