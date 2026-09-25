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
