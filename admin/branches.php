<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login('admin');
$pdo = db();

if (is_post()) {
    check_csrf_or_bounce();
    $act = $_POST['action'] ?? '';
    $id  = (int)($_POST['id'] ?? 0);
    $name = trim((string)($_POST['name'] ?? ''));
    $addr = trim((string)($_POST['address'] ?? ''));

    if ($act === 'create' || $act === 'update') {
        if ($name === '' || mb_strlen($name) > 100 || mb_strlen($addr) > 255) {
            flash('error', 'Enter a branch name (up to 100 characters).');
        } else {
            try {
                if ($act === 'create') {                                      // CREATE
                    $pdo->prepare('INSERT INTO branches (name, address) VALUES (?,?)')->execute([$name, $addr ?: null]);
                    log_activity('branch', "Added branch: $name", (int)$pdo->lastInsertId());
                    flash('ok', "Branch “{$name}” added.");
                } else {                                                      // UPDATE
                    $pdo->prepare('UPDATE branches SET name = ?, address = ? WHERE id = ?')->execute([$name, $addr ?: null, $id]);
                    flash('ok', 'Branch updated.');
                }
            } catch (PDOException $e) {
                flash('error', 'A branch with that name already exists.');
            }
        }
    } elseif ($act === 'toggle') {                                            // UPDATE (active / inactive)
        $pdo->prepare('UPDATE branches SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('ok', 'Branch status changed. Vendors of an inactive branch cannot sign in.');
    } elseif ($act === 'delete') {                                            // DELETE
        try {
            $pdo->prepare('DELETE FROM branches WHERE id = ?')->execute([$id]);
            flash('ok', 'Branch deleted.');
        } catch (PDOException $e) {
            flash('error', 'This branch still has vendors, products, sales or expenses, so it cannot be deleted. Deactivate it instead.');
        }
    }
    redirect('admin/branches.php');
}

// READ
$branches = $pdo->query("
  SELECT b.*,
    (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id AND u.role = 'vendor') AS vendors,
    (SELECT COUNT(*) FROM products p WHERE p.branch_id = b.id AND p.active = 1) AS products
  FROM branches b ORDER BY b.active DESC, b.name")->fetchAll();
$editId = (int)($_GET['edit'] ?? 0);

$page_title = 'Branches';
$nav = 'branches';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Branches</h1></div>

<section class="card">
  <h2>Add a branch</h2>
  <form method="post" class="row-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <label>Name <input name="name" required maxlength="100"></label>
    <label>Address (optional) <input name="address" maxlength="255"></label>
    <button class="btn btn-primary">Add branch</button>
  </form>
</section>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Branch</th><th>Address</th><th class="num">Vendors</th><th class="num">Products</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($branches as $b): ?>
    <?php if ($editId === (int)$b['id']): ?>
    <tr><td colspan="6">
      <form method="post" class="row-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
        <label>Name <input name="name" value="<?= e($b['name']) ?>" required maxlength="100"></label>
        <label>Address <input name="address" value="<?= e($b['address']) ?>" maxlength="255"></label>
        <button class="btn btn-primary">Save</button>
        <a class="btn btn-ghost" href="<?= e(url('admin/branches.php')) ?>">Cancel</a>
      </form>
    </td></tr>
    <?php else: ?>
    <tr class="<?= $b['active'] ? '' : 'is-off' ?>">
      <td><strong><?= e($b['name']) ?></strong></td><td><?= e($b['address']) ?></td>
      <td class="num"><?= (int)$b['vendors'] ?></td><td class="num"><?= (int)$b['products'] ?></td>
      <td><?= $b['active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge">Inactive</span>' ?></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/branches.php?edit=' . (int)$b['id'])) ?>">Edit</a>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <button class="btn btn-ghost btn-sm"><?= $b['active'] ? 'Deactivate' : 'Activate' ?></button></form>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this branch permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
          <button class="btn btn-ghost btn-sm btn-danger">Delete</button></form>
      </td>
    </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$branches): ?><tr><td colspan="6" class="muted center">No branches yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
