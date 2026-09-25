<?php

declare(strict_types=1);

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrf_input(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify_or_fail(): void
{
    $token = (string) ($_POST['_csrf'] ?? '');
    if (!hash_equals((string) ($_SESSION['_csrf'] ?? ''), $token)) {
        http_response_code(419);
        exit('CSRF token mismatch.');
    }
}
