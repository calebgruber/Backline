<?php

declare(strict_types=1);

function passkeys_table_exists(): bool
{
    static $exists = null;
    if ($exists !== null) {
        return $exists;
    }
    try {
        $stmt = db()->query("SHOW TABLES LIKE 'user_passkeys'");
        $exists = (bool) $stmt->fetchColumn();
    } catch (Throwable) {
        $exists = false;
    }
    return $exists;
}

function passkey_base64url_encode(string $raw): string
{
    return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
}

function passkey_base64url_decode(string $b64url): ?string
{
    $value = trim($b64url);
    if ($value === '' || preg_match('/[^A-Za-z0-9\-_]/', $value)) {
        return null;
    }
    $pad = strlen($value) % 4;
    if ($pad > 0) {
        $value .= str_repeat('=', 4 - $pad);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    return $decoded === false ? null : $decoded;
}

function passkey_origin(): string
{
    $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    $isHttps = ($forwardedProto === 'https')
        || (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;
    $scheme = $isHttps ? 'https' : 'http';
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    return $host !== '' ? ($scheme . '://' . $host) : '';
}

function passkey_rp_id(): string
{
    $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }
    $rpId = parse_url('http://' . $host, PHP_URL_HOST);
    return is_string($rpId) ? strtolower($rpId) : '';
}

function passkey_begin_challenge(string $sessionKey, int $userId): array
{
    $challenge = passkey_base64url_encode(random_bytes(32));
    $_SESSION[$sessionKey] = [
        'challenge' => $challenge,
        'user_id' => $userId,
        'issued_at' => time(),
    ];
    return $_SESSION[$sessionKey];
}

function passkey_take_challenge(string $sessionKey): ?array
{
    $value = $_SESSION[$sessionKey] ?? null;
    unset($_SESSION[$sessionKey]);
    if (!is_array($value)) {
        return null;
    }
    $challenge = (string) ($value['challenge'] ?? '');
    $userId = (int) ($value['user_id'] ?? 0);
    $issuedAt = (int) ($value['issued_at'] ?? 0);
    if ($challenge === '' || $userId <= 0 || $issuedAt <= 0 || (time() - $issuedAt) > 300) {
        return null;
    }
    return ['challenge' => $challenge, 'user_id' => $userId, 'issued_at' => $issuedAt];
}

function passkey_parse_client_data(string $clientDataJsonB64): ?array
{
    $raw = passkey_base64url_decode($clientDataJsonB64);
    if ($raw === null) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return null;
    }
    return ['raw' => $raw, 'json' => $decoded];
}

function passkey_parse_authenticator_data(string $authenticatorDataB64): ?array
{
    $raw = passkey_base64url_decode($authenticatorDataB64);
    if ($raw === null || strlen($raw) < 37) {
        return null;
    }
    $flags = ord($raw[32]);
    $signCount = unpack('N', substr($raw, 33, 4))[1] ?? 0;
    $result = [
        'raw' => $raw,
        'rp_id_hash' => substr($raw, 0, 32),
        'flags' => $flags,
        'sign_count' => (int) $signCount,
        'credential_id' => null,
    ];
    $hasAttestedCredentialData = ($flags & 0x40) === 0x40;
    if ($hasAttestedCredentialData) {
        if (strlen($raw) < 55) {
            return null;
        }
        $offset = 37 + 16;
        $credentialLengthBytes = substr($raw, $offset, 2);
        if (strlen($credentialLengthBytes) !== 2) {
            return null;
        }
        $credentialIdLength = unpack('n', $credentialLengthBytes)[1] ?? 0;
        $offset += 2;
        $credentialId = substr($raw, $offset, (int) $credentialIdLength);
        if (strlen($credentialId) !== (int) $credentialIdLength) {
            return null;
        }
        $result['credential_id'] = $credentialId;
    }
    return $result;
}

function passkey_spki_pem_from_b64url(string $publicKeyB64): ?string
{
    $spki = passkey_base64url_decode($publicKeyB64);
    if ($spki === null || $spki === '') {
        return null;
    }
    $pem = "-----BEGIN PUBLIC KEY-----\n";
    $pem .= chunk_split(base64_encode($spki), 64, "\n");
    $pem .= "-----END PUBLIC KEY-----\n";
    return $pem;
}

function passkey_verify_signature(string $publicKeyPem, string $authenticatorDataRaw, string $clientDataJsonRaw, string $signatureRaw): bool
{
    $data = $authenticatorDataRaw . hash('sha256', $clientDataJsonRaw, true);
    return openssl_verify($data, $signatureRaw, $publicKeyPem, OPENSSL_ALGO_SHA256) === 1;
}

