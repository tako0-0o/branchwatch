<?php

function current_user(bool $refresh = false): ?array
{
    static $loaded = false, $user = null;
    if ($refresh) { $loaded = false; $user = null; }
    if ($loaded) return $user;
    $loaded = true;

    if (empty($_SESSION['uid'])) return null;

    $st = db()->prepare(
        'SELECT u.*, b.name AS branch_name, b.active AS branch_active
           FROM users u LEFT JOIN branches b ON b.id = u.branch_id
          WHERE u.id = ?'
    );
    $st->execute([(int)$_SESSION['uid']]);
    $u = $st->fetch();

    $bad = !$u || !$u['active']
        || ($u['role'] === 'vendor' && (!$u['branch_id'] || !$u['branch_active']));
    if ($bad) {
        unset($_SESSION['uid']);
        return null;
    }

    // see who is online 
    db()->prepare(
        'UPDATE users SET last_seen = NOW()
          WHERE id = ? AND (last_seen IS NULL OR last_seen < NOW() - INTERVAL 30 SECOND)'
    )->execute([$u['id']]);

    return $user = $u;
}

function home_for(array $u): string
{
    return $u['role'] === 'admin' ? 'admin/dashboard.php' : 'vendor/pos.php';
}

function require_login(?string $role = null): array
{
    $u = current_user();
    if (!$u) redirect('login.php');
    if ($role !== null && $u['role'] !== $role) redirect(home_for($u));
    return $u;
}

function api_boot(?string $role, bool $post = false): array
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    set_exception_handler(function (Throwable $e) {
        error_log($e);
        json_out(['ok' => false, 'error' => 'Server error. Please try again.'], 500);
    });

    $u = current_user();
    if (!$u) json_out(['ok' => false, 'error' => 'Please sign in again.'], 401);
    if ($role !== null && $u['role'] !== $role) json_out(['ok' => false, 'error' => 'Not allowed.'], 403);

    if ($post) {
        if (!is_post()) json_out(['ok' => false, 'error' => 'POST required.'], 405);
        if (!csrf_verify()) json_out(['ok' => false, 'error' => 'Session expired. Reload the page.'], 419);
    } else {
        session_write_close(); // don't let polling requests block each other
    }
    return $u;
}

// Returns null on success, or an error message
function attempt_login(string $username, string $password): ?string
{
    $pdo = db();
    $ip  = substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);

    // 5 failures per username or per IP in 15 minutes
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
          WHERE created_at > NOW() - INTERVAL 15 MINUTE AND (username = ? OR ip = ?)'
    );
    $st->execute([$username, $ip]);
    if ((int)$st->fetchColumn() >= 5) {
        return 'Too many failed attempts. Please wait 15 minutes and try again.';
    }

    $st = $pdo->prepare('SELECT * FROM users WHERE username = ?');
    $st->execute([$username]);
    $u = $st->fetch();

    // always run a hash check so timing doesn't reveal whether the username exists
    $hash = $u['password_hash'] ?? '$2y$10$usesomesillystringforsaltandhashingxxxxxxxxxxxxxxxxxxxxxxx';
    $ok = password_verify($password, $hash) && $u && $u['active'];

    if (!$ok) {
        $pdo->prepare('INSERT INTO login_attempts (username, ip) VALUES (?,?)')->execute([$username, $ip]);
        return 'Wrong username or password.';
    }

    if ($u['role'] === 'vendor') {
        $st = $pdo->prepare('SELECT active FROM branches WHERE id = ?');
        $st->execute([$u['branch_id']]);
        if (!$st->fetchColumn()) return 'Your branch is not active. Contact the admin.';
    }

    $pdo->prepare('DELETE FROM login_attempts WHERE username = ?')->execute([$username]);
    session_regenerate_id(true);
    $_SESSION['uid'] = (int)$u['id'];
    unset($_SESSION['csrf']);
    $pdo->prepare('UPDATE users SET last_login = NOW(), last_seen = NOW() WHERE id = ?')->execute([$u['id']]);
    log_activity('login', $u['name'] . ' signed in', $u['branch_id'] ? (int)$u['branch_id'] : null, (int)$u['id']);
    return null;
}
