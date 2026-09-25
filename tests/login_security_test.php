<?php

declare(strict_types=1);

require_once __DIR__ . '/../shared/login_security.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$validPassword = 'ValidPassword123!';
$validUser = [
    'password_hash' => password_hash($validPassword, PASSWORD_DEFAULT),
];

assert_true(verify_login_credentials($validUser, $validPassword) === true, 'valid user/password should pass');
assert_true(verify_login_credentials($validUser, 'WrongPass123!') === false, 'wrong password should fail');
assert_true(verify_login_credentials(null, $validPassword) === false, 'missing user should fail while still executing verify path');
assert_true(verify_login_credentials(['password_hash' => ''], $validPassword) === false, 'user without usable hash should fail');
assert_true(
    normalized_session_concentrations(['snd', 'other', 'lx', 'snd']) === ['snd', 'lx'],
    'concentrations should keep only supported values in source order without duplicates'
);

fwrite(STDOUT, "login_security_test: OK\n");
