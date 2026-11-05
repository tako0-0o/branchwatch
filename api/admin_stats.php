<?php
require __DIR__ . '/../includes/bootstrap.php';
api_boot('admin');

$pdo = db();
$online = (int)ONLINE_SECS;

$branches = $pdo->query("
  SELECT b.id, b.name,
    COALESCE((SELECT SUM(s.total)  FROM sales s    WHERE s.branch_id = b.id AND s.created_at >= CURDATE()), 0) AS income,
    (SELECT COUNT(*)               FROM sales s    WHERE s.branch_id = b.id AND s.created_at >= CURDATE())     AS sales_count,
    COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.branch_id = b.id AND e.created_at >= CURDATE()), 0) AS expenses,
    (SELECT COUNT(*)               FROM expenses e WHERE e.branch_id = b.id AND e.created_at >= CURDATE())     AS expense_count,
    (SELECT COUNT(*) FROM products p WHERE p.branch_id = b.id AND p.active = 1 AND p.stock > 0 AND p.stock <= p.low_stock_threshold) AS low_count,
    (SELECT COUNT(*) FROM products p WHERE p.branch_id = b.id AND p.active = 1 AND p.stock <= 0)  AS out_count,
    (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id AND u.role = 'vendor' AND u.active = 1) AS vendors,
    (SELECT COUNT(*) FROM users u WHERE u.branch_id = b.id AND u.role = 'vendor' AND u.active = 1
        AND u.last_seen >= NOW() - INTERVAL $online SECOND) AS online,
    (SELECT TIMESTAMPDIFF(SECOND, MAX(s.created_at), NOW()) FROM sales s WHERE s.branch_id = b.id) AS last_sale_ago
  FROM branches b WHERE b.active = 1 ORDER BY b.name
")->fetchAll();

foreach ($branches as &$b) {
    foreach (['id','sales_count','expense_count','low_count','out_count','vendors','online'] as $k) $b[$k] = (int)$b[$k];
    $b['income']   = (float)$b['income'];
    $b['expenses'] = (float)$b['expenses'];
    $b['last_sale_ago'] = $b['last_sale_ago'] === null ? null : (int)$b['last_sale_ago'];
}
unset($b);

$low = $pdo->query("
  SELECT p.id, p.name, p.stock, p.low_stock_threshold AS threshold, b.id AS branch_id, b.name AS branch
    FROM products p JOIN branches b ON b.id = p.branch_id
   WHERE p.active = 1 AND b.active = 1 AND p.stock <= p.low_stock_threshold
   ORDER BY p.stock ASC, b.name, p.name
   LIMIT 25
")->fetchAll();
foreach ($low as &$r) { $r['stock'] = (int)$r['stock']; $r['threshold'] = (int)$r['threshold']; $r['branch_id'] = (int)$r['branch_id']; }
unset($r);

$activity = $pdo->query("
  SELECT a.action, a.detail, b.name AS branch, u.name AS user,
         TIMESTAMPDIFF(SECOND, a.created_at, NOW()) AS ago
    FROM activity_log a
    LEFT JOIN branches b ON b.id = a.branch_id
    LEFT JOIN users u    ON u.id = a.user_id
   ORDER BY a.created_at DESC, a.id DESC LIMIT 20
")->fetchAll();
foreach ($activity as &$r) { $r['ago'] = (int)$r['ago']; }
unset($r);

// last 7 days, all branches 
$inc = $pdo->query("SELECT DATE(created_at) d, SUM(total)  v FROM sales    WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
$exp = $pdo->query("SELECT DATE(created_at) d, SUM(amount) v FROM expenses WHERE created_at >= CURDATE() - INTERVAL 6 DAY GROUP BY d")->fetchAll(PDO::FETCH_KEY_PAIR);
$series = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $series[] = ['date' => $d, 'label' => date('D', strtotime($d)), 'income' => (float)($inc[$d] ?? 0), 'expenses' => (float)($exp[$d] ?? 0)];
}

$totals = ['income' => 0, 'expenses' => 0, 'sales' => 0, 'low' => 0, 'out' => 0, 'online' => 0, 'vendors' => 0];
foreach ($branches as $b) {
    $totals['income']   += $b['income'];
    $totals['expenses'] += $b['expenses'];
    $totals['sales']    += $b['sales_count'];
    $totals['low']      += $b['low_count'];
    $totals['out']      += $b['out_count'];
    $totals['online']   += $b['online'];
    $totals['vendors']  += $b['vendors'];
}

json_out([
    'ok'       => true,
    'now'      => date('g:i:s A'),
    'today'    => date('l, F j'),
    'totals'   => $totals,
    'branches' => $branches,
    'low'      => $low,
    'activity' => $activity,
    'series'   => $series,
]);