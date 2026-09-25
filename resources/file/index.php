<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';

if (!current_user()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$roots = ['Lighting', 'Sound', 'Backline Manuals'];
$folder = (string) ($_GET['folder'] ?? '');
$name = (string) ($_GET['name'] ?? '');
$relativePath = trim((string) ($_GET['path'] ?? ''), '/');
$segments = $relativePath === '' ? [] : explode('/', $relativePath);
$segments = array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..'));
$relativePath = implode('/', $segments);

if (!in_array($folder, $roots, true) || $name === '' || str_contains($name, '/')) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$base = realpath(dirname(__DIR__, 2) . '/uploads/resources/' . $folder);
$filePath = dirname(__DIR__, 2) . '/uploads/resources/' . $folder . ($relativePath !== '' ? '/' . $relativePath : '') . '/' . $name;
$file = realpath($filePath);
if ($base === false || $file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$mime = mime_content_type($file) ?: 'application/octet-stream';
$safeFilename = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], basename($file));
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . $safeFilename . '"');
readfile($file);
