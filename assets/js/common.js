// Shared helpers for the dashboard and the POS.
(function () {
  const BW = window.BW;

  BW.esc = function (s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  };

  BW.money = function (n) {
    n = Number(n || 0);
    return (n < 0 ? '-' : '') + BW.currency + Math.abs(n).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  };

  BW.ago = function (sec) {
    if (sec == null) return 'never';
    if (sec < 10) return 'just now';
    if (sec < 60) return sec + 's ago';
    if (sec < 3600) return Math.floor(sec / 60) + ' min ago';
    if (sec < 86400) return Math.floor(sec / 3600) + ' h ago';
    return Math.floor(sec / 86400) + ' d ago';
  };

  // fetch wrapper: JSON in/out, CSRF header, sends the user to login if the session ended
  BW.api = async function (path, body) {
    const opt = { credentials: 'same-origin', headers: { 'Accept': 'application/json' } };
    if (body !== undefined) {
      opt.method = 'POST';
      opt.headers['Content-Type'] = 'application/json';
      opt.headers['X-CSRF-Token'] = BW.csrf;
      opt.body = JSON.stringify(body);
    }
    let res;
    try {
      res = await fetch(BW.base + path, opt);
    } catch (e) {
      const err = new Error('No connection to the server.');
      err.network = true;
      throw err;
    }
    let data = null;
    try { data = await res.json(); } catch (e) { /* not JSON */ }
    if (res.status === 401) { location.href = BW.base + '/login.php'; throw new Error('Signed out'); }
    if (!res.ok || !data || data.ok === false) {
      const err = new Error((data && data.error) || 'Server error (' + res.status + ').');
      err.data = data;
      throw err;
    }
    return data;
  };

  BW.setLive = function (ok, text) {
    const el = document.getElementById('live');
    if (!el) return;
    el.classList.toggle('is-down', !ok);
    document.getElementById('live-text').textContent = text;
  };
})();
