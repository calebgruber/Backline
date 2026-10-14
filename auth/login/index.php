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

if (!app_is_installed()) {
    redirect('/setup');
}

if (auth_user()) {
    redirect('/dash/home');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'passkey_begin') {
    csrf_verify_or_fail();
    header('Content-Type: application/json; charset=UTF-8');
    if (!passkeys_table_exists()) {
        echo json_encode(['ok' => false, 'message' => 'Passkeys are unavailable until migrations are run.']);
        exit;
    }
    $email = mb_strtolower(trim(post('email')));
    if ($email === '') {
        echo json_encode(['ok' => false, 'message' => 'Enter your email first.']);
        exit;
    }
    $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (!$user) {
        echo json_encode(['ok' => false, 'message' => 'No account found for that email.']);
        exit;
    }
    $passkeyStmt = db()->prepare('SELECT credential_id_b64, transports_json FROM user_passkeys WHERE user_id = ? AND revoked_at IS NULL ORDER BY id ASC');
    $passkeyStmt->execute([(int) $user['id']]);
    $passkeys = $passkeyStmt->fetchAll();
    if (!$passkeys) {
        echo json_encode(['ok' => false, 'message' => 'No passkeys are registered for this account yet.']);
        exit;
    }
    $challengeState = passkey_begin_challenge('passkey_login', (int) $user['id']);
    $allowCredentials = [];
    foreach ($passkeys as $passkey) {
        $allowCredentials[] = [
            'id' => (string) $passkey['credential_id_b64'],
            'type' => 'public-key',
            'transports' => json_decode((string) ($passkey['transports_json'] ?? '[]'), true) ?: [],
        ];
    }
    echo json_encode([
        'ok' => true,
        'options' => [
            'challenge' => $challengeState['challenge'],
            'timeout' => 60000,
            'rpId' => passkey_rp_id(),
            'userVerification' => 'preferred',
            'allowCredentials' => $allowCredentials,
        ],
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'passkey_finish') {
    csrf_verify_or_fail();
    header('Content-Type: application/json; charset=UTF-8');
    if (!passkeys_table_exists()) {
        echo json_encode(['ok' => false, 'message' => 'Passkeys are unavailable until migrations are run.']);
        exit;
    }
    $challengeState = passkey_take_challenge('passkey_login');
    if (!$challengeState) {
        echo json_encode(['ok' => false, 'message' => 'Passkey login timed out. Please try again.']);
        exit;
    }

    $credentialId = trim((string) post('credential_id'));
    $clientDataJsonB64 = trim((string) post('client_data_json'));
    $authenticatorDataB64 = trim((string) post('authenticator_data'));
    $signatureB64 = trim((string) post('signature'));
    if ($credentialId === '' || $clientDataJsonB64 === '' || $authenticatorDataB64 === '' || $signatureB64 === '') {
        echo json_encode(['ok' => false, 'message' => 'Invalid passkey response payload.']);
        exit;
    }

    $passkeyStmt = db()->prepare('SELECT up.id, up.user_id, up.public_key_pem, up.sign_count FROM user_passkeys up WHERE up.credential_id_b64 = ? AND up.revoked_at IS NULL LIMIT 1');
    $passkeyStmt->execute([$credentialId]);
    $passkey = $passkeyStmt->fetch();
    if (!$passkey || (int) $passkey['user_id'] !== (int) $challengeState['user_id']) {
        echo json_encode(['ok' => false, 'message' => 'Passkey not recognized for this account.']);
        exit;
    }

    $clientData = passkey_parse_client_data($clientDataJsonB64);
    $authenticatorData = passkey_parse_authenticator_data($authenticatorDataB64);
    $signature = passkey_base64url_decode($signatureB64);
    if (!$clientData || !$authenticatorData || $signature === null) {
        echo json_encode(['ok' => false, 'message' => 'Could not decode passkey response.']);
        exit;
    }
    $clientJson = $clientData['json'];
    $clientType = (string) ($clientJson['type'] ?? '');
    $clientChallenge = (string) ($clientJson['challenge'] ?? '');
    $clientOrigin = (string) ($clientJson['origin'] ?? '');
    if ($clientType !== 'webauthn.get' || !hash_equals($challengeState['challenge'], $clientChallenge) || !hash_equals(passkey_origin(), $clientOrigin)) {
        echo json_encode(['ok' => false, 'message' => 'Passkey response failed validation.']);
        exit;
    }
    $expectedRpHash = hash('sha256', passkey_rp_id(), true);
    if (!hash_equals($expectedRpHash, (string) $authenticatorData['rp_id_hash'])) {
        echo json_encode(['ok' => false, 'message' => 'Passkey RP validation failed.']);
        exit;
    }
    if (((int) $authenticatorData['flags'] & 0x01) !== 0x01) {
        echo json_encode(['ok' => false, 'message' => 'User presence was not confirmed by the passkey.']);
        exit;
    }
    $isSignatureValid = passkey_verify_signature((string) $passkey['public_key_pem'], (string) $authenticatorData['raw'], (string) $clientData['raw'], $signature);
    if (!$isSignatureValid) {
        echo json_encode(['ok' => false, 'message' => 'Passkey signature check failed.']);
        exit;
    }
    $storedSignCount = (int) ($passkey['sign_count'] ?? 0);
    $newSignCount = (int) ($authenticatorData['sign_count'] ?? 0);
    if ($storedSignCount > 0 && $newSignCount > 0 && $newSignCount <= $storedSignCount) {
        echo json_encode(['ok' => false, 'message' => 'Passkey counter check failed.']);
        exit;
    }
    $updateStmt = db()->prepare('UPDATE user_passkeys SET sign_count = ?, last_used_at = NOW(), updated_at = NOW() WHERE id = ?');
    $updateStmt->execute([$newSignCount, (int) $passkey['id']]);

    if (!auth_login_user_id((int) $passkey['user_id'])) {
        echo json_encode(['ok' => false, 'message' => 'Unable to complete passkey login.']);
        exit;
    }
    echo json_encode(['ok' => true, 'redirect' => '/dash/home']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify_or_fail();
    $email = post('email');
    $password = post('password');
    if (auth_login($email, $password)) {
        flash_set('success', 'Welcome back.');
        redirect('/dash/home');
    }
    flash_set('danger', 'Invalid credentials.');
}

render_page('Login', function (): void {
    $appName = (string) app_setting('branding.app_name', config('app_name', 'Backline'));
    $brandingDir = __DIR__ . '/../../uploads/branding';
    $logoLightFile = function_exists('first_existing_brand_asset') ? first_existing_brand_asset($brandingDir, ['logo-light.*', 'logo.*']) : null;
    $logoDarkFile = function_exists('first_existing_brand_asset') ? first_existing_brand_asset($brandingDir, ['logo-dark.*']) : null;
    $logoLightPath = $logoLightFile ? '/uploads/branding/' . basename($logoLightFile) : null;
    $logoDarkPath = $logoDarkFile ? '/uploads/branding/' . basename($logoDarkFile) : null;
    $loginCardColor = (string) app_setting('branding.login_card_color', '');
    $loginCardIcon = (string) app_setting('branding.login_card_icon', '');
    ?>
    <div class="text-center mb-4">
        <a href="/" class="navbar-brand navbar-brand-autodark">
            <?php if ($logoLightPath): ?><img src="<?= e($logoLightPath) ?>" alt="<?= e($appName) ?> logo" class="auth-page-logo logo-light"><?php endif; ?>
            <?php if ($logoDarkPath): ?><img src="<?= e($logoDarkPath) ?>" alt="<?= e($appName) ?> logo" class="auth-page-logo logo-dark"><?php endif; ?>
            <?php if (!$logoLightPath && !$logoDarkPath): ?><span class="h2"><?= e($appName) ?></span><?php endif; ?>
        </a>
    </div>
    <form class="card card-md" method="post" autocomplete="on" data-card-color="<?= e($loginCardColor) ?>" data-card-icon="<?= e($loginCardIcon) ?>">
        <?= csrf_input() ?>
        <div class="card-body">
            <h2 class="h2 text-center mb-4">Sign in to your account</h2>
            <div class="mb-3">
                <label class="form-label">Email address</label>
                <input class="form-control" type="email" name="email" required autocomplete="email">
            </div>
            <div class="mb-2">
                <label class="form-label">
                    Password
                    <span class="form-label-description"><a href="/auth/forgot">Forgot password?</a></span>
                </label>
                <input class="form-control" type="password" name="password" required autocomplete="current-password">
            </div>
            <div class="form-footer">
                <button class="btn btn-primary w-100">Sign in</button>
            </div>
            <div class="text-center text-secondary my-2">or</div>
            <div class="form-footer">
                <button class="btn btn-outline-primary w-100" type="button" id="passkey-login-button">Sign in with passkey</button>
            </div>
        </div>
    </form>
    <script>
    (() => {
      const button = document.getElementById('passkey-login-button');
      if (!button) return;
      if (!window.PublicKeyCredential || !window.isSecureContext) {
        button.disabled = true;
        button.title = 'Passkeys require HTTPS and a supported browser.';
        return;
      }
      const form = button.closest('form');
      const emailInput = form?.querySelector('input[name="email"]');
      const csrfInput = form?.querySelector('input[name="_csrf"]');
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
        const endpoint = (form?.getAttribute('action') || window.location.pathname || '/auth/login');
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
        const email = (emailInput?.value || '').trim();
        if (!email) {
          alert('Enter your email, then try passkey sign-in.');
          emailInput?.focus();
          return;
        }
        button.disabled = true;
        try {
          const begin = await postForm({
            action: 'passkey_begin',
            _csrf: csrfInput?.value || '',
            email,
          });
          if (!begin?.ok || !begin.options) {
            throw new Error(begin?.message || 'Could not start passkey login.');
          }
          const options = begin.options;
          options.challenge = toBytes(options.challenge);
          options.allowCredentials = (options.allowCredentials || []).map((item) => ({
            ...item,
            id: toBytes(item.id),
          }));
          const credential = await navigator.credentials.get({ publicKey: options });
          if (!credential || !credential.response) {
            throw new Error('Passkey sign-in was cancelled.');
          }
          const assertion = credential;
          const finish = await postForm({
            action: 'passkey_finish',
            _csrf: csrfInput?.value || '',
            credential_id: assertion.id,
            client_data_json: toB64url(assertion.response.clientDataJSON),
            authenticator_data: toB64url(assertion.response.authenticatorData),
            signature: toB64url(assertion.response.signature),
          });
          if (!finish?.ok) {
            throw new Error(finish?.message || 'Passkey sign-in failed.');
          }
          window.location.href = finish.redirect || '/dash/home';
        } catch (error) {
          alert(error?.message || 'Passkey sign-in failed.');
        } finally {
          button.disabled = false;
        }
      });
    })();
    </script>
    <?php
});
