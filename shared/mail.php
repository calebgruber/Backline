<?php

declare(strict_types=1);

function send_basic_mail(string $to, string $subject, string $body): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . config('mail.from_name', 'Backline') . ' <' . config('mail.from_email', 'noreply@example.com') . '>',
    ];
    return mail($to, $subject, $body, implode("
", $headers));
}
