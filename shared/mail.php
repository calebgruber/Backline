<?php

declare(strict_types=1);

function send_basic_mail(string $to, string $subject, string $body): bool
{
    $cleanTo = str_replace(["\r", "\n"], '', trim($to));
    if (!filter_var($cleanTo, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $cleanSubject = str_replace(["\r", "\n"], ' ', trim($subject));
    $fromName = str_replace(["\r", "\n"], '', (string) config('mail.from_name', 'Backline'));
    $fromEmail = str_replace(["\r", "\n"], '', (string) config('mail.from_email', 'noreply@example.com'));
    if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
        $fromEmail = 'noreply@example.com';
    }
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $fromName . ' <' . $fromEmail . '>',
    ];
    return mail($cleanTo, $cleanSubject, $body, implode("\r\n", $headers));
}

function mail_absolute_url(string $path): string
{
    $normalizedPath = '/' . ltrim($path, '/');
    $baseUrl = trim((string) config('base_url', ''));
    if ($baseUrl !== '') {
        return rtrim($baseUrl, '/') . $normalizedPath;
    }

    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return $normalizedPath;
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . $host . $normalizedPath;
}

function send_password_token_mail(string $to, string $tokenType, string $token): bool
{
    $tokenType = $tokenType === 'invite' ? 'invite' : 'reset';
    $actionText = $tokenType === 'invite' ? 'Set your password' : 'Reset your password';
    $subject = $tokenType === 'invite' ? 'Backline invite' : 'Backline password reset';
    $url = mail_absolute_url('/auth/reset?token=' . urlencode($token));

    $safeActionText = htmlspecialchars($actionText, ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $safeAppName = htmlspecialchars((string) config('app_name', 'Backline'), ENT_QUOTES, 'UTF-8');
    $body = '<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f6f8fb;font-family:Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;border:1px solid #e5e7eb;">
        <tr>
            <td style="padding:24px;">
                <h2 style="margin:0 0 12px;font-size:22px;color:#111827;">' . $safeAppName . '</h2>
                <p style="margin:0 0 18px;line-height:1.6;">Use the button below within 24 hours to continue.</p>
                <p style="margin:0 0 20px;">
                    <a href="' . $safeUrl . '" style="display:inline-block;background:#206bc4;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:600;">' . $safeActionText . '</a>
                </p>
                <p style="margin:0 0 8px;line-height:1.6;">If the button does not work, copy and paste this link into your browser:</p>
                <p style="margin:0;word-break:break-all;line-height:1.6;"><a href="' . $safeUrl . '">' . $safeUrl . '</a></p>
            </td>
        </tr>
    </table>
</body>
</html>';

    return send_basic_mail($to, $subject, $body);
}
