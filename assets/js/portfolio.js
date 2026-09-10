(() => {
  'use strict';
  let renderedURL = location.href;
  const serviceTypes = {'custom-web-application-development':'Web Application','laravel-development':'Web Application','saas-development':'SaaS Platform','crm-development':'CRM','hrms-development':'HRMS','erp-development':'ERP','healthcare-management-system-development':'Healthcare System','inventory-management-system-development':'Inventory System','warehouse-management-system-development':'Warehouse System','api-development-integration':'API Integration','laravel-maintenance':'Existing Project Improvement','admin-panel-development':'Web Application'};

  function closeMenu() {
    const toggle = document.querySelector('.menu-toggle');
    toggle?.setAttribute('aria-expanded', 'false');
    toggle?.setAttribute('aria-label', 'Open navigation');
    document.querySelector('#main-nav')?.classList.remove('is-open');
  }

  function applyFilter(form, value) {
    let count = 0;
    document.querySelectorAll('#main [data-categories]').forEach(card => {
      card.hidden = value !== 'All' && !card.dataset.categories.split('|').includes(value);
      if (!card.hidden) count++;
    });
    form.querySelectorAll('[data-filter]').forEach(button => {
      const selected = button.dataset.filter === value;
      button.classList.toggle('selected', selected);
      button.setAttribute('aria-pressed', String(selected));
    });
    const status = document.querySelector('[data-filter-status]');
    if (status) status.textContent = count ? `${count} ${count === 1 ? 'result' : 'results'}` : 'No entries in this category yet. Explore another category.';
  }

  function updateExperience() {
    const year = Number(new Intl.DateTimeFormat('en', {year: 'numeric', timeZone: 'Asia/Kolkata'}).format(new Date()));
    document.querySelectorAll('[data-experience-since]').forEach(element => {
      const years = Math.max(0, year - Number(element.dataset.experienceSince));
      const duration = `${years} ${years === 1 ? 'year' : 'years'}`;
      element.textContent = element.dataset.experienceFormat === 'count' ? `~${years}`
        : element.dataset.experienceFormat === 'duration' ? `around ${duration} of professional experience`
        : `Around ${duration} of professional development experience.`;
    });
  }
  // Also update tabs left open across the start of a new year.
  window.setInterval(updateExperience, 60000);
  document.addEventListener('visibilitychange', updateExperience);

  function initializePage() {
    updateExperience();
    const params = new URLSearchParams(location.search);
    document.querySelectorAll('[data-filter-form]').forEach(form => {
      const value = params.get('category') || 'All';
      if ([...form.querySelectorAll('[data-filter]')].some(button => button.dataset.filter === value)) applyFilter(form, value);
    });
    const inquiry = document.querySelector('#inquiry-form');
    const selectedService = serviceTypes[params.get('service')];
    if (inquiry && selectedService) inquiry.elements.project_type.value = selectedService;
    const submit = inquiry?.querySelector('[type=submit]');
    if (submit?.disabled) {
      submit.disabled = false;
      submit.textContent = 'Request a free consultation';
    }
  }

  // Delegated handlers survive page replacement without duplicate listeners.
  document.addEventListener('click', event => {
    if (!(event.target instanceof Element)) return;
    const toggle = event.target.closest('.menu-toggle');
    if (toggle) {
      const open = toggle.getAttribute('aria-expanded') !== 'true';
      toggle.setAttribute('aria-expanded', String(open));
      toggle.setAttribute('aria-label', open ? 'Close navigation' : 'Open navigation');
      document.querySelector('#main-nav')?.classList.toggle('is-open', open);
    }
    const filter = event.target.closest('[data-filter]');
    const form = filter?.closest('[data-filter-form]');
    if (form) {
      event.preventDefault();
      applyFilter(form, filter.dataset.filter);
      const url = new URL(location.href);
      filter.dataset.filter === 'All' ? url.searchParams.delete('category') : url.searchParams.set('category', filter.dataset.filter);
      history.replaceState(history.state, '', url);
      renderedURL = location.href;
    }
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') closeMenu(); });
  document.addEventListener('submit', event => {
    const form = event.target;
    if (form.matches('[data-confirm-delete]') && !confirm('Delete this content record? This cannot be undone.')) event.preventDefault();
    if (form.id === 'inquiry-form' && form.checkValidity()) {
      const button = form.querySelector('[type=submit]');
      button.disabled = true;
      button.textContent = 'Sending your enquiry…';
    }
  });
  window.addEventListener('pageshow', initializePage);
  initializePage();

  // Admin, legacy documents and unsupported browsers keep native navigation.
  if (!document.querySelector('.site-header') || !window.fetch || !window.AbortController || !history.pushState) return;
  const metaSelector = 'meta[name="description"],meta[name="author"],meta[name="robots"],meta[property^="og:"],meta[name^="twitter:"],link[rel="canonical"],script[type="application/ld+json"]';
  const scriptPath = [...document.scripts].find(script => script.src.includes('/assets/js/portfolio.js'))?.getAttribute('src');
  const announcer = document.createElement('div');
  announcer.className = 'navigation-announcement';
  announcer.setAttribute('role', 'status');
  announcer.setAttribute('aria-live', 'polite');
  document.body.append(announcer);
  let controller = null;
  let scrollFrame = null;
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  function saveScroll() {
    // During back/forward the address already points to the destination entry.
    if (location.href !== renderedURL || controller) return;
    history.replaceState({...history.state, portfolioScroll: [window.scrollX, window.scrollY]}, '');
  }
  window.addEventListener('scroll', () => {
    if (scrollFrame !== null) return;
    scrollFrame = requestAnimationFrame(() => { scrollFrame = null; saveScroll(); });
  }, {passive: true});
  saveScroll();

  function cancelNavigation() {
    controller?.abort();
    controller = null;
    document.body.classList.remove('page-loading');
    document.querySelector('#main')?.removeAttribute('aria-busy');
  }

  function placeScroll(url, position) {
    if (position) {
      window.scrollTo({left: position[0], top: position[1], behavior: 'instant'});
      return;
    }
    let target;
    try { target = url.hash && document.getElementById(decodeURIComponent(url.hash.slice(1))); } catch { /* malformed anchors fall back to the top */ }
    if (target) target.scrollIntoView();
    else window.scrollTo({left: 0, top: 0, behavior: 'instant'});
  }

  async function navigate(url, {pop = false, position = null} = {}) {
    if (!pop) saveScroll();
    cancelNavigation();
    const request = new AbortController();
    controller = request;
    const timeout = setTimeout(() => {
      if (controller === request) {
        cancelNavigation();
        location.assign(url.href);
      }
    }, 12000);
    document.body.classList.add('page-loading');
    document.querySelector('#main')?.setAttribute('aria-busy', 'true');
    closeMenu();
    try {
      // Do not cache PHP forms: each visit must receive a valid current CSRF token.
      const response = await fetch(url.href, {signal: request.signal, credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'text/html'}});
      if (!response.ok || !response.headers.get('content-type')?.includes('text/html')) throw new Error('Native navigation required');
      const finalURL = new URL(response.url || url.href);
      if (finalURL.origin !== location.origin) throw new Error('External redirect');
      const html = await response.text();
      if (request.signal.aborted) return;
      const next = new DOMParser().parseFromString(html, 'text/html');
      const main = next.querySelector('#main');
      const header = next.querySelector('.site-header');
      const footer = next.querySelector('.site-footer');
      const nextScript = [...next.scripts].find(script => script.getAttribute('src')?.includes('/assets/js/portfolio.js'))?.getAttribute('src');
      if (!main || !header || !footer || !next.title || nextScript !== scriptPath) throw new Error('Incompatible page');
      finalURL.hash = url.hash;
      document.querySelector('#main').replaceWith(document.importNode(main, true));
      document.querySelector('.site-header').replaceWith(document.importNode(header, true));
      document.querySelector('.site-footer').replaceWith(document.importNode(footer, true));
      document.head.querySelectorAll(metaSelector).forEach(node => node.remove());
      next.head.querySelectorAll(metaSelector).forEach(node => document.head.append(document.importNode(node, true)));
      document.title = next.title;
      if (pop) history.replaceState(history.state, '', finalURL);
      else history.pushState({portfolioScroll: [0, 0]}, '', finalURL);
      renderedURL = location.href;
      initializePage();
      const currentMain = document.querySelector('#main');
      currentMain.setAttribute('tabindex', '-1');
      currentMain.focus({preventScroll: true});
      placeScroll(finalURL, position);
      announcer.textContent = `Loaded: ${next.title}`;
      document.dispatchEvent(new CustomEvent('portfolio:navigated', {detail: {url: location.href}}));
    } catch (error) {
      if (!request.signal.aborted) location.assign(url.href);
    } finally {
      clearTimeout(timeout);
      if (controller === request) {
        controller = null;
        document.body.classList.remove('page-loading');
        document.querySelector('#main')?.removeAttribute('aria-busy');
        saveScroll();
      }
    }
  }

  document.addEventListener('click', event => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !(event.target instanceof Element)) return;
    const link = event.target.closest('a[href]');
    if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self') || link.hasAttribute('data-no-navigation')) return;
    const url = new URL(link.href, location.href);
    if (url.origin !== location.origin || !/^https?:$/.test(url.protocol)) return;
    if (url.pathname !== '/' && !/^\/(about|services|projects|technologies|blog|contact|privacy)(\/|$)/.test(url.pathname)) return;
    if (/\.[^/]+$/.test(url.pathname)) return;
    if (url.pathname === location.pathname && url.search === location.search && url.hash) {
      cancelNavigation();
      closeMenu();
      return; // Preserve native anchor links and keyboard behavior.
    }
    event.preventDefault();
    navigate(url);
  });
  window.addEventListener('hashchange', () => { renderedURL = location.href; });
  window.addEventListener('popstate', event => {
    const url = new URL(location.href);
    const previous = new URL(renderedURL);
    if (url.pathname === previous.pathname && url.search === previous.search) {
      cancelNavigation();
      renderedURL = url.href;
      placeScroll(url, event.state?.portfolioScroll);
      return;
    }
    navigate(url, {pop: true, position: event.state?.portfolioScroll});
  });
})();
