<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = api_boot('vendor', true);
$bid = (int)$u['branch_id'];
$pdo = db();

$in = request_json();
$lines = [];
foreach ((array)($in['items'] ?? []) as $it) {
    $pid = (int)($it['product_id'] ?? 0);
    $qty = (int)($it['qty'] ?? 0);
    if ($pid <= 0 || $qty <= 0 || $qty > 9999) json_out(['ok' => false, 'error' => 'Invalid item in cart.'], 422);
    $lines[$pid] = ($lines[$pid] ?? 0) + $qty;
}
if (!$lines || count($lines) > 100) json_out(['ok' => false, 'error' => 'Cart is empty.'], 422);
ksort($lines); // lock rows in a fixed order to avoid deadlocks between two cashiers

try {
    $pdo->beginTransaction();

    $sel = $pdo->prepare('SELECT id, name, price, stock FROM products WHERE id = ? AND branch_id = ? AND active = 1 FOR UPDATE');
    $rows = [];
    $totalCents = 0;
    foreach ($lines as $pid => $qty) {
        $sel->execute([$pid, $bid]);
        $p = $sel->fetch();
        if (!$p) { $pdo->rollBack(); json_out(['ok' => false, 'error' => 'An item is no longer available.', 'refresh' => true], 409); }
        if ((int)$p['stock'] < $qty) {
            $pdo->rollBack();
            json_out(['ok' => false, 'error' => "Not enough stock for {$p['name']} ({$p['stock']} left).", 'refresh' => true], 409);
        }
        $cents = (int)round(((float)$p['price']) * 100) * $qty;
        $totalCents += $cents;
        $rows[] = [$p, $qty, $cents];
    }

    $pdo->prepare('INSERT INTO sales (branch_id, user_id, total) VALUES (?,?,?)')
        ->execute([$bid, $u['id'], number_format($totalCents / 100, 2, '.', '')]);
    $saleId = (int)$pdo->lastInsertId();

    $insItem = $pdo->prepare('INSERT INTO sale_items (sale_id, product_id, product_name, qty, unit_price, line_total) VALUES (?,?,?,?,?,?)');
    $dec     = $pdo->prepare('UPDATE products SET stock = stock - ? WHERE id = ?');
    $units = 0;
    foreach ($rows as [$p, $qty, $cents]) {
        $insItem->execute([$saleId, $p['id'], $p['name'], $qty, $p['price'], number_format($cents / 100, 2, '.', '')]);
        $dec->execute([$qty, $p['id']]);
        $units += $qty;
    }

    log_activity('sale', sprintf('Sale #%d · %s%s · %d item%s', $saleId, CURRENCY, number_format($totalCents / 100, 2), $units, $units === 1 ? '' : 's'));
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $e;
}

json_out(['ok' => true, 'sale_id' => $saleId, 'total' => $totalCents / 100]);
