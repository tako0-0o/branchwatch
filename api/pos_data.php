<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = api_boot('vendor');
$bid = (int)$u['branch_id'];
$pdo = db();

$st = $pdo->prepare('SELECT id, name, price, stock, low_stock_threshold AS threshold FROM products WHERE branch_id = ? AND active = 1 ORDER BY name');
$st->execute([$bid]);
$products = $st->fetchAll();
foreach ($products as &$p) { $p['id'] = (int)$p['id']; $p['price'] = (float)$p['price']; $p['stock'] = (int)$p['stock']; $p['threshold'] = (int)$p['threshold']; }
unset($p);

$st = $pdo->prepare('SELECT COUNT(*) c, COALESCE(SUM(total),0) t FROM sales WHERE branch_id = ? AND created_at >= CURDATE()');
$st->execute([$bid]);
$s = $st->fetch();
$st = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM expenses WHERE branch_id = ? AND created_at >= CURDATE()');
$st->execute([$bid]);
$expToday = (float)$st->fetchColumn();

$st = $pdo->prepare("
  SELECT s.id, s.total, DATE_FORMAT(s.created_at, '%l:%i %p') AS time,
         (SELECT COALESCE(SUM(qty),0) FROM sale_items i WHERE i.sale_id = s.id) AS items
    FROM sales s WHERE s.branch_id = ? ORDER BY s.id DESC LIMIT 8");
$st->execute([$bid]);
$recent = $st->fetchAll();

$st = $pdo->prepare("
  SELECT category, description, amount, DATE_FORMAT(created_at, '%l:%i %p') AS time
    FROM expenses WHERE branch_id = ? AND created_at >= CURDATE() ORDER BY id DESC LIMIT 8");
$st->execute([$bid]);
$exps = $st->fetchAll();

json_out([
    'ok'       => true,
    'branch'   => $u['branch_name'],
    'products' => $products,
    'summary'  => ['sales_count' => (int)$s['c'], 'sales_total' => (float)$s['t'], 'expenses_total' => $expToday],
    'recent'   => $recent,
    'expenses' => $exps,
]);
