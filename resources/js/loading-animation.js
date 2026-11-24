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
  let linkFallback = null;
  // When a backend returns Content-Disposition: attachment there is no
  // document unload. A defensive guard prevents an unannotated future
  // download or canceled ordinary link from leaving the loader forever.
  const LINK_GUARD_MS = 12000;
  const fileRoutePattern = /^\/app\/(?:keuangan\/bukti\/[^/]+|dokumen\/[^/]+\/unduh)\/?$/;

  function nonNavigatingLink(link, url) {
    return link.hasAttribute('download')
      || Boolean(link.closest('[data-rt03-download], [data-rt03-no-loading]'))
      || fileRoutePattern.test(url.pathname);
  }

  function finish() {
    token += 1;
    if (waiting !== null) window.clearTimeout(waiting);
    if (linkFallback !== null) window.clearTimeout(linkFallback);
    waiting = null;
    linkFallback = null;
    overlay.hidden = true;
  }

  function begin(text = 'Membuka halaman…', delayMs = 150, fromLink = false) {
    const version = ++token;
    if (waiting !== null) window.clearTimeout(waiting);
    if (linkFallback !== null) window.clearTimeout(linkFallback);
    linkFallback = null;
    waiting = window.setTimeout(() => {
      waiting = null;
      if (version !== token) return;
      if (label) label.textContent = text;
      overlay.hidden = false;
    }, delayMs);
    if (fromLink) {
      // Do not impose a waiting time on navigation: this merely releases an
      // orphaned overlay if the same document is still on screen afterward.
      linkFallback = window.setTimeout(() => {
        if (version === token) finish();
      }, LINK_GUARD_MS);
    }
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
    if (form.matches('[data-rt03-download], [data-rt03-no-loading]')) return;
    if (form.target && form.target !== '_self') return;
    begin(form.method.toLowerCase() === 'get' ? 'Membuka halaman…' : 'Memproses permintaan…', 140);
  });

  // Only normal same-origin navigations not intercepted by the fast routers.
  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey
      || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link || (link.target && link.target !== '_self')) return;
    try {
      const url = new URL(link.href, location.href);
      if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)
        || (url.pathname === location.pathname && url.search === location.search)
        || url.hash || nonNavigatingLink(link, url)) return;
      begin('Membuka halaman…', 160, true);
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
