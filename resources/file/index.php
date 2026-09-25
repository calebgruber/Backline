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
if (!in_array($folder, $roots, true) || $name === '' || str_contains($name, '/')) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$base = realpath(dirname(__DIR__, 2) . '/uploads/resources/' . $folder);
$file = realpath(dirname(__DIR__, 2) . '/uploads/resources/' . $folder . '/' . $name);
if ($base === false || $file === false || !str_starts_with($file, $base . DIRECTORY_SEPARATOR) || !is_file($file)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$mime = mime_content_type($file) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($file) . '"');
readfile($file);
