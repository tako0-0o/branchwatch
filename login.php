<?php
require __DIR__ . '/includes/bootstrap.php';

if ($u = current_user()) redirect(home_for($u));

$error = null;
$username = '';
if (is_post()) {
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!csrf_verify()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($username === '' || $password === '') {
        $error = 'Enter your username and password.';
    } else {
        $error = attempt_login($username, $password);
        if ($error === null) {
            redirect(home_for(current_user(true)));
        }
    }
}

$page_title = 'Sign in';
$body_class = 'auth';
require __DIR__ . '/includes/header.php';
?>
<section class="auth-card">
  <div class="auth-head">
    <span class="brand-mark big" aria-hidden="true">B</span>
    <h1>Sign in to <?= e(APP_NAME) ?></h1>
  </div>
  <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post" autocomplete="on" class="stack">
    <?= csrf_field() ?>
    <label>Username
      <input name="username" value="<?= e($username) ?>" autocomplete="username" autofocus required>
    </label>
    <label>Password
      <input name="password" type="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-block" type="submit">Sign in</button>
  </form>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>