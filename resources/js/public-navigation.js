/**
 * PANITIA HUB RT 03 - instant public navigation (progressive enhancement).
 *
 * Only the six public GET routes are eligible. Full Laravel HTML is fetched
 * once, then only <main> changes. Header, CSS, JS, Lenis and icons remain
 * mounted. The server still renders all resident-facing data and permissions.
 * Any unexpected response uses ordinary navigation as a safe fallback.
 */
const rt03Body = document.body;
const rt03Nav = document.querySelector('[data-public-nav]');

if (rt03Body?.matches('[data-rt03-public-router]') && rt03Nav
  && window.fetch && window.history?.pushState && window.DOMParser) {
  const menuLinks = Array.from(rt03Nav.querySelectorAll('a[href]'));
  const eligiblePaths = new Set(menuLinks.map(a => new URL(a.href, location.href).pathname));
  const ttlMs = 15000; // Fresh public info after a short interval.
  const pageCache = new Map(); // Public HTML only. Never cache login/workspace.
  const inFlight = new Map();
  let requestVersion = 0;

  // Browser history is preserved, including back/forward scroll positions.
  history.scrollRestoration = 'manual';
  if (!history.state?.rt03Public) {
    history.replaceState({ ...(history.state || {}), rt03Public: true, scrollY: scrollY }, '', location.href);
  }

  const routeURL = value => {
    try {
      const target = new URL(value, location.href);
      if (target.origin !== location.origin || target.search || target.hash
          || !eligiblePaths.has(target.pathname)) return null;
      return target;
    } catch {
      return null;
    }
  };

  function reflectPending(path) {
    rt03Nav.classList.toggle('is-navigating', Boolean(path));
    menuLinks.forEach(link => {
      link.classList.toggle('is-pending', Boolean(path) && new URL(link.href).pathname === path);
    });
  }

  function reflectCurrent(path) {
    menuLinks.forEach(link => {
      const active = new URL(link.href).pathname === path;
      link.classList.toggle('is-active', active);
      if (active) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
    reflectPending(null);
  }

  function closeMobileMenu() {
    rt03Nav.classList.remove('is-open');
    document.querySelector('[data-mobile-menu]')?.setAttribute('aria-expanded', 'false');
  }

  function fetchPage(url) {
    const path = url.pathname;
    const cached = pageCache.get(path);
    if (cached && Date.now() - cached.savedAt < ttlMs) {
      return Promise.resolve(cached.html);
    }
    if (inFlight.has(path)) return inFlight.get(path);

    const pending = fetch(url.href, {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'text/html' },
      cache: 'no-store',
    }).then(async response => {
      const finalPath = new URL(response.url).pathname;
      if (!response.ok || response.redirected || finalPath !== path
          || !response.headers.get('content-type')?.includes('text/html')) {
        throw new Error('Unexpected response for public page.');
      }
      const html = await response.text();
      pageCache.set(path, { html, savedAt: Date.now() });
      return html;
    }).finally(() => inFlight.delete(path));

    inFlight.set(path, pending);
    return pending;
  }

  function parsePage(html) {
    const page = new DOMParser().parseFromString(html, 'text/html');
    const main = page.querySelector('main#main');
    const expectedNav = page.querySelector('[data-public-nav]');
    if (!main || !expectedNav || !page.body.matches('[data-rt03-public-router]')) {
      throw new Error('Navigation response did not contain the expected public layout.');
    }
    return { main, title: page.title, bodyClasses: page.body.className };
  }

  function swapPage(page, url, mode, restoredY) {
    document.dispatchEvent(new Event('rt03:public:before-swap'));
    document.getElementById('main')?.replaceWith(page.main);
    document.body.className = page.bodyClasses;
    document.title = page.title;

    if (mode === 'push') {
      history.replaceState({ ...(history.state || {}), rt03Public: true, scrollY }, '', location.href);
      history.pushState({ rt03Public: true, scrollY: 0 }, '', url.href);
    }

    reflectCurrent(url.pathname);
    closeMobileMenu();

    const destinationScroll = mode === 'pop' ? Math.max(0, restoredY || 0) : 0;
    window.rt03PublicLenis?.scrollTo(destinationScroll, { immediate: true, force: true });
    window.scrollTo({ top: destinationScroll, left: 0, behavior: 'instant' });

    // No old-page 0.25s exit animation. Content is already swapped.
    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      page.main.classList.add('rt03-quick-enter');
      page.main.addEventListener('animationend', () => page.main.classList.remove('rt03-quick-enter'), { once: true });
    }

    document.dispatchEvent(new Event('rt03:public:after-swap'));
    if (mode !== 'pop') {
      page.main.tabIndex = -1;
      page.main.focus({ preventScroll: true });
    }
  }

  async function navigate(url, mode = 'push', restoredY = 0) {
    const version = ++requestVersion;
    if (mode === 'push' && url.pathname === location.pathname) {
      // Also cancels a previous pending navigation and restores the active tab.
      reflectCurrent(location.pathname);
      closeMobileMenu();
      document.getElementById('main')?.removeAttribute('aria-busy');
      window.rt03PublicLenis?.scrollTo(0, { immediate: true, force: true });
      window.scrollTo(0, 0);
      return;
    }
    reflectPending(url.pathname);
    document.getElementById('main')?.setAttribute('aria-busy', 'true');
    try {
      const html = await fetchPage(url);
      if (version !== requestVersion) return; // Ignore stale racing requests.
      const page = parsePage(html);
      swapPage(page, url, mode, restoredY);
    } catch {
      if (version !== requestVersion) return;
      // Revert to fully working Laravel navigation when fetch fails.
      if (mode === 'pop') location.reload();
      else location.assign(url.href);
    } finally {
      if (version === requestVersion) {
        reflectPending(null);
        document.getElementById('main')?.removeAttribute('aria-busy');
      }
    }
  }

  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey
        || event.shiftKey || event.altKey) return;
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link || link.hasAttribute('download')
        || (link.target && link.target !== '_self')) return;
    const url = routeURL(link.href);
    if (!url) return;
    event.preventDefault();
    closeMobileMenu(); // Respond instantly on mobile, even if fetch is pending.
    void navigate(url);
  });

  window.addEventListener('popstate', event => {
    const url = routeURL(location.href);
    if (!url) return;
    void navigate(url, 'pop', event.state?.scrollY || 0);
  });

  // Warm the page before click when a menu item is hovered/focused.
  const prefetch = link => {
    if (document.visibilityState === 'hidden') return;
    const url = routeURL(link.href);
    if (url && url.pathname !== location.pathname) void fetchPage(url).catch(() => {});
  };
  rt03Nav.addEventListener('pointerover', event => {
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (link) prefetch(link);
  });
  rt03Nav.addEventListener('focusin', event => {
    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (link) prefetch(link);
  });

  // Laravel's local development server may be single-threaded. Warming all
  // five pages concurrently can delay actual clicks. Only warm the two most
  // frequently visited destinations (Lomba and Jadwal), sequentially.
  // The remaining pages are fetched on pointer hover / keyboard focus.
  const warmPages = async () => {
    if (document.visibilityState === 'hidden') return;
    const favorites = ['/informasi/lomba', '/informasi/jadwal'];
    for (const path of favorites) {
      if (document.visibilityState !== 'visible') break;
      if (path === location.pathname || !eligiblePaths.has(path)) continue;
      await fetchPage(new URL(path, location.origin)).catch(() => {});
    }
  };
  if ('requestIdleCallback' in window) requestIdleCallback(() => { void warmPages(); }, { timeout: 1800 });
  else setTimeout(() => { void warmPages(); }, 1100);

  window.addEventListener('pageshow', () => reflectCurrent(location.pathname));
  reflectCurrent(location.pathname);
}
