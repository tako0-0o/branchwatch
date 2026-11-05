<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login('admin');
$pdo = db();

$branches = $pdo->query('SELECT id, name FROM branches ORDER BY name')->fetchAll();
$branchIds = array_map('intval', array_column($branches, 'id'));

if (is_post()) {
    check_csrf_or_bounce();
    $act  = $_POST['action'] ?? '';
    $id   = (int)($_POST['id'] ?? 0);
    $cat  = (string)($_POST['category'] ?? '');
    $desc = trim((string)($_POST['description'] ?? ''));
    $amt  = $_POST['amount'] ?? '';
    $bid  = (int)($_POST['branch_id'] ?? 0);
    $date = valid_date($_POST['date'] ?? '') ?? date('Y-m-d');

    if ($act === 'create' || $act === 'update') {
        if (!in_array($cat, EXPENSE_CATEGORIES, true))                              flash('error', 'Pick a category.');
        elseif (!is_numeric($amt) || (float)$amt <= 0 || (float)$amt > 9999999.99)   flash('error', 'Enter an amount greater than zero.');
        elseif (mb_strlen($desc) > 255)                                             flash('error', 'The note is too long.');
        elseif ($date > date('Y-m-d'))                                              flash('error', 'The date cannot be in the future.');
        elseif ($act === 'create' && !in_array($bid, $branchIds, true))             flash('error', 'Pick a branch.');
        else {
            $amt = number_format((float)$amt, 2, '.', '');
            $when = $date . ' ' . ($date === date('Y-m-d') ? date('H:i:s') : '12:00:00');
            if ($act === 'create') {                                                // CREATE
                $pdo->prepare('INSERT INTO expenses (branch_id, user_id, category, description, amount, created_at) VALUES (?,?,?,?,?,?)')
                    ->execute([$bid, $user['id'], $cat, $desc, $amt, $when]);
                log_activity('expense', "Admin added: $cat · " . money($amt) . ($desc !== '' ? " · $desc" : ''), $bid);
                flash('ok', 'Expense added.');
            } else {                                                                // UPDATE
                $pdo->prepare('UPDATE expenses SET category = ?, description = ?, amount = ?, created_at = ? WHERE id = ?')->execute([$cat, $desc, $amt, $when, $id]);
                log_activity('expense', "Admin edited an expense: $cat · " . money($amt));
                flash('ok', 'Expense updated.');
            }
        }
    } elseif ($act === 'delete') {                                                  // DELETE 
        $st = $pdo->prepare('SELECT branch_id, category, amount FROM expenses WHERE id = ?');
        $st->execute([$id]);
        if ($old = $st->fetch()) {
            $pdo->prepare('DELETE FROM expenses WHERE id = ?')->execute([$id]);
            log_activity('expense', 'Admin deleted an expense: ' . $old['category'] . ' · ' . money($old['amount']), (int)$old['branch_id']);
            flash('ok', 'Expense deleted.');
        }
    }
    header('Location: ' . url('admin/expenses.php?' . http_build_query(array_filter(['branch' => $_POST['back_branch'] ?? '', 'from' => $_POST['back_from'] ?? '', 'to' => $_POST['back_to'] ?? '']))));
    exit;
}

// READ (with filters)
$fb   = (int)($_GET['branch'] ?? 0);
$cat  = (string)($_GET['category'] ?? '');
$from = valid_date($_GET['from'] ?? '') ?? date('Y-m-d');
$to   = valid_date($_GET['to'] ?? '') ?? date('Y-m-d');
if ($to < $from) $to = $from;
$edit = (int)($_GET['edit'] ?? 0);

$where = 'e.created_at >= ? AND e.created_at < ? + INTERVAL 1 DAY';
$args = [$from, $to];
if ($fb) { $where .= ' AND e.branch_id = ?'; $args[] = $fb; }
if (in_array($cat, EXPENSE_CATEGORIES, true)) { $where .= ' AND e.category = ?'; $args[] = $cat; } else { $cat = ''; }

$st = $pdo->prepare("SELECT COALESCE(SUM(e.amount),0) total, COUNT(*) n FROM expenses e WHERE $where");
$st->execute($args); $sum = $st->fetch();

