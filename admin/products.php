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
    $price = $_POST['price'] ?? '';
    $stock = (int)($_POST['stock'] ?? 0);
    $thr   = (int)($_POST['threshold'] ?? 5);

    if ($act === 'create' || $act === 'update') {
        $bid = (int)($_POST['branch_id'] ?? 0);
        if ($name === '' || mb_strlen($name) > 120)                          flash('error', 'Enter a product name (up to 120 characters).');
        elseif (!is_numeric($price) || $price < 0 || $price > 9999999)       flash('error', 'Enter a valid price.');
        elseif ($stock < 0 || $thr < 0)                                      flash('error', 'Stock and alert level cannot be negative.');
        elseif ($act === 'create' && !in_array($bid, $branchIds, true))      flash('error', 'Pick a branch.');
        else {
            $price = number_format((float)$price, 2, '.', '');
            try {
                if ($act === 'create') {                                      // CREATE
                    $pdo->prepare('INSERT INTO products (branch_id, name, price, stock, low_stock_threshold) VALUES (?,?,?,?,?)')
                        ->execute([$bid, $name, $price, $stock, $thr]);
                    log_activity('product', "Added product: $name", $bid);
                    flash('ok', "Added “{$name}”.");
                } else {                                                      // UPDATE
                    $pdo->prepare('UPDATE products SET name = ?, price = ?, stock = ?, low_stock_threshold = ? WHERE id = ?')
                        ->execute([$name, $price, $stock, $thr, $id]);
                    log_activity('product', "Edited product: $name");
                    flash('ok', 'Product updated.');
                }
            } catch (PDOException $e) {
                flash('error', 'That branch already has a product with this name.');
            }
        }
    } elseif ($act === 'restock') {                                           // UPDATE (quick restock)
        $qty = (int)($_POST['qty'] ?? 0);
        if ($qty < 1 || $qty > 100000) flash('error', 'Enter a restock quantity of at least 1.');
        else {
            $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?')->execute([$qty, $id]);
            $p = $pdo->prepare('SELECT name, branch_id, stock FROM products WHERE id = ?'); $p->execute([$id]); $p = $p->fetch();
            if ($p) { log_activity('restock', "Restocked {$p['name']} +$qty (now {$p['stock']})", (int)$p['branch_id']); flash('ok', "Restocked {$p['name']}: now {$p['stock']}."); }
        }
    } elseif ($act === 'toggle') {                                            // UPDATE (hide / show in the POS)
        $pdo->prepare('UPDATE products SET active = 1 - active WHERE id = ?')->execute([$id]);
        flash('ok', 'Product visibility changed.');
    } elseif ($act === 'delete') {                                            // DELETE
        try {
            $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            flash('ok', 'Product deleted.');
        } catch (PDOException $e) {
            flash('error', 'This product has sales history, so it cannot be deleted. Use Hide instead.');
        }
    }
    header('Location: ' . url('admin/products.php' . (!empty($_POST['back']) ? '?branch=' . (int)$_POST['back'] : '')));
    exit;
}

// READ
$filter = (int)($_GET['branch'] ?? 0);
$q      = trim((string)($_GET['q'] ?? ''));
$edit   = (int)($_GET['edit'] ?? 0);

$sql = 'SELECT p.*, b.name AS branch FROM products p JOIN branches b ON b.id = p.branch_id WHERE 1=1';
$args = [];
if ($filter) { $sql .= ' AND p.branch_id = ?'; $args[] = $filter; }
if ($q !== '') { $sql .= ' AND p.name LIKE ?'; $args[] = '%' . $q . '%'; }
$sql .= ' ORDER BY b.name, p.name LIMIT 500';
$st = $pdo->prepare($sql);
$st->execute($args);
$products = $st->fetchAll();

$editing = null;
if ($edit) { $st = $pdo->prepare('SELECT * FROM products WHERE id = ?'); $st->execute([$edit]); $editing = $st->fetch() ?: null; }

$page_title = 'Products';
$nav = 'products';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Products</h1></div>

