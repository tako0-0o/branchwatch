
(function () {
  const BW = window.BW, $ = id => document.getElementById(id);
  let products = [];
  const cart = new Map(); // product_id -> qty
  let busy = false;

  const byId = id => products.find(p => p.id === id);
  const stockOf = p => p.stock;

  function showError(msg) {
    const el = $('pos-error'); el.textContent = msg; el.hidden = !msg;
    if (msg) setTimeout(() => { if (el.textContent === msg) el.hidden = true; }, 8000);
  }
  function showOk(msg) {
    const el = $('pos-ok'); el.textContent = msg; el.hidden = !msg;
    if (msg) setTimeout(() => { if (el.textContent === msg) el.hidden = true; }, 6000);
  }

  // products
  function renderGrid() {
    const q = $('search').value.trim().toLowerCase();
    const list = products.filter(p => !q || p.name.toLowerCase().includes(q));
    if (!products.length) { $('grid').innerHTML = '<p class="muted">No products yet. Ask the owner to add stock for this branch.</p>'; return; }
    if (!list.length) { $('grid').innerHTML = '<p class="muted">No product matches “' + BW.esc(q) + '”.</p>'; return; }
    $('grid').innerHTML = list.map(p => {
      const inCart = cart.get(p.id) || 0;
      const left = p.stock - inCart;
      const out = p.stock <= 0;
      const low = !out && p.stock <= p.threshold;
      return '<button type="button" class="tile' + (out ? ' is-out' : '') + '" data-id="' + p.id + '"' + (out || left <= 0 ? ' disabled' : '') + '>' +
        '<span class="tile-name">' + BW.esc(p.name) + '</span>' +
        '<span class="tile-price">' + BW.money(p.price) + '</span>' +
        '<span class="tile-stock' + (out ? ' bad' : low ? ' warn' : '') + '">' + (out ? 'Out of stock' : left + ' left') + '</span>' +
        (inCart ? '<span class="tile-qty">' + inCart + '</span>' : '') +
        '</button>';
    }).join('');
  }

  // cart
  function total() {
    let t = 0;
    cart.forEach((q, id) => { const p = byId(id); if (p) t += Math.round(p.price * 100) * q; });
    return t / 100;
  }

  function renderCart() {
    const rows = [];
    cart.forEach((q, id) => {
      const p = byId(id); if (!p) return;
      rows.push('<li><div class="ci-name">' + BW.esc(p.name) + '<small>' + BW.money(p.price) + ' each</small></div>' +
        '<div class="qty"><button type="button" data-act="dec" data-id="' + id + '" aria-label="Fewer">−</button>' +
        '<span>' + q + '</span>' +
        '<button type="button" data-act="inc" data-id="' + id + '" aria-label="More"' + (q >= p.stock ? ' disabled' : '') + '>+</button></div>' +
        '<strong class="ci-total">' + BW.money(Math.round(p.price * 100) * q / 100) + '</strong></li>');
    });
    $('cart').innerHTML = rows.length ? rows.join('') : '<li class="muted">Tap a product to add it.</li>';
    $('total').textContent = BW.money(total());
    $('checkout').disabled = !rows.length || busy;
    updateChange();
    renderGrid();
  }

  function updateChange() {
    const t = total(), cash = parseFloat($('cash').value);
    const el = $('change');
    if (!cart.size || isNaN(cash)) { el.textContent = '–'; el.classList.remove('neg'); return; }
    const c = Math.round((cash - t) * 100) / 100;
    el.textContent = c >= 0 ? BW.money(c) : 'Short ' + BW.money(-c);
    el.classList.toggle('neg', c < 0);
  }

  function add(id, d) {
    const p = byId(id); if (!p) return;
    const q = (cart.get(id) || 0) + d;
    if (q <= 0) cart.delete(id);
    else if (q <= p.stock) cart.set(id, q);
    renderCart();
  }

  $('grid').addEventListener('click', e => {
    const b = e.target.closest('.tile'); if (b) add(+b.dataset.id, 1);
  });
  $('cart').addEventListener('click', e => {
    const b = e.target.closest('button[data-act]'); if (!b) return;
    add(+b.dataset.id, b.dataset.act === 'inc' ? 1 : -1);
  });
  $('clear').addEventListener('click', () => { cart.clear(); $('cash').value = ''; renderCart(); });
  $('cash').addEventListener('input', updateChange);
  $('search').addEventListener('input', renderGrid);

  $('checkout').addEventListener('click', async () => {
    if (busy || !cart.size) return;
    const t = total(), cash = parseFloat($('cash').value);
    if (!isNaN(cash) && cash < t) { showError('Cash received is less than the total.'); return; }
    busy = true; $('checkout').disabled = true; $('checkout').textContent = 'Saving…'; showError(''); $('pos-ok').hidden = true;
    try {
      const items = [...cart].map(([product_id, qty]) => ({ product_id, qty }));
      const r = await BW.api('/api/sale.php', { items });
      const change = !isNaN(cash) ? ' · Change ' + BW.money(cash - r.total) : '';
      showOk('Sale #' + r.sale_id + ' saved · ' + BW.money(r.total) + change);
      cart.clear(); $('cash').value = '';
    } catch (e) {
      showError(e.network ? 'No connection. The sale was NOT saved. Check the internet and try again.' : e.message);
    } finally {
      busy = false; $('checkout').textContent = 'Complete sale';
      await refresh(true);
    }
  });

  // todays panel
  function renderToday(d) {
    $('t-sales').textContent = d.summary.sales_count;
    $('t-total').textContent = BW.money(d.summary.sales_total);
    $('t-exp').textContent = BW.money(d.summary.expenses_total);
    $('recent').innerHTML = d.recent.length
      ? d.recent.map(r => '<li><span>#' + r.id + ' · ' + BW.esc(r.time) + ' · ' + r.items + ' item' + (+r.items === 1 ? '' : 's') + '</span><strong>' + BW.money(r.total) + '</strong></li>').join('')
      : '<li class="muted">No sales yet today.</li>';
    $('exp-list').innerHTML = d.expenses.length
      ? d.expenses.map(r => '<li><span>' + BW.esc(r.time) + ' · ' + BW.esc(r.category) + (r.description ? ' · ' + BW.esc(r.description) : '') + '</span><strong>' + BW.money(r.amount) + '</strong></li>').join('')
      : '<li class="muted">No expenses yet today.</li>';
  }

  async function refresh(quiet) {
    try {
      const d = await BW.api('/api/pos_data.php');
      products = d.products;
      // drop cart lines that no longer fit the stock
      cart.forEach((q, id) => { const p = byId(id); if (!p || p.stock <= 0) cart.delete(id); else if (q > p.stock) cart.set(id, p.stock); });
      renderToday(d);
      renderCart();
      BW.setLive(true, 'Online · ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }));
    } catch (e) {
      BW.setLive(false, e.network ? 'Offline · sales cannot be saved' : 'Problem loading');
    }
  }

  // expense
  $('e-cat').innerHTML = BW.categories.map(c => '<option>' + BW.esc(c) + '</option>').join('');
  $('expense-form').addEventListener('submit', async ev => {
    ev.preventDefault();
    const btn = $('e-btn'); btn.disabled = true; showError('');
    try {
      await BW.api('/api/expense.php', { category: $('e-cat').value, amount: $('e-amt').value, description: $('e-desc').value });
      showOk('Expense saved.');
      $('e-amt').value = ''; $('e-desc').value = '';
      await refresh(true);
    } catch (e) {
      showError(e.network ? 'No connection. The expense was NOT saved.' : e.message);
    } finally { btn.disabled = false; }
  });

  refresh();
  setInterval(() => { if (!document.hidden && !busy) refresh(true); }, 30000); // also keeps the vendor shown as online
  document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(true); });
})();
