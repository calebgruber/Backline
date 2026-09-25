<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/shared/auth.php';

if (!current_user()) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

$roots = allowed_resource_roots((array) current_user());
$folder = (string) ($_GET['folder'] ?? '');
$name = (string) ($_GET['name'] ?? '');
$relativePath = trim((string) ($_GET['path'] ?? ''), '/');
$segments = $relativePath === '' ? [] : explode('/', $relativePath);
$segments = array_values(array_filter($segments, static fn (string $segment): bool => $segment !== '' && $segment !== '.' && $segment !== '..' && !str_contains($segment, '\\') && !str_contains($segment, '/')));
$relativePath = implode('/', $segments);

if (!in_array($folder, $roots, true) || $name === '' || str_contains($name, '/') || str_contains($name, '\\') || $name === '.' || $name === '..' || str_contains($name, '..')) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$root = realpath(dirname(__DIR__, 2) . '/uploads/resources/' . $folder);
$base = realpath(dirname(__DIR__, 2) . '/uploads/resources/' . $folder . ($relativePath !== '' ? '/' . $relativePath : ''));
$filePath = dirname(__DIR__, 2) . '/uploads/resources/' . $folder . ($relativePath !== '' ? '/' . $relativePath : '') . '/' . $name;
$file = realpath($filePath);
if ($root === false || $base === false || $file === false || !is_file($file) || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !str_starts_with($file, $root . DIRECTORY_SEPARATOR)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$detectedMime = mime_content_type($file) ?: 'application/octet-stream';
$mime = strtolower($detectedMime);
$extension = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));
$riskyMimeTypes = ['image/svg+xml', 'text/html', 'application/xhtml+xml'];
$riskyExtensions = ['svg', 'html', 'htm', 'xhtml'];
$isRisky = in_array($mime, $riskyMimeTypes, true) || in_array($extension, $riskyExtensions, true);
$inlineMimeAllowlist = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
$safeInline = !$isRisky && in_array($mime, $inlineMimeAllowlist, true);
$contentType = $safeInline ? $detectedMime : 'application/octet-stream';
$disposition = $safeInline ? 'inline' : 'attachment';
$downloadName = $isRisky ? 'resource-download.bin' : $name;
$safeFilename = str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $downloadName);
$encodedFilename = rawurlencode($downloadName);
header('Content-Type: ' . $contentType);
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; img-src 'self' data:; style-src 'none'; script-src 'none'");
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Disposition: ' . $disposition . '; filename="' . $safeFilename . '"; filename*=UTF-8\'\'' . $encodedFilename);
header('Content-Length: ' . (string) filesize($file));
readfile($file);
