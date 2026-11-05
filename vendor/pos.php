<?php
require __DIR__ . '/../includes/bootstrap.php';
$user = require_login('vendor');

$page_title = 'POS';
$nav = 'pos';
$body_class = 'pos-page';
$scripts = ['pos.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1><?= e($user['branch_name']) ?></h1>
    <p class="muted">Signed in as <?= e($user['name']) ?></p>
  </div>
  <div class="live" id="live" aria-live="polite"><span class="dot"></span><span id="live-text">Connecting…</span></div>
</div>

<div id="pos-error" class="flash flash-error" role="alert" hidden></div>
<div id="pos-ok" class="flash flash-ok" role="status" hidden></div>

<div class="pos">
  <section class="pos-products card">
    <div class="pos-search"><input type="search" id="search" placeholder="Search products…" aria-label="Search products" autocomplete="off"></div>
    <div id="grid" class="grid"><p class="muted">Loading products…</p></div>
  </section>

  <aside class="pos-side">
    <section class="card cart">
      <h2>Current sale</h2>
      <ul id="cart" class="cart-list"><li class="muted">Tap a product to add it.</li></ul>
      <div class="cart-total"><span>Total</span><strong id="total">₱0.00</strong></div>
      <label class="cash">Cash received (optional, for change)
        <input id="cash" type="number" inputmode="decimal" min="0" step="0.01" placeholder="0.00">
      </label>
      <div class="cart-total change"><span>Change</span><strong id="change">–</strong></div>
      <div class="cart-actions">
        <button class="btn btn-ghost" id="clear" type="button">Clear</button>
        <button class="btn btn-primary btn-lg" id="checkout" type="button" disabled>Complete sale</button>
      </div>
    </section>

    <section class="card today">
      <h2>Today at this branch</h2>
      <div class="mini-kpis">
        <div><span>Sales</span><strong id="t-sales">–</strong></div>
        <div><span>Sales total</span><strong id="t-total">–</strong></div>
        <div><span>Expenses</span><strong id="t-exp">–</strong></div>
      </div>
      <h3>Recent sales</h3>
      <ul class="list" id="recent"><li class="muted">–</li></ul>
    </section>

    <section class="card">
      <h2>Record an expense</h2>
      <p class="muted small">Utilities, restock payments, supplies… anything the branch paid for.</p>
      <form id="expense-form" class="stack">
        <label>What for
          <select id="e-cat" required></select>
        </label>
        <label>Amount <input id="e-amt" type="number" inputmode="decimal" min="0.01" step="0.01" required></label>
        <label>Note (optional) <input id="e-desc" maxlength="200" placeholder="e.g. electric bill, supplier name, week 1"></label>
        <button class="btn btn-primary" type="submit" id="e-btn">Save</button>
      </form>
      <h3>Expenses today</h3>
      <ul class="list" id="exp-list"><li class="muted">–</li></ul>
    </section>
  </aside>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
