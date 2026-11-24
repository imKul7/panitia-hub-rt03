/**
 * PANITIA HUB RT03 — workspace fast navigation.
 *
 * GET-only, role-specific whitelist derived from the links the server has
 * actually rendered. Never intercept POST/PATCH/DELETE or file downloads.
 * Every visit is fetched with no-store and without retaining private HTML:
 * authorization is always re-checked by Laravel, including on Back/Forward.
 * If permissions/session/layout changed, fall back to a full document request.
 */
const workspaceBody = document.querySelector('body[data-rt03-workspace-router]');
const workspaceSidebar = workspaceBody?.querySelector('nav.sidebar-nav');
if (workspaceBody && workspaceSidebar && window.fetch && window.DOMParser
  && window.history?.pushState) {

  const routeLinks = [...workspaceSidebar.querySelectorAll('a[href]')];
  const dashboardLink = workspaceBody.querySelector('.app-brand[href]');
  const profileLink = workspaceBody.querySelector('a.account-pill[href]');
  const allNavLinks = [dashboardLink, profileLink, ...routeLinks].filter(Boolean);
  const knownPaths = new Set(allNavLinks.map(link => new URL(link.href, location.href).pathname));
  const statusBar = workspaceBody.querySelector('[data-rt03-workspace-progress]');
  const statusText = workspaceBody.querySelector('[data-rt03-workspace-progress-label]');
  const mainSelector = '.app-main > main.page-content';
  let requestVersion = 0;
  let activeController = null;
  let isChangingPage = false;

  history.scrollRestoration = 'manual';
  if (!history.state?.rt03Workspace) {
    history.replaceState({ ...(history.state || {}), rt03Workspace: true, scrollY: window.scrollY }, '', location.href);
  }

  function eligible(value) {
    try {
      const url = new URL(value, location.href);
      if (!/^https?:$/.test(url.protocol) || url.origin !== location.origin || url.hash
        || !knownPaths.has(url.pathname)) return null;
      // Allow ?page=N for Laravel's native pagination, never intercept
      // arbitrary query strings or signed/private download routes.
      if ([...url.searchParams.keys()].some(key => key !== 'page')) return null;
      return url;
    } catch {
      return null;
    }
  }

  function setProgress(text = '', on = false) {
    statusBar?.classList.toggle('is-visible', on);
    if (statusText) statusText.textContent = on ? text : '';
  }

  function currentPage(path) {
    routeLinks.forEach(link => {
      const active = new URL(link.href).pathname === path;
      link.classList.toggle('active', active);
      link.classList.remove('is-pending');
      if (active) link.setAttribute('aria-current', 'page');
      else link.removeAttribute('aria-current');
    });
  }

  function pendingPage(path) {
    routeLinks.forEach(link => {
      link.classList.toggle('is-pending', new URL(link.href).pathname === path);
    });
    setProgress('Membuka halaman…', true);
  }

  function setSidebarClosed() {
    // The existing Alpine x-data scope controls sidebar visibility.
    // A click on the rendered link also invokes its @click directive.
    workspaceBody.querySelector('.sidebar-scrim')?.dispatchEvent(new Event('rt03:navigation-start'));
  }

  function currentNavSignature(root) {
    return [...root.querySelectorAll('nav.sidebar-nav a[href]')].map(a => {
      const url = new URL(a.getAttribute('href'), location.origin);
      return url.pathname + '|' + a.textContent.trim();
    }).join('\n');
  }

  function extract(html) {
    const page = new DOMParser().parseFromString(html, 'text/html');
    const body = page.querySelector('body.app-body[data-rt03-workspace-router]');
    const main = page.querySelector(mainSelector);
    const title = page.querySelector('.breadcrumb-current');
    const year = page.querySelector('.topbar-year');
    if (!body || !main || !title || !year || !page.querySelector('nav.sidebar-nav')) {
      throw new Error('Unexpected workspace layout.');
    }
    // Role changes/period activation in another tab must refresh the whole
    // shell; don't leave stale links or old access controls visible.
    if (currentNavSignature(page) !== currentNavSignature(document)
      || year.textContent.trim() !== workspaceBody.querySelector('.topbar-year')?.textContent.trim()) {
      throw new Error('Workspace access or period changed.');
    }
    return {
      main, title: page.title, breadcrumb: title.textContent, path: location.pathname,
      feedback: page.querySelector('[data-rt03-feedback-dialog]'),
    };
  }

  function clearOldMain(current) {
    if (window.Alpine?.destroyTree) window.Alpine.destroyTree(current);
    current.remove();
  }

  function render(page, url, mode, savedScroll) {
    const current = document.querySelector(mainSelector);
    if (!current) throw new Error('Workspace content disappeared.');

    document.dispatchEvent(new Event('rt03:workspace:before-swap'));
    clearOldMain(current);
    workspaceBody.querySelector('.app-topbar')?.insertAdjacentElement('afterend', page.main);
    const existingFeedback = document.querySelector('[data-rt03-feedback-dialog]');
    if (existingFeedback && page.feedback) {
      if (existingFeedback.open) existingFeedback.close();
      existingFeedback.replaceWith(page.feedback);
    }
    document.title = page.title;
    const breadcrumb = workspaceBody.querySelector('.breadcrumb-current');
    if (breadcrumb) breadcrumb.textContent = page.breadcrumb;
    if (window.Alpine?.initTree) window.Alpine.initTree(page.main);

    if (mode === 'push') {
      history.replaceState({ ...(history.state || {}), rt03Workspace: true, scrollY: window.scrollY }, '', location.href);
      history.pushState({ rt03Workspace: true, scrollY: 0 }, '', url.href);
    }

    currentPage(url.pathname);
    isChangingPage = false;
    const y = mode === 'pop' ? Math.max(0, savedScroll || 0) : 0;
    window.rt03WorkspaceLenis?.scrollTo(y, { immediate: true, force: true });
    window.scrollTo({ top: y, left: 0, behavior: 'instant' });

    if (!window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
      page.main.classList.add('rt03-workspace-quick-enter');
      page.main.addEventListener('animationend', () => page.main.classList.remove('rt03-workspace-quick-enter'), { once: true });
    }

    document.dispatchEvent(new Event('rt03:workspace:after-swap'));
    if (mode !== 'pop') {
      page.main.tabIndex = -1;
      page.main.focus({ preventScroll: true });
    }
  }

  async function navigate(url, mode = 'push', savedScroll = 0) {
    const version = ++requestVersion;
    activeController?.abort();
    activeController = null;

    if (mode === 'push' && url.href === location.href) {
      currentPage(url.pathname);
      document.dispatchEvent(new Event('rt03:loading:end'));
      setProgress('', false);
      const y = 0;
      window.rt03WorkspaceLenis?.scrollTo(y, { immediate: true, force: true });
      window.scrollTo(0, 0);
      return;
    }

    const controller = new AbortController();
    activeController = controller;
    isChangingPage = true;
    pendingPage(url.pathname);
    document.dispatchEvent(new CustomEvent('rt03:loading:start', { detail: { label: 'Membuka halaman…' } }));
    const previousMain = document.querySelector(mainSelector);
    previousMain?.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch(url.href, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store',
        headers: { Accept: 'text/html' },
        signal: controller.signal,
      });
      if (!response.ok || response.redirected
        || response.url !== url.href
        || !response.headers.get('content-type')?.includes('text/html')) {
        throw new Error('Workspace request was redirected or rejected.');
      }
      const html = await response.text();
      if (version !== requestVersion) return;
      const page = extract(html);
      render(page, url, mode, savedScroll);
    } catch (error) {
      if (version !== requestVersion) return;
      if (error?.name === 'AbortError') return;
      // If the authenticated session changed, show Laravel's real result.
      // Fail closed for 403/419/error status and for mismatched permissions.
      if (mode === 'pop') location.reload();
      else location.assign(url.href);
    } finally {
      if (version === requestVersion) {
        activeController = null;
        isChangingPage = false;
        document.dispatchEvent(new Event('rt03:loading:end'));
        document.querySelector(mainSelector)?.removeAttribute('aria-busy');
        currentPage(location.pathname);
        setProgress('', false);
      }
    }
  }

  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey
      || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (document.querySelector('dialog[data-rt03-confirm-dialog][open], dialog[data-rt03-feedback-dialog][open]')) return;

    const link = event.target instanceof Element ? event.target.closest('a[href]') : null;
    if (!link || link.hasAttribute('download')
      || (link.target && link.target !== '_self')
      || link.closest('[data-rt03-no-fast-nav]')) return;

    const url = eligible(link.href);
    if (!url) return;

    event.preventDefault();
    // Visual response occurs on the same frame, even when Laravel is slow.
    void navigate(url);
  });

  window.addEventListener('popstate', event => {
    const url = eligible(location.href);
    if (!url) {
      location.reload();
      return;
    }
    void navigate(url, 'pop', event.state?.scrollY || 0);
  });

  window.addEventListener('scroll', () => {
    if (isChangingPage || !history.state?.rt03Workspace) return;
    // Save scroll in browser history for Back/Forward without extra request.
    history.replaceState({ ...history.state, scrollY: window.scrollY }, '', location.href);
  }, { passive: true });

  window.addEventListener('pageshow', () => currentPage(location.pathname));
  currentPage(location.pathname);

  // Preserve normal POST/PATCH/DELETE, file upload and backend flash messages.
  // Display progress immediately, but never mutate the submission payload.
  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement)) return;
    if (form.hasAttribute('data-rt03-confirm') && form.dataset.rt03Confirmed !== '1') return;
    if (form.dataset.rt03Submitting === '1') {
      event.preventDefault();
      return;
    }
    if (event.defaultPrevented) return;
    form.dataset.rt03Submitting = '1';
    form.classList.add('rt03-form-submitting');
    form.setAttribute('aria-busy', 'true');
    setProgress('Menyimpan perubahan…', true);
    // A failed HTML validation doesn't fire submit; normal Laravel
    // validation/error redirects will reset this state on the new document.
  });
}
