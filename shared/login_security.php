<?php

declare(strict_types=1);

const LOGIN_DUMMY_PASSWORD_HASH = '$2y$10$7f88sJHCa9BnB3V0mzx4YO9HByB3H0QdVfW0IpW8I8wlQwLO9vf7W';

function verify_login_credentials(?array $userRow, string $password): bool
{
    $hashToVerify = $userRow && !empty($userRow['password_hash'])
        ? (string) $userRow['password_hash']
        : LOGIN_DUMMY_PASSWORD_HASH;

    $passwordOk = password_verify($password, $hashToVerify);
    return $userRow !== null && $passwordOk;
}

function normalized_session_concentrations(array $dbConcentrations): array
{
    $allowed = ['lx', 'snd'];
    $result = [];
    foreach ($dbConcentrations as $concentration) {
        if (!in_array($concentration, $allowed, true)) {
            continue;
        }
        if (in_array($concentration, $result, true)) {
            continue;
        }
        $result[] = $concentration;
    }

    return $result;
}
