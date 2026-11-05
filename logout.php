<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) {
    log_activity('logout', $u['name'] . ' signed out');
    db()->prepare('UPDATE users SET last_seen = NULL WHERE id = ?')->execute([$u['id']]);
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
}
session_destroy();
header('Location: ' . url('login.php'));
exit;
