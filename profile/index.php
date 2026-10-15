<?php

declare(strict_types=1);

if (!function_exists('app_config')) {
    $bootstrapRoot = __DIR__;
    while (!file_exists($bootstrapRoot . '/shared/bootstrap.php')) {
        $parent = dirname($bootstrapRoot);
        if ($parent === $bootstrapRoot) {
            break;
        }
        $bootstrapRoot = $parent;
    }
    require_once $bootstrapRoot . '/shared/bootstrap.php';
}

$user = require_auth();
$roleOptions = [
    'admin.access' => ['label' => 'Admin', 'class' => 'bg-red-lt'],
    'lx.access' => ['label' => 'Lighting Major', 'class' => 'bg-blue-lt'],
    'snd.access' => ['label' => 'Sound Major', 'class' => 'bg-indigo-lt'],
    'lx.shop' => ['label' => 'Lighting Shop', 'class' => 'bg-cyan-lt'],
    'snd.shop' => ['label' => 'Sound Shop', 'class' => 'bg-purple-lt'],
    'snd.cables.manage' => ['label' => 'Spreadsheet Editor', 'class' => 'bg-teal-lt'],
    'paid.export' => ['label' => 'Paid', 'class' => 'bg-green-lt'],
];
$roleBadges = [];
if ((int) ($user['is_super_admin'] ?? 0) === 1) {
    $roleBadges[] = ['label' => 'Super Admin', 'class' => 'bg-red-lt'];
}
$permStmt = db()->prepare('SELECT p.key_name FROM user_permissions up JOIN permissions p ON p.id = up.permission_id WHERE up.user_id = ?');
$permStmt->execute([(int) $user['id']]);
$permissionKeys = array_values(array_unique(array_map('strval', $permStmt->fetchAll(PDO::FETCH_COLUMN))));
foreach ($roleOptions as $permissionKey => $meta) {
    if (in_array($permissionKey, $permissionKeys, true)) {
        $roleBadges[] = $meta;
    }
}
$passkeysAvailable = passkeys_table_exists();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $action = post('action');

    if ($action === 'passkey_begin_register') {
        header('Content-Type: application/json; charset=UTF-8');
        if (!$passkeysAvailable) {
            echo json_encode(['ok' => false, 'message' => 'Passkeys are unavailable until migrations are run.']);
            exit;
        }
        $excludeStmt = db()->prepare('SELECT credential_id_b64, transports_json FROM user_passkeys WHERE user_id = ? AND revoked_at IS NULL ORDER BY id ASC');
        $excludeStmt->execute([(int) $user['id']]);
        $excludeRows = $excludeStmt->fetchAll();
        $challengeState = passkey_begin_challenge('passkey_register', (int) $user['id']);
        $excludeCredentials = [];
        foreach ($excludeRows as $row) {
            $excludeCredentials[] = [
                'id' => (string) $row['credential_id_b64'],
                'type' => 'public-key',
                'transports' => json_decode((string) ($row['transports_json'] ?? '[]'), true) ?: [],
            ];
        }
        echo json_encode([
            'ok' => true,
            'options' => [
                'challenge' => $challengeState['challenge'],
                'rp' => [
                    'name' => (string) app_setting('branding.app_name', config('app_name', 'Backline')),
                    'id' => passkey_rp_id(),
                ],
                'user' => [
                    'id' => passkey_base64url_encode('user-' . (string) $user['id']),
                    'name' => (string) $user['email'],
                    'displayName' => (string) $user['name'],
                ],
                'pubKeyCredParams' => [
                    ['type' => 'public-key', 'alg' => -7],
                    ['type' => 'public-key', 'alg' => -257],
                ],
                'timeout' => 60000,
                'attestation' => 'none',
                'authenticatorSelection' => [
                    'residentKey' => 'required',
                    'authenticatorAttachment' => 'platform',
                    'userVerification' => 'required',
                ],
                'excludeCredentials' => $excludeCredentials,
            ],
        ]);
        exit;
    }

    if ($action === 'passkey_finish_register') {
        header('Content-Type: application/json; charset=UTF-8');
        if (!$passkeysAvailable) {
            echo json_encode(['ok' => false, 'message' => 'Passkeys are unavailable until migrations are run.']);
            exit;
        }
        $challengeState = passkey_take_challenge('passkey_register');
        if (!$challengeState || (int) $challengeState['user_id'] !== (int) $user['id']) {
            echo json_encode(['ok' => false, 'message' => 'Passkey registration timed out. Please try again.']);
            exit;
        }
        $credentialId = trim((string) post('credential_id'));
        $clientDataJsonB64 = trim((string) post('client_data_json'));
        $authenticatorDataB64 = trim((string) post('authenticator_data'));
        $publicKeyB64 = trim((string) post('public_key'));
        $displayName = trim((string) post('display_name'));
        $transportsRaw = trim((string) post('transports_json'));
        if ($credentialId === '' || $clientDataJsonB64 === '' || $authenticatorDataB64 === '' || $publicKeyB64 === '') {
            echo json_encode(['ok' => false, 'message' => 'Invalid passkey registration payload.']);
            exit;
        }
        $clientData = passkey_parse_client_data($clientDataJsonB64);
        $authenticatorData = passkey_parse_authenticator_data($authenticatorDataB64);
        $publicKeyPem = passkey_spki_pem_from_b64url($publicKeyB64);
        if (!$clientData || !$authenticatorData || !$publicKeyPem) {
            echo json_encode(['ok' => false, 'message' => 'Could not decode passkey registration data.']);
            exit;
        }
        $clientJson = $clientData['json'];
        $clientType = (string) ($clientJson['type'] ?? '');
        $clientChallenge = (string) ($clientJson['challenge'] ?? '');
        $clientOrigin = (string) ($clientJson['origin'] ?? '');
        if ($clientType !== 'webauthn.create' || !hash_equals((string) $challengeState['challenge'], $clientChallenge) || !hash_equals(passkey_origin(), $clientOrigin)) {
            echo json_encode(['ok' => false, 'message' => 'Passkey registration validation failed.']);
            exit;
        }
        $expectedRpHash = hash('sha256', passkey_rp_id(), true);
        if (!hash_equals($expectedRpHash, (string) $authenticatorData['rp_id_hash'])) {
            echo json_encode(['ok' => false, 'message' => 'Passkey RP validation failed.']);
            exit;
        }
        if (((int) $authenticatorData['flags'] & 0x01) !== 0x01 || ((int) $authenticatorData['flags'] & 0x40) !== 0x40) {
            echo json_encode(['ok' => false, 'message' => 'Passkey attestation flags were invalid.']);
            exit;
        }
        $credentialIdRaw = passkey_base64url_decode($credentialId);
        if ($credentialIdRaw === null) {
            echo json_encode(['ok' => false, 'message' => 'Passkey credential ID was invalid.']);
            exit;
        }
        $authDataCredentialId = $authenticatorData['credential_id'];
        if (!is_string($authDataCredentialId) || !hash_equals($authDataCredentialId, $credentialIdRaw)) {
            echo json_encode(['ok' => false, 'message' => 'Passkey credential mismatch.']);
            exit;
        }
        $name = $displayName !== '' ? mb_substr($displayName, 0, 190) : ('Passkey ' . date('Y-m-d H:i'));
        $transports = json_decode($transportsRaw, true);
        if (!is_array($transports)) {
            $transports = [];
        }
        $transports = array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $transports), static fn ($v): bool => $v !== ''));
        try {
            $insertStmt = db()->prepare('INSERT INTO user_passkeys (user_id, credential_id_b64, display_name, public_key_pem, sign_count, transports_json, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())');
            $insertStmt->execute([(int) $user['id'], $credentialId, $name, $publicKeyPem, (int) $authenticatorData['sign_count'], json_encode($transports)]);
        } catch (Throwable) {
            echo json_encode(['ok' => false, 'message' => 'That passkey already exists on your account.']);
            exit;
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'update_name') {
        $name = post('name');
        if ($name === '') {
            flash_set('danger', 'Name is required.');
        } else {
            $stmt = db()->prepare('UPDATE users SET name = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$name, (int) $user['id']]);
            flash_set('success', 'Profile updated.');
        }
        redirect('/profile');
    }

    if ($action === 'rename_passkey') {
        if (!$passkeysAvailable) {
            flash_set('warning', 'Passkeys are unavailable until migrations are run.');
            redirect('/profile');
        }
        $passkeyId = (int) post('passkey_id');
        $displayName = mb_substr(trim((string) post('display_name')), 0, 190);
        if ($passkeyId <= 0 || $displayName === '') {
            flash_set('danger', 'Passkey name is required.');
            redirect('/profile');
        }
        $stmt = db()->prepare('UPDATE user_passkeys SET display_name = ?, updated_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL');
        $stmt->execute([$displayName, $passkeyId, (int) $user['id']]);
        flash_set('success', 'Passkey updated.');
        redirect('/profile');
    }

    if ($action === 'delete_passkey') {
        if (!$passkeysAvailable) {
            flash_set('warning', 'Passkeys are unavailable until migrations are run.');
            redirect('/profile');
        }
        $passkeyId = (int) post('passkey_id');
        if ($passkeyId > 0) {
            $stmt = db()->prepare('UPDATE user_passkeys SET revoked_at = NOW(), updated_at = NOW() WHERE id = ? AND user_id = ? AND revoked_at IS NULL');
            $stmt->execute([$passkeyId, (int) $user['id']]);
        }
        flash_set('success', 'Passkey removed.');
        redirect('/profile');
    }

    if ($action === 'change_password') {
        $currentPassword = post('current_password');
        $newPassword = post('new_password');
        $confirmPassword = post('confirm_password');

        if (!password_verify($currentPassword, (string) $user['password_hash'])) {
            flash_set('danger', 'Current password is incorrect.');
            redirect('/profile');
        }
        if (strlen($newPassword) < 12) {
            flash_set('danger', 'New password must be at least 12 characters.');
            redirect('/profile');
        }
        if ($newPassword !== $confirmPassword) {
            flash_set('danger', 'New password and confirmation do not match.');
            redirect('/profile');
        }

        $stmt = db()->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), (int) $user['id']]);
        flash_set('success', 'Password updated.');
        redirect('/profile');
    }

    if ($action === 'update_desktop_mode') {
        $choice = trim((string) post('desktop_mode_override', 'system'));
        $value = null;
        if ($choice === 'on') {
            $value = 1;
        } elseif ($choice === 'off') {
            $value = 0;
        }
        try {
            $stmt = db()->prepare('UPDATE users SET desktop_mode_override = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$value, (int) $user['id']]);
            flash_set('success', 'Desktop mode preference updated.');
        } catch (Throwable) {
            flash_set('warning', 'Desktop mode preference is unavailable until migrations are run.');
        }
        redirect('/profile');
    }
}