$st = $pdo->prepare("SELECT e.*, b.name AS branch, u.name AS vendor, DATE_FORMAT(e.created_at, '%b %e, %l:%i %p') AS at
                       FROM expenses e JOIN branches b ON b.id = e.branch_id JOIN users u ON u.id = e.user_id
                      WHERE $where ORDER BY e.id DESC LIMIT 300");
$st->execute($args); $rows = $st->fetchAll();

$back = '<input type="hidden" name="back_branch" value="' . $fb . '"><input type="hidden" name="back_from" value="' . e($from) . '"><input type="hidden" name="back_to" value="' . e($to) . '">';

$page_title = 'Expenses';
$nav = 'expenses';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><h1>Expenses</h1></div>

<details class="card" open>
  <summary><strong>Add an expense</strong></summary>
  <form method="post" class="row-form">
    <?= csrf_field() ?><input type="hidden" name="action" value="create"><?= $back ?>
    <label>Branch <select name="branch_id" required><?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fb === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?></select></label>
    <label>Date <input type="date" name="date" value="<?= e(date('Y-m-d')) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
    <label>Category <select name="category" required><?php foreach (EXPENSE_CATEGORIES as $c): ?><option><?= e($c) ?></option><?php endforeach; ?></select></label>
    <label>Amount <input name="amount" type="number" step="0.01" min="0.01" required></label>
    <label>Note <input name="description" maxlength="255"></label>
    <button class="btn btn-primary">Add</button>
  </form>
</details>

<form method="get" class="filters card">
  <label>Branch
    <select name="branch"><option value="0">All branches</option>
      <?php foreach ($branches as $b): ?><option value="<?= (int)$b['id'] ?>" <?= $fb === (int)$b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>Category
    <select name="category"><option value="">All</option>
      <?php foreach (EXPENSE_CATEGORIES as $c): ?><option <?= $cat === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label>From <input type="date" name="from" value="<?= e($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= e($to) ?>"></label>
  <button class="btn btn-primary">Apply</button>
</form>

<section class="kpis kpis-3">
  <div class="kpi"><span class="kpi-label">Total expenses</span><strong class="kpi-value"><?= money($sum['total']) ?></strong><span class="kpi-sub"><?= (int)$sum['n'] ?> entr<?= (int)$sum['n'] === 1 ? 'y' : 'ies' ?> in this period</span></div>
</section>

<div class="table-wrap">
<table class="table">
  <thead><tr><th>When</th><th>Branch</th><th>Category</th><th>Details</th><th>Recorded by</th><th class="num">Amount</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <?php if ($edit === (int)$r['id']): ?>
    <tr><td colspan="7">
      <form method="post" class="row-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?= $back ?>
        <label>Date <input type="date" name="date" value="<?= e(substr($r['created_at'], 0, 10)) ?>" max="<?= e(date('Y-m-d')) ?>" required></label>
        <label>Category <select name="category"><?php foreach (EXPENSE_CATEGORIES as $c): ?><option <?= $r['category'] === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?></select></label>
        <label>Amount <input name="amount" type="number" step="0.01" min="0.01" value="<?= e($r['amount']) ?>" required></label>
        <label>Note <input name="description" value="<?= e($r['description']) ?>" maxlength="255"></label>
        <button class="btn btn-primary">Save</button>
        <a class="btn btn-ghost" href="<?= e(url('admin/expenses.php?' . http_build_query(['branch' => $fb, 'from' => $from, 'to' => $to]))) ?>">Cancel</a>
      </form>
    </td></tr>
    <?php else: ?>
    <tr>
      <td><?= e($r['at']) ?></td><td><?= e($r['branch']) ?></td><td><span class="badge"><?= e($r['category']) ?></span></td>
      <td><?= e($r['description']) ?></td><td><?= e($r['vendor']) ?></td><td class="num"><?= money($r['amount']) ?></td>
      <td class="actions">
        <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/expenses.php?' . http_build_query(['edit' => $r['id'], 'branch' => $fb, 'from' => $from, 'to' => $to]))) ?>">Edit</a>
        <form method="post" class="inline-form" onsubmit="return confirm('Delete this expense?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><?= $back ?>
          <button class="btn btn-ghost btn-sm btn-danger">Delete</button></form>
      </td>
    </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="7" class="muted center">No expenses in this period.</td></tr><?php endif; ?>
  </tbody>
</table>
</div>
<?php if (count($rows) === 300): ?><p class="muted">Showing the latest 300 entries. Narrow the dates to see others.</p><?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
