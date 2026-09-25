<?php

declare(strict_types=1);

function app_config(): array
{
    if (!array_key_exists('__app_config_cache', $GLOBALS)) {
        $GLOBALS['__app_config_cache'] = null;
    }
    $config = $GLOBALS['__app_config_cache'];
    if ($config !== null) {
        return $config;
    }

    $app = require __DIR__ . '/../config/app.php';
    $localPath = __DIR__ . '/../config/local.php';
    $local = file_exists($localPath) ? require $localPath : [];
    $config = array_replace_recursive($app, $local);
    $GLOBALS['__app_config_cache'] = $config;
    return $config;
}

function config(string $key, mixed $default = null): mixed
{
    $value = app_config();
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function app_is_installed(): bool
{
    return (bool) config('installed', false);
}

function write_local_config(array $local): bool
{
    $path = __DIR__ . '/../config/local.php';
    $export = var_export($local, true);
    $content = "<?php

return " . $export . ";
";
    return (bool) file_put_contents($path, $content, LOCK_EX);
}

function app_setting(string $key, mixed $default = null): mixed
{
    if (!array_key_exists('__app_settings_cache', $GLOBALS)) {
        $GLOBALS['__app_settings_cache'] = null;
    }
    $cache = $GLOBALS['__app_settings_cache'];

    if (!app_is_installed()) {
        return $default;
    }

    if ($cache === null) {
        $cache = [];
        try {
            $rows = db()->query('SELECT key_name, value_json FROM app_settings')->fetchAll();
            foreach ($rows as $row) {
                $cache[(string) $row['key_name']] = json_decode((string) $row['value_json'], true);
            }
            $GLOBALS['__app_settings_cache'] = $cache;
        } catch (Throwable) {
            return $default;
        }
    }

    return array_key_exists($key, $cache) ? $cache[$key] : $default;
}

function app_setting_clear_cache(): void
{
    $GLOBALS['__app_settings_cache'] = null;
}

function app_config_clear_cache(): void
{
    $GLOBALS['__app_config_cache'] = null;
}
