<?php
/**
 * RentaDog admin auth — shared helper
 * Created Day 2 of pentest week (2026-04-21) — A1 hardening (FIND-008)
 *
 * Replaces the inline `$ADMIN_PASS` plaintext that lived
 * in pages/admin_contacts.php, pages/admin_helpdesk.php, and the orphan
 * rentadog/pages/admin_helpdesk.php.
 *
 * Architecture:
 *   - Credentials in C:\ProgramData\RentaDog\admin.env (outside docroot)
 *   - bcrypt hash (cost=12) is the primary auth path
 *   - Legacy plaintext fallback during cutover (LEGACY_AUTH_DISABLED=false)
 *     so a bad new-hash deploy never locks us out; flip to true after verify
 *   - IP-based lockout (3 strikes / 5 min by default), Kali whitelisted
 *   - Cookie hardening: httponly + samesite=Lax (+ secure when TLS lands)
 *   - session_regenerate_id(true) on successful login (fixation prevention)
 *
 * Usage from each admin page (BEFORE any output):
 *
 *   require_once __DIR__ . '/../includes/admin_auth.php';
 *   admin_auth_require([
 *       'title'       => 'Bark Bot Admin',
 *       'theme_color' => '#6c5ce7',
 *       'theme_hover' => '#5a4bd1',
 *   ]);
 *
 * After this call, $_SESSION['admin_auth'] === true; otherwise the function
 * has already rendered the login form / lockout page and called exit.
 */

const ADMIN_AUTH_ENV_FILE = 'C:/ProgramData/RentaDog/admin.env';
const ADMIN_AUTH_LOCK_DIR = 'C:/xampp/tmp/admin_auth_fails';

/* ──────────────────────────────────────────────────────────────────────── */

