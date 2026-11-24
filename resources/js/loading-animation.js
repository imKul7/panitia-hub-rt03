/**
 * Global RT03 request animation: liquid fill + square morph.
 * Works across public fast routes, internal role-based routes, login,
 * ordinary links and form submissions.
 *
 * Never delays navigation: only appears after 140–180 ms of actual work.
 * Loader is pointer-transparent so Back, escape and cancellation still work.
 */
const overlay = document.querySelector('[data-rt03-loading-overlay]');

if (overlay) {
  const label = overlay.querySelector('[data-rt03-loading-label]');
  let token = 0;
  let waiting = null;

  function finish() {
    token += 1;
    if (waiting !== null) window.clearTimeout(waiting);
    waiting = null;
    overlay.hidden = true;
  }

  function begin(text = 'Membuka halaman…', delayMs = 150) {
    const version = ++token;
    if (waiting !== null) window.clearTimeout(waiting);
    waiting = window.setTimeout(() => {
      waiting = null;
      if (version !== token) return;
      if (label) label.textContent = text;
      overlay.hidden = false;
    }, delayMs);
  }

  document.addEventListener('rt03:loading:start', event => {
    begin(event.detail?.label || 'Membuka halaman…', 160);
  });

  document.addEventListener('rt03:loading:end', finish);

  // Normal Laravel forms perform full navigation. Show progress, but never
  // interfere with native constraint validation or confirmed actions.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || event.defaultPrevented) return;
    if (form.matches('[data-rt03-confirm]') && form.dataset.rt03Confirmed !== '1') return;
    if (form.target && form.target !== '_self') return;
    begin(form.method.toLowerCase() === 'get' ? 'Membuka halaman…' : 'Memproses permintaan…', 140);
  });

  // Only normal same-origin navigations not intercepted by the fast routers.
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey
      || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link || link.hasAttribute('download') || link.target === '_blank'
      || link.closest('[data-rt03-no-loading]')) return;
    try {
      const url = new URL(link.href, location.href);
      if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)
        || (url.pathname === location.pathname && url.search === location.search)
        || url.hash) return;
      begin('Membuka halaman…', 160);
    } catch { /* Ignore malformed or unsupported links. */ }
  });

  // Back/Forward Cache restores an old document without a new load.
  window.addEventListener('pageshow', finish);
  window.addEventListener('pagehide', finish);

  // Initial load: show progress only when page resources genuinely remain
  // pending. No mandatory splash screen or artificial animation wait.
  if (document.readyState !== 'complete') {
    begin('Menyiapkan halaman…', 250);
    window.addEventListener('load', finish, { once: true });
  }
}