if ($passkeysAvailable) {
    $passkeysStmt = db()->prepare('SELECT id, display_name, created_at, last_used_at FROM user_passkeys WHERE user_id = ? AND revoked_at IS NULL ORDER BY id DESC');
    $passkeysStmt->execute([(int) $user['id']]);
    $passkeys = $passkeysStmt->fetchAll();
} else {
    $passkeys = [];
}

render_page('Profile', function () use ($user, $roleBadges, $passkeysAvailable, $passkeys): void {
    ?>
    <div class="row row-cards">
        <div class="col-lg-4">
            <div class="card profile-sticky-card">
                <div class="card-body text-center">
                    <span class="avatar avatar-xl rounded-3 mb-3" style="background-image:url(https://api.dicebear.com/9.x/thumbs/svg?seed=<?= urlencode((string) $user['email']) ?>)"></span>
                    <h3 class="mb-1"><?= e($user['name']) ?></h3>
                    <p class="text-secondary mb-0"><?= e($user['email']) ?></p>
                    <?php if ($roleBadges): ?>
                        <div class="mt-3 d-flex flex-wrap justify-content-center gap-2">
                            <?php foreach ($roleBadges as $badge): ?>
                                <span class="badge <?= e((string) $badge['class']) ?>"><?= e((string) $badge['label']) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="row row-cards">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Edit Profile</h3></div>
                        <div class="card-body">
                            <form method="post">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="update_name">
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input class="form-control" value="<?= e($user['email']) ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input class="form-control" name="name" value="<?= e($user['name']) ?>" required>
                                </div>
                                <button class="btn btn-primary">Save Profile</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h3 class="card-title mb-0">Passkeys</h3>
                            <button class="btn btn-outline-primary btn-sm" id="add-passkey-button" type="button" <?= $passkeysAvailable ? '' : 'disabled' ?>>Add Passkey</button>
                        </div>
                        <div class="card-body">
                            <?php if (!$passkeysAvailable): ?>
                                <div class="alert alert-warning mb-0">Passkeys are unavailable until migrations are run from the admin setup tools.</div>
                            <?php elseif (!$passkeys): ?>
                                <p class="text-secondary mb-0">No passkeys added yet.</p>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($passkeys as $passkey): ?>
                                        <div class="border rounded p-3">
                                            <form method="post" class="d-flex flex-wrap gap-2 align-items-end mb-2">
                                                <?= csrf_input() ?>
                                                <input type="hidden" name="action" value="rename_passkey">
                                                <input type="hidden" name="passkey_id" value="<?= (int) $passkey['id'] ?>">
                                                <div class="flex-fill" style="min-width: 220px;">
                                                    <label class="form-label mb-1">Passkey Name</label>
                                                    <input class="form-control form-control-sm" name="display_name" value="<?= e((string) $passkey['display_name']) ?>" required>
                                                </div>
                                                <button class="btn btn-sm btn-primary">Save</button>
                                            </form>
                                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                                                <div class="text-secondary small">
                                                    Added <?= e((string) $passkey['created_at']) ?>
                                                    <?php if (!empty($passkey['last_used_at'])): ?>
                                                        · Last used <?= e((string) $passkey['last_used_at']) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="post" onsubmit="return confirm('Remove this passkey?')">
                                                    <?= csrf_input() ?>
                                                    <input type="hidden" name="action" value="delete_passkey">
                                                    <input type="hidden" name="passkey_id" value="<?= (int) $passkey['id'] ?>">
                                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                                </form>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($passkeysAvailable): ?>
                                <div class="form-hint mt-3">Passkeys require HTTPS and a browser/device that supports WebAuthn.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Change Password</h3></div>
                        <div class="card-body">
                            <form method="post" autocomplete="off">
                                <?= csrf_input() ?>
                                <input type="hidden" name="action" value="change_password">
                                <div class="mb-3">
                                    <label class="form-label">Current Password</label>
                                    <input class="form-control" type="password" name="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password</label>
                                    <input class="form-control" type="password" name="new_password" minlength="12" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input class="form-control" type="password" name="confirm_password" minlength="12" required>
                                </div>
                                <button class="btn btn-primary">Update Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
    (() => {
      const button = document.getElementById('add-passkey-button');
      if (!button) return;
      if (!window.PublicKeyCredential || !window.isSecureContext) {
        button.disabled = true;
        button.title = 'Passkeys require HTTPS and a supported browser.';
        return;
      }
      const csrfInput = document.querySelector('input[name="_csrf"]');
      const toBytes = (b64url) => {
        const base64 = b64url.replace(/-/g, '+').replace(/_/g, '/');
        const padded = base64 + '==='.slice((base64.length + 3) % 4);
        const str = atob(padded);
        return Uint8Array.from(str, (c) => c.charCodeAt(0));
      };
      const toB64url = (buffer) => {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        bytes.forEach((b) => { binary += String.fromCharCode(b); });
        return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
      };
      const postForm = async (payload) => {
        const body = new URLSearchParams(payload);
        const endpoint = (window.location.pathname || '/profile');
        const response = await fetch(endpoint, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
          },
          body: body.toString(),
          credentials: 'same-origin',
        });
        const text = await response.text();
        try {
          return JSON.parse(text);
        } catch (_) {
          const trimmed = (text || '').trim();
          const lower = trimmed.toLowerCase();
          if (lower.startsWith('<!doctype') || lower.startsWith('<html')) {
            throw new Error('Server returned HTML instead of JSON. Please refresh and try again.');
          }
          throw new Error(trimmed !== '' ? trimmed : 'Unexpected server response.');
        }
      };
      button.addEventListener('click', async () => {
        button.disabled = true;
        try {
          const begin = await postForm({
            action: 'passkey_begin_register',
            _csrf: csrfInput?.value || '',
          });
          if (!begin?.ok || !begin.options) {
            throw new Error(begin?.message || 'Could not start passkey registration.');
          }
          const options = begin.options;
          options.challenge = toBytes(options.challenge);
          options.user.id = toBytes(options.user.id);
          options.excludeCredentials = (options.excludeCredentials || []).map((item) => ({
            ...item,
            id: toBytes(item.id),
          }));
          const credential = await navigator.credentials.create({ publicKey: options });
          if (!credential || !credential.response || typeof credential.response.getPublicKey !== 'function') {
            throw new Error('This browser cannot export passkey public keys yet.');
          }
          const passkeyName = window.prompt('Name this passkey', 'My Passkey') || '';
          const transports = (typeof credential.response.getTransports === 'function')
            ? credential.response.getTransports()
            : [];
          const publicKey = credential.response.getPublicKey();
          if (!publicKey) {
            throw new Error('Could not read public key from this passkey.');
          }
          const finish = await postForm({
            action: 'passkey_finish_register',
            _csrf: csrfInput?.value || '',
            credential_id: credential.id,
            client_data_json: toB64url(credential.response.clientDataJSON),
            authenticator_data: toB64url(credential.response.getAuthenticatorData()),
            public_key: toB64url(publicKey),
            transports_json: JSON.stringify(transports || []),
            display_name: passkeyName.trim(),
          });
          if (!finish?.ok) {
            throw new Error(finish?.message || 'Passkey registration failed.');
          }
          window.location.reload();
        } catch (error) {
          alert(error?.message || 'Passkey registration failed.');
        } finally {
          button.disabled = false;
        }
      });
    })();
    </script>
    <?php
}, $user);