function admin_auth_load_env(): array {
    static $cache = null;
    if ($cache !== null) return $cache;
    $cache = [
        'ADMIN_PASS_HASH'      => '',
        'LEGACY_PLAINTEXT'     => '',
        'LEGACY_AUTH_DISABLED' => 'false',
        'LOCKOUT_THRESHOLD'    => '3',
        'LOCKOUT_WINDOW_SEC'   => '300',
        'LOCKOUT_DURATION_SEC' => '300',
        'LOCKOUT_WHITELIST'    => '172.31.0.100,127.0.0.1,::1',
    ];
    if (!is_readable(ADMIN_AUTH_ENV_FILE)) {
        error_log('[admin_auth] env file unreadable: ' . ADMIN_AUTH_ENV_FILE);
        return $cache;
    }
    foreach (file(ADMIN_AUTH_ENV_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (strpos($line, '=') === false) continue;
        [$k, $v] = explode('=', $line, 2);
        $k = trim($k);
        $v = trim($v);
        if (array_key_exists($k, $cache)) {
            $cache[$k] = $v;
        }
    }
    return $cache;
}

function admin_auth_client_ip(): string {
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function admin_auth_is_whitelisted(string $ip, array $env): bool {
    $list = array_filter(array_map('trim', explode(',', $env['LOCKOUT_WHITELIST'])));
    return in_array($ip, $list, true);
}

function admin_auth_lockfile(string $ip): string {
    if (!is_dir(ADMIN_AUTH_LOCK_DIR)) {
        @mkdir(ADMIN_AUTH_LOCK_DIR, 0700, true);
    }
    return ADMIN_AUTH_LOCK_DIR . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
}

function admin_auth_load_state(string $ip): array {
    $f = admin_auth_lockfile($ip);
    if (!is_file($f)) return ['fails' => [], 'locked_until' => 0];
    $j = json_decode(@file_get_contents($f), true);
    if (!is_array($j)) return ['fails' => [], 'locked_until' => 0];
    return [
        'fails'        => is_array($j['fails'] ?? null) ? $j['fails'] : [],
        'locked_until' => (int)($j['locked_until'] ?? 0),
    ];
}

function admin_auth_save_state(string $ip, array $state): void {
    $f = admin_auth_lockfile($ip);
    @file_put_contents($f, json_encode($state, JSON_PRETTY_PRINT), LOCK_EX);
}

function admin_auth_record_fail(string $ip, array $env): array {
    $now   = time();
    $state = admin_auth_load_state($ip);
    $win   = (int)$env['LOCKOUT_WINDOW_SEC'];
    // prune fails older than window
    $state['fails'] = array_values(array_filter($state['fails'], fn($t) => ($now - $t) < $win));
    $state['fails'][] = $now;
    $thresh = (int)$env['LOCKOUT_THRESHOLD'];
    if (count($state['fails']) >= $thresh) {
        $state['locked_until'] = $now + (int)$env['LOCKOUT_DURATION_SEC'];
        error_log("[admin_auth] LOCKED OUT $ip — {$thresh} fails in {$win}s");
    }
    admin_auth_save_state($ip, $state);
    return $state;
}

function admin_auth_clear_fails(string $ip): void {
    @unlink(admin_auth_lockfile($ip));
}

function admin_auth_is_locked(string $ip, array $env): array {
    if (admin_auth_is_whitelisted($ip, $env)) return [false, 0];
    $state = admin_auth_load_state($ip);
    $now   = time();
    if (($state['locked_until'] ?? 0) > $now) {
        return [true, $state['locked_until'] - $now];
    }
    return [false, 0];
}

/**
 * Verify a submitted password. Returns:
 *   'hash'    — matched bcrypt hash (preferred path)
 *   'legacy'  — matched legacy plaintext (only if LEGACY_AUTH_DISABLED=false)
 *   'fail'    — no match
 */
function admin_auth_verify(string $submitted, array $env): string {
    $hash = $env['ADMIN_PASS_HASH'] ?? '';
    if ($hash !== '' && password_verify($submitted, $hash)) {
        return 'hash';
    }
    $legacy_disabled = strtolower(trim($env['LEGACY_AUTH_DISABLED'] ?? 'true')) === 'true';
    if (!$legacy_disabled) {
        $legacy = $env['LEGACY_PLAINTEXT'] ?? '';
        if ($legacy !== '' && hash_equals($legacy, $submitted)) {
            return 'legacy';
        }
    }
    return 'fail';
}

/* ──────────────────────────────────────────────────────────────────────── */

function admin_auth_require(array $opts = []): void {
    $title       = $opts['title']       ?? 'Admin Login';
    $theme_color = $opts['theme_color'] ?? '#6c5ce7';
    $theme_hover = $opts['theme_hover'] ?? '#5a4bd1';

    if (session_status() === PHP_SESSION_NONE) {
        // Cookie hardening — set BEFORE session_start. SameSite=Lax keeps form
        // POST flows working; httponly blocks JS theft. (Add 'secure' when TLS.)
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    $env = admin_auth_load_env();
    $ip  = admin_auth_client_ip();
    [$locked, $remaining] = admin_auth_is_locked($ip, $env);

    // Logout
    if (isset($_GET['logout'])) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'] ?? false, $p['httponly'] ?? true);
        }
        session_destroy();
        header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
        exit;
    }

    $login_error = null;

    // Already authed — let the page render
    if (!empty($_SESSION['admin_auth'])) {
        return;
    }

    // Locked? Refuse even to attempt verification
    if ($locked) {
        admin_auth_render_locked($title, $theme_color, $remaining);
        exit;
    }

    // POST attempt
    if (isset($_POST['admin_pass'])) {
        $result = admin_auth_verify((string)$_POST['admin_pass'], $env);
        if ($result === 'hash' || $result === 'legacy') {
            session_regenerate_id(true);  // fixation prevention
            $_SESSION['admin_auth']   = true;
            $_SESSION['admin_method'] = $result;
            $_SESSION['admin_at']     = time();
            admin_auth_clear_fails($ip);
            error_log("[admin_auth] login OK ip=$ip method=$result");
            // Redirect to GET so refresh doesn't re-POST
            header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
            exit;
        }
        // Fail path
        admin_auth_record_fail($ip, $env);
        error_log("[admin_auth] login FAIL ip=$ip");
        // Re-check lock state immediately so the user sees the lockout page
        // on the same response if their last attempt tripped it
        [$locked2, $rem2] = admin_auth_is_locked($ip, $env);
        if ($locked2) {
            admin_auth_render_locked($title, $theme_color, $rem2);
            exit;
        }
        $login_error = 'Incorrect password.';
    }

    admin_auth_render_login($title, $theme_color, $theme_hover, $login_error);
    exit;
}

function admin_auth_render_login(string $title, string $color, string $hover, ?string $err): void {
    $title_h = htmlspecialchars($title);
    $err_h   = $err !== null ? htmlspecialchars($err) : null;
    ?><!DOCTYPE html><html><head><title><?= $title_h ?></title>
    <style>body{font-family:-apple-system,sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
    .login{background:#fff;padding:40px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.1);width:340px;text-align:center;}
    .login h2{margin-bottom:20px;color:#2d3436;} .login input{width:100%;padding:12px;border:1px solid #ddd;border-radius:8px;font-size:1rem;margin-bottom:16px;box-sizing:border-box;}
    .login button{width:100%;padding:12px;background:<?= htmlspecialchars($color) ?>;color:#fff;border:none;border-radius:8px;font-size:1rem;font-weight:600;cursor:pointer;}
    .login button:hover{background:<?= htmlspecialchars($hover) ?>;} .error{color:#d63031;font-size:0.9rem;margin-bottom:12px;}</style></head>
    <body><div class="login"><h2><?= $title_h ?></h2>
    <?php if ($err_h !== null): ?><div class="error"><?= $err_h ?></div><?php endif; ?>
    <form method="POST"><input type="password" name="admin_pass" placeholder="Admin password" autofocus>
    <button type="submit">Sign In</button></form></div></body></html><?php
}

function admin_auth_render_locked(string $title, string $color, int $remaining_sec): void {
    $title_h = htmlspecialchars($title);
    $minutes = max(1, (int)ceil($remaining_sec / 60));
    ?><!DOCTYPE html><html><head><title><?= $title_h ?> — Locked</title>
    <style>body{font-family:-apple-system,sans-serif;background:#f0f2f5;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;}
    .login{background:#fff;padding:40px;border-radius:12px;box-shadow:0 2px 12px rgba(0,0,0,0.1);width:380px;text-align:center;}
    .login h2{margin-bottom:12px;color:#d63031;} .login p{color:#636e72;margin-bottom:8px;}</style></head>
    <body><div class="login"><h2>Too many failed attempts</h2>
    <p>This source IP has been temporarily locked from the admin login.</p>
    <p>Try again in approximately <strong><?= $minutes ?></strong> minute<?= $minutes === 1 ? '' : 's' ?>.</p>
    </div></body></html><?php
}
