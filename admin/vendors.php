<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login('admin');
$pdo = db();

$branches  = $pdo->query('SELECT id, name FROM branches WHERE active = 1 ORDER BY name')->fetchAll();
$branchIds = array_map('intval', array_column($branches, 'id'));

if (is_post()) {
    check_csrf_or_bounce();
    $act  = $_POST['action'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $uname = strtolower(trim((string)($_POST['username'] ?? '')));
    $pass = (string)($_POST['password'] ?? '');
    $bid  = (int)($_POST['branch_id'] ?? 0);

    if ($act === 'create' || $act === 'update') {
        if ($name === '' || mb_strlen($name) > 100)                       flash('error', 'Enter the vendor\'s name.');
        elseif (!preg_match('/^[a-z0-9._-]{3,40}$/', $uname))              flash('error', 'Username: 3-40 letters, numbers, dot, dash or underscore.');
        elseif (!in_array($bid, $branchIds, true))                         flash('error', 'Pick a branch.');
        elseif ($act === 'create' && strlen($pass) < 8)                    flash('error', 'Password must be at least 8 characters.');
        elseif ($act === 'update' && $pass !== '' && strlen($pass) < 8)    flash('error', 'New password must be at least 8 characters (or leave it blank).');
        else {
            try {
                if ($act === 'create') {                                    // CREATE
                    $pdo->prepare("INSERT INTO users (name, username, password_hash, role, branch_id) VALUES (?,?,?,'vendor',?)")
                        ->execute([$name, $uname, password_hash($pass, PASSWORD_DEFAULT), $bid]);
                    log_activity('vendor', "Created vendor account: $name", $bid);
                    flash('ok', "Vendor “{$name}” created. Give them the username and password.");
                } else {                                                    // UPDATE
                    $pdo->prepare("UPDATE users SET name = ?, username = ?, branch_id = ? WHERE id = ? AND role = 'vendor'")->execute([$name, $uname, $bid, $id]);
                    if ($pass !== '') {
                        $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ? AND role = 'vendor'")->execute([password_hash($pass, PASSWORD_DEFAULT), $id]);
                    }
                    flash('ok', 'Vendor updated.');
                }
            } catch (PDOException $e) {
                flash('error', 'That username is already taken.');
            }
        }
    } elseif ($act === 'toggle') {                                          // UPDATE (enable / disable login)
        $pdo->prepare("UPDATE users SET active = 1 - active, last_seen = NULL WHERE id = ? AND role = 'vendor'")->execute([$id]);
        flash('ok', 'Vendor status changed.');
    } elseif ($act === 'delete') {                                          // DELETE
        try {
            $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'vendor'")->execute([$id]);
            flash('ok', 'Vendor deleted.');
        } catch (PDOException $e) {
            flash('error', 'This vendor has recorded sales or expenses, so the account cannot be deleted. Disable it instead.');
        }
    }
    redirect('admin/vendors.php');
}

// READ
$online = (int)ONLINE_SECS;
$vendors = $pdo->query("
  SELECT u.*, b.name AS branch,
         (u.last_seen >= NOW() - INTERVAL $online SECOND) AS is_online,
         DATE_FORMAT(u.last_login, '%b %e, %l:%i %p') AS login_at
    FROM users u LEFT JOIN branches b ON b.id = u.branch_id
   WHERE u.role = 'vendor' ORDER BY b.name, u.name")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);

$page_title = 'Vendors';
$nav = 'vendors';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Vendors</h1></div>

<?php if (!$branches): ?>
  <div class="card">Add a branch first, then create vendor accounts. <a class="btn btn-primary" href="<?= e(url('admin/branches.php')) ?>">Go to Branches</a></div>
<?php else: ?>
<section class="card">
  <h2>Create a vendor account</h2>
  <form method="post" class="row-form" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Full name <input name="name" required maxlength="100"></label>
    <label>Username <input name="username" required pattern="[A-Za-z0-9._-]{3,40}" autocapitalize="none"></label>
    <label>Password (8+) <input name="password" type="text" minlength="8" required autocomplete="off"></label>
    <label>Branch
      <select name="branch_id" required><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>"><?= e($b['name']) ?></option><?php endforeach; ?></select>
    </label>
    <button class="btn btn-primary">Create</button>
  </form>
  <p class="muted small">A vendor can only open the POS of their own branch.</p>
</section>
<?php endif; ?>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Vendor</th><th>Branch</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($vendors as $v): ?>
    <?php if ($editId === (int)$v['id']): ?>
    <tr><td colspan="5">
      <form method="post" class="row-form" autocomplete="off">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
        <label>Full name <input name="name" value="<?= e($v['name']) ?>" required maxlength="100"></label>
        <label>Username <input name="username" value="<?= e($v['username']) ?>" required pattern="[A-Za-z0-9._-]{3,40}"></label>
        <label>New password <input name="password" type="text" minlength="8" placeholder="leave blank to keep" autocomplete="off"></label>
        <label>Branch
          <select name="branch_id"><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= (int)$v['branch_id'] === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select>
        </label>
        <button class="btn btn-primary">Save</button><a class="btn btn-ghost" href="<?= e(url('admin/vendors.php')) ?>">Cancel</a>
      </form>
    </td></tr>
    <?php else: ?>
    <tr class="<?= $v['active'] ? '' : 'is-off' ?>">
      <td><strong><?= e($v['name']) ?></strong><br><small class="muted"><?= e($v['username']) ?></small></td>
      <td><?= e($v['branch']) ?></td>
      <td><?= !$v['active'] ? '<span class="badge">Disabled</span>' : ($v['is_online'] ? '<span class="badge badge-ok">Online</span>' : '<span class="badge">Offline</span>') ?></td>
      <td><?= e($v['login_at'] ?? 'Never') ?></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/vendors.php?edit=' . (int)$v['id'])) ?>">Edit</a>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
          <button class="btn btn-ghost btn-sm"><?= $v['active'] ? 'Disable' : 'Enable' ?></button></form>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this vendor account permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$v['id'] ?>">
          <button class="btn btn-ghost btn-sm btn-danger">Delete</button></form>
      </td>
    </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$vendors): ?><tr><td colspan="5" class="muted center">No vendor accounts yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
