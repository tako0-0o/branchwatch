<?php
$user = current_user();
$links = [];
if ($user && $user['role'] === 'admin') {
    $links = [
        'dashboard' => ['Dashboard', 'admin/dashboard.php'],
        'products'  => ['Products',  'admin/products.php'],
        'expenses'  => ['Expenses',  'admin/expenses.php'],
        'branches'  => ['Branches',  'admin/branches.php'],
        'vendors'   => ['Vendors',   'admin/vendors.php'],
    ];
} elseif ($user) {
    $links = ['pos' => ['POS', 'vendor/pos.php']];
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($page_title ?? APP_NAME) ?> · <?= e(APP_NAME) ?></title>
<link rel="icon" href="data:,">
<link rel="stylesheet" href="<?= e(url('assets/css/style.css')) ?>">
<script>
window.BW = <?= json_encode([
    'base'     => base_path(),
    'csrf'     => csrf_token(),
    'currency' => CURRENCY,
    'pollMs'   => POLL_MS,
    'categories' => EXPENSE_CATEGORIES,
], JSON_UNESCAPED_SLASHES) ?>;
</script>
</head>
<body class="<?= e($body_class ?? '') ?>">
<header class="topbar">
  <a class="brand" href="<?= e(url($user ? home_for($user) : 'login.php')) ?>">
    <span class="brand-mark" aria-hidden="true">B</span><?= e(APP_NAME) ?>
  </a>
  <?php if ($user): ?>
  <nav class="nav" aria-label="Main">
    <?php foreach ($links as $key => [$label, $href]): ?>
      <a href="<?= e(url($href)) ?>" class="<?= ($nav ?? '') === $key ? 'active' : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="userbox">
    <a href="<?= e(url('account.php')) ?>" class="who">
      <strong><?= e($user['name']) ?></strong>
      <small><?= $user['role'] === 'admin' ? 'Admin' : e($user['branch_name']) ?></small>
    </a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('logout.php')) ?>">Sign out</a>
  </div>
  <?php endif; ?>
</header>
<main class="page">
<?php foreach (take_flash() as $f): ?>
  <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