<?php if (!$branches): ?>
  <div class="card">Add a branch first, then come back to add products. <a class="btn btn-primary" href="<?= e(url('admin/branches.php')) ?>">Go to Branches</a></div>
<?php else: ?>

<?php if ($editing): ?>
<section class="card">
  <h2>Edit product</h2>
  <form method="post" class="row-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><input type="hidden" name="back" value="<?= $filter ?>">
    <label>Name <input name="name" value="<?= e($editing['name']) ?>" required maxlength="120"></label>
    <label>Price <input name="price" type="number" step="0.01" min="0" value="<?= e($editing['price']) ?>" required></label>
    <label>Stock <input name="stock" type="number" min="0" value="<?= (int)$editing['stock'] ?>" required></label>
    <label>Low-stock alert at <input name="threshold" type="number" min="0" value="<?= (int)$editing['low_stock_threshold'] ?>" required></label>
    <button class="btn btn-primary">Save changes</button>
    <a class="btn btn-ghost" href="<?= e(url('admin/products.php' . ($filter ? '?branch=' . $filter : ''))) ?>">Cancel</a>
  </form>
</section>
<?php endif; ?>

<details class="card" <?= $products ? '' : 'open' ?>>
  <summary><strong>Add a product</strong></summary>
  <form method="post" class="row-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="create"><input type="hidden" name="back" value="<?= $filter ?>">
    <label>Branch
      <select name="branch_id" required>
        <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $filter === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
      </select>
    </label>
    <label>Name <input name="name" required maxlength="120"></label>
    <label>Price <input name="price" type="number" step="0.01" min="0" required></label>
    <label>Starting stock <input name="stock" type="number" min="0" value="0" required></label>
    <label>Low-stock alert at <input name="threshold" type="number" min="0" value="5" required></label>
    <button class="btn btn-primary">Add product</button>
  </form>
</details>

<form method="get" class="filters card">
  <label>Branch
    <select name="branch" onchange="this.form.submit()">
      <option value="0">All branches</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $filter === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Search <input type="search" name="q" value="<?= e($q) ?>" placeholder="Product name"></label>
  <button class="btn btn-ghost">Search</button>
</form>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>Product</th><th>Branch</th><th class="num">Price</th><th class="num">Stock</th><th>Status</th><th>Restock</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($products as $p):
      $out = (int)$p['stock'] <= 0; $low = !$out && (int)$p['stock'] <= (int)$p['low_stock_threshold']; ?>
    <tr class="<?= $p['active'] ? '' : 'is-off' ?>">
      <td><?= e($p['name']) ?><?= $p['active'] ? '' : ' <span class="badge">hidden</span>' ?></td>
      <td><?= e($p['branch']) ?></td>
      <td class="num"><?= money($p['price']) ?></td>
      <td class="num"><strong><?= (int)$p['stock'] ?></strong></td>
      <td><?= $out ? '<span class="badge badge-bad">Out of stock</span>' : ($low ? '<span class="badge badge-warn">Low</span>' : '<span class="badge badge-ok">OK</span>') ?></td>
      <td>
        <form method="post" class="inline-form">
          <?= csrf_field() ?><input type="hidden" name="action" value="restock"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= $filter ?>">
          <input name="qty" type="number" min="1" placeholder="+qty" aria-label="Restock quantity for <?= e($p['name']) ?>" required>
          <button class="btn btn-sm">Add</button>
        </form>
      </td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/products.php?edit=' . (int)$p['id'] . ($filter ? '&branch=' . $filter : ''))) ?>">Edit</a>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= $filter ?>">
          <button class="btn btn-ghost btn-sm"><?= $p['active'] ? 'Hide' : 'Show' ?></button></form>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete “<?= e(addslashes($p['name'])) ?>” permanently?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="back" value="<?= $filter ?>">
          <button class="btn btn-ghost btn-sm btn-danger">Delete</button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$products): ?><tr><td colspan="7" class="muted center">No products match.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
