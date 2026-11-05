
(function () {
  const BW = window.BW, $ = id => document.getElementById(id);
  let failures = 0;

  const ACTION_ICON = { sale: '🧾', expense: '💸', login: '🔓', logout: '🔒', restock: '📦', product: '🏷️', branch: '🏬', vendor: '👤', password: '🔑' };

  function setK(name, text, cls) {
    const el = document.querySelector('[data-k="' + name + '"]');
    if (!el) return;
    el.textContent = text;
    if (cls !== undefined) el.classList.toggle('neg', cls);
  }

  function renderKpis(t) {
    setK('income', BW.money(t.income));
    setK('sales', t.sales + ' sale' + (t.sales === 1 ? '' : 's'));
    setK('expenses', BW.money(t.expenses));
    const net = t.income - t.expenses;
    setK('net', BW.money(net), net < 0);
    setK('stock', String(t.low + t.out), t.out > 0);
    setK('stocksub', t.out + ' out · ' + t.low + ' low');
    setK('online', t.online + ' / ' + t.vendors);
  }

  function renderAlerts(d) {
    const out = [];
    d.branches.forEach(b => {
      if (b.out_count > 0) out.push(['bad', b.name + ': ' + b.out_count + ' item' + (b.out_count === 1 ? '' : 's') + ' out of stock.']);
      if (b.vendors > 0 && b.online === 0) out.push(['info', b.name + ': no vendor is signed in right now.']);
      if (b.expenses > b.income && b.expenses > 0) out.push(['warn', b.name + ': expenses (' + BW.money(b.expenses) + ') are higher than income today.']);
    });
    $('alerts').innerHTML = out.slice(0, 6).map(a => '<div class="alert alert-' + a[0] + '">' + BW.esc(a[1]) + '</div>').join('');
  }

  function renderBranches(list) {
    if (!list.length) { $('branches').innerHTML = '<p class="muted">No active branches yet. Add one under Branches.</p>'; return; }
    $('branches').innerHTML = list.map(b => {
      const net = b.income - b.expenses;
      const stock = b.out_count ? '<span class="badge badge-bad">' + b.out_count + ' out</span>' : '';
      const low = b.low_count ? '<span class="badge badge-warn">' + b.low_count + ' low</span>' : '';
      const ok = (!b.out_count && !b.low_count) ? '<span class="badge badge-ok">Stock OK</span>' : '';
      const presence = b.online > 0
        ? '<span class="pres on"><i></i>' + b.online + ' online</span>'
        : '<span class="pres"><i></i>' + (b.vendors ? 'No one online' : 'No vendors') + '</span>';
      return '<article class="branch">' +
        '<header><h3>' + BW.esc(b.name) + '</h3>' + presence + '</header>' +
        '<dl>' +
          '<div><dt>Income</dt><dd>' + BW.money(b.income) + '</dd></div>' +
          '<div><dt>Expenses</dt><dd>' + BW.money(b.expenses) + '</dd></div>' +
          '<div><dt>Net</dt><dd class="' + (net < 0 ? 'neg' : '') + '">' + BW.money(net) + '</dd></div>' +
        '</dl>' +
        '<p class="branch-meta">' + b.sales_count + ' sale' + (b.sales_count === 1 ? '' : 's') + ' · ' + b.expense_count + ' expense' + (b.expense_count === 1 ? '' : 's') +
          ' · last entry ' + BW.ago(b.last_sale_ago) + '</p>' +
        '<footer>' + stock + low + ok +
          '<span class="spacer"></span>' +
          '<a href="' + BW.base + '/admin/products.php?branch=' + b.id + '">Stock</a>' +
          '<a href="' + BW.base + '/admin/expenses.php?branch=' + b.id + '">Expenses</a>' +
        '</footer></article>';
    }).join('');
  }

  function renderLow(rows) {
    if (!rows.length) { $('low').innerHTML = '<p class="empty">Everything is stocked. 🎉</p>'; return; }
    $('low').innerHTML = '<table class="table compact"><thead><tr><th>Product</th><th>Branch</th><th class="num">Left</th></tr></thead><tbody>' +
      rows.map(r => '<tr><td>' + BW.esc(r.name) + '</td><td>' + BW.esc(r.branch) + '</td><td class="num">' +
        (r.stock <= 0 ? '<span class="badge badge-bad">0</span>' : '<span class="badge badge-warn">' + r.stock + '</span>') + '</td></tr>').join('') +
      '</tbody></table><p class="small"><a href="' + BW.base + '/admin/products.php">Open products →</a></p>';
  }

  function renderFeed(rows) {
    if (!rows.length) { $('feed').innerHTML = '<li class="muted">Nothing has happened yet.</li>'; return; }
    $('feed').innerHTML = rows.map(r =>
      '<li><span class="ic" aria-hidden="true">' + (ACTION_ICON[r.action] || '•') + '</span>' +
      '<div><strong>' + BW.esc(r.detail) + '</strong><small>' + BW.esc([r.branch, r.user].filter(Boolean).join(' · ')) + '</small></div>' +
      '<time>' + BW.ago(r.ago) + '</time></li>').join('');
  }

  // Small dependency-free bar chart (SVG)
  function renderChart(series) {
    const W = 560, H = 220, L = 48, R = 8, T = 10, B = 28;
    const max = Math.max(1, ...series.map(s => Math.max(s.income, s.expenses)));
    const nice = niceMax(max);
    const pw = W - L - R, ph = H - T - B;
    const gw = pw / series.length, bw = Math.min(22, gw / 2 - 4);
    let g = '';
    for (let i = 0; i <= 4; i++) {
      const v = nice * i / 4, y = T + ph - ph * i / 4;
      g += '<line x1="' + L + '" x2="' + (W - R) + '" y1="' + y + '" y2="' + y + '" class="grid"/>' +
           '<text x="' + (L - 6) + '" y="' + (y + 4) + '" text-anchor="end" class="axis">' + short(v) + '</text>';
    }
    series.forEach((s, i) => {
      const cx = L + gw * i + gw / 2;
      const hi = ph * s.income / nice, he = ph * s.expenses / nice;
      g += '<rect class="bar-income" x="' + (cx - bw - 1) + '" y="' + (T + ph - hi) + '" width="' + bw + '" height="' + hi + '" rx="3"><title>' + BW.esc(s.label) + ' income ' + BW.money(s.income) + '</title></rect>' +
           '<rect class="bar-exp" x="' + (cx + 1) + '" y="' + (T + ph - he) + '" width="' + bw + '" height="' + he + '" rx="3"><title>' + BW.esc(s.label) + ' expenses ' + BW.money(s.expenses) + '</title></rect>' +
           '<text x="' + cx + '" y="' + (H - 8) + '" text-anchor="middle" class="axis">' + BW.esc(s.label) + '</text>';
    });
    $('chart').innerHTML = '<svg viewBox="0 0 ' + W + ' ' + H + '" preserveAspectRatio="xMidYMid meet">' + g + '</svg>';
  }
  function niceMax(v) {
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const n = v / p;
    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p;
  }
  function short(v) {
    if (v >= 1e6) return (v / 1e6).toFixed(v % 1e6 ? 1 : 0) + 'M';
    if (v >= 1e3) return (v / 1e3).toFixed(v % 1e3 ? 1 : 0) + 'k';
    return String(Math.round(v));
  }

  async function load() {
    try {
      const d = await BW.api('/api/admin_stats.php');
      failures = 0;
      $('today').textContent = d.today;
      renderKpis(d.totals);
      renderAlerts(d);
      renderBranches(d.branches);
      renderLow(d.low);
      renderFeed(d.activity);
      renderChart(d.series);
      BW.setLive(true, 'Live · updated ' + d.now);
    } catch (e) {
      failures++;
      BW.setLive(false, e.network ? 'Connection lost · retrying…' : 'Problem loading · retrying…');
    }
  }

  function loop() {
    load().finally(() => {
      const base = document.hidden ? 30000 : BW.pollMs;
      setTimeout(loop, Math.min(base * Math.max(1, failures), 60000));
    });
  }
  document.addEventListener('visibilitychange', () => { if (!document.hidden) load(); });
  loop();
})();
