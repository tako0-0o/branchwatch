<?php
require __DIR__ . '/includes/bootstrap.php';
$user = require_login();

if (is_post()) {
    check_csrf_or_bounce();
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $rep = (string)($_POST['repeat'] ?? '');

    if (!password_verify($cur, $user['password_hash'])) {
        flash('error', 'Your current password is wrong.');
    } elseif (strlen($new) < 8) {
        flash('error', 'New password must be at least 8 characters.');
    } elseif ($new !== $rep) {
        flash('error', 'The two new passwords do not match.');
    } else {
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
        log_activity('password', $user['name'] . ' changed their password');
        flash('ok', 'Password updated.');
    }
    redirect('account.php');
}

$page_title = 'Account';
$nav = '';
require __DIR__ . '/includes/header.php';
?>
<h1>My account</h1>
<section class="card narrow">
  <dl class="meta">
    <dt>Name</dt><dd><?= e($user['name']) ?></dd>
    <dt>Username</dt><dd><?= e($user['username']) ?></dd>
    <dt>Role</dt><dd><?= $user['role'] === 'admin' ? 'Admin (all branches)' : 'Vendor · ' . e($user['branch_name']) ?></dd>
  </dl>
  <h2>Change password</h2>
  <form method="post" class="stack">
    <?= csrf_field() ?>
    <label>Current password <input type="password" name="current" autocomplete="current-password" required></label>
    <label>New password (8+ characters) <input type="password" name="new" minlength="8" autocomplete="new-password" required></label>
    <label>Repeat new password <input type="password" name="repeat" minlength="8" autocomplete="new-password" required></label>
    <button class="btn btn-primary" type="submit">Update password</button>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
