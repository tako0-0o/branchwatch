<?php
require __DIR__ . '/../includes/bootstrap.php';
require_login('admin');

$page_title = 'Dashboard';
$nav = 'dashboard';
$scripts = ['dashboard.js'];
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
  <div>
    <h1>All branches</h1>
    <p class="muted" id="today">Today</p>
  </div>
  <div class="live" id="live" aria-live="polite"><span class="dot"></span><span id="live-text">Connecting…</span></div>
</div>

<div id="alerts" class="alerts"></div>

<section class="kpis" id="kpis" aria-label="Today across all branches">
  <div class="kpi"><span class="kpi-label">Income today</span><strong class="kpi-value" data-k="income">–</strong><span class="kpi-sub" data-k="sales">&nbsp;</span></div>
  <div class="kpi"><span class="kpi-label">Expenses today</span><strong class="kpi-value" data-k="expenses">–</strong><span class="kpi-sub">&nbsp;</span></div>
  <div class="kpi"><span class="kpi-label">Net today</span><strong class="kpi-value" data-k="net">–</strong><span class="kpi-sub">income − expenses</span></div>
  <div class="kpi"><span class="kpi-label">Stock problems</span><strong class="kpi-value" data-k="stock">–</strong><span class="kpi-sub" data-k="stocksub">&nbsp;</span></div>
  <div class="kpi"><span class="kpi-label">Vendors online</span><strong class="kpi-value" data-k="online">–</strong><span class="kpi-sub">seen in last 2 min</span></div>
</section>

<h2>Branches</h2>
<section class="branch-grid" id="branches"><p class="muted">Loading…</p></section>

<div class="two-col">
  <section class="card">
    <h2>Last 7 days <span class="legend"><i class="sw sw-income"></i>Income <i class="sw sw-exp"></i>Expenses</span></h2>
    <div id="chart" class="chart" role="img" aria-label="Income and expenses for the last 7 days"></div>
  </section>
  <section class="card">
    <h2>Running low</h2>
    <div id="low"><p class="muted">Loading…</p></div>
  </section>
</div>

<section class="card">
  <h2>Live activity</h2>
  <ul class="feed" id="feed"><li class="muted">Loading…</li></ul>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
