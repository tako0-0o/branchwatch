<?php
require __DIR__ . '/../includes/bootstrap.php';
$u = api_boot('vendor', true);
$bid = (int)$u['branch_id'];
$pdo = db();

$in = request_json();
$cat    = (string)($in['category'] ?? '');
$desc   = trim((string)($in['description'] ?? ''));
$amount = $in['amount'] ?? null;

if (!in_array($cat, EXPENSE_CATEGORIES, true)) json_out(['ok' => false, 'error' => 'Pick a category.'], 422);
if (!is_numeric($amount) || (float)$amount <= 0 || (float)$amount > 9999999.99) {
    json_out(['ok' => false, 'error' => 'Enter an amount greater than zero.'], 422);
}
if (mb_strlen($desc) > 200) json_out(['ok' => false, 'error' => 'The note is too long.'], 422);
$amount = number_format((float)$amount, 2, '.', '');

$pdo->prepare('INSERT INTO expenses (branch_id, user_id, category, description, amount) VALUES (?,?,?,?,?)')
    ->execute([$bid, $u['id'], $cat, $desc, $amount]);
log_activity('expense', $cat . ' · ' . money($amount) . ($desc !== '' ? ' · ' . $desc : ''));
json_out(['ok' => true]);
