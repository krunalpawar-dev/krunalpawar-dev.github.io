const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const {JSDOM, VirtualConsole} = require('jsdom');
const root = path.resolve(__dirname, '..');
const script = fs.readFileSync(path.join(root, 'assets/js/portfolio.js'), 'utf8');
const origin = 'https://krunalpawar-dev.github.io';
const tick = () => new Promise(resolve => setTimeout(resolve, 25));
const pageHTML = pathname => fs.readFileSync(path.join(root, pathname.replace(/^\//, ''), 'index.html'), 'utf8');

async function fixture(t, pathname = '/') {
  const errors = [];
  const console = new VirtualConsole();
  console.on('jsdomError', error => errors.push(error));
  const dom = new JSDOM(pageHTML(pathname), {url: origin + pathname, runScripts: 'outside-only', pretendToBeVisual: true, virtualConsole: console});
  t.after(() => dom.window.close());
  const w = dom.window;
  w.AbortController = AbortController;
  w.scrollTo = options => { w.scrollX = options.left; w.scrollY = options.top; };
  w.HTMLElement.prototype.scrollIntoView = function() { w.lastAnchor = this.id; };
  const requests = [];
  const answer = (url, html) => ({ok: true, url: String(url), headers: {get: () => 'text/html; charset=utf-8'}, text: async () => html ?? pageHTML(new URL(url).pathname)});
  w.fetch = async (url, options) => { requests.push({url, options}); return answer(url); };
  w.eval(script);
  await tick();
  const click = (selector, options = {}) => {
    const link = w.document.querySelector(selector);
    assert.ok(link, selector);
    const event = new w.MouseEvent('click', {bubbles: true, cancelable: true, button: 0, ...options});
    link.dispatchEvent(event);
    return event;
  };
  const loaded = () => new Promise((resolve, reject) => {
    const timer = setTimeout(() => reject(new Error('Navigation did not finish')), 1500);
    w.document.addEventListener('portfolio:navigated', () => {clearTimeout(timer); resolve();}, {once: true});
  });
  return {w, requests, errors, click, loaded, answer};
}

test('internal navigation keeps the document and updates content, SEO, focus and active links', async t => {
  const f = await fixture(t);
  const originalDocument = f.w.document;
  f.w.testMarker = 'same browsing context';
  const ready = f.loaded();
  assert.equal(f.click('#main-nav a[href="/projects/"]').defaultPrevented, true);
  await ready;
  assert.equal(f.w.document, originalDocument);
  assert.equal(f.w.testMarker, 'same browsing context');
  assert.equal(f.w.location.pathname, '/projects/');
  assert.match(f.w.document.querySelector('h1').textContent, /Software for real/);
  assert.equal(f.w.document.querySelector('#main-nav [aria-current="page"]').textContent, 'Projects');
  assert.match(f.w.document.title, /Software Projects/);
  assert.equal(f.w.document.querySelector('link[rel=canonical]').href, origin + '/projects/');
  assert.equal(f.w.document.activeElement.id, 'main');
  assert.match(f.w.document.querySelector('[role=status]').textContent, /Loaded:/);
  assert.equal(f.w.document.querySelectorAll('script[type="application/ld+json"]').length, 1);
  assert.equal(f.requests[0].options.cache, 'no-store');
  assert.equal(f.w.document.body.classList.contains('page-loading'), false);
  assert.equal(f.errors.length, 0);
});

test('filters and mobile menu work after repeated page swaps; back restores filters and scroll', async t => {
  const f = await fixture(t);
  let ready = f.loaded(); f.click('#main-nav a[href="/projects/"]'); await ready;
  f.click('[data-filter="HRMS"]');
  assert.equal(f.w.location.search, '?category=HRMS');
  assert.equal(f.w.document.querySelectorAll('.project-card:not([hidden])').length, 1);
  f.w.scrollTo({left: 0, top: 420});
  f.w.dispatchEvent(new f.w.Event('scroll')); await tick();
  ready = f.loaded(); f.click('#main-nav a[href="/about/"]'); await ready;
  ready = f.loaded(); f.w.history.back(); await ready;
  assert.equal(f.w.location.search, '?category=HRMS');
  assert.equal(f.w.document.querySelectorAll('.project-card:not([hidden])').length, 1);
  assert.equal(f.w.scrollY, 420);
  f.click('.menu-toggle');
  assert.equal(f.w.document.querySelector('.menu-toggle').getAttribute('aria-expanded'), 'true');
  ready = f.loaded(); f.w.history.forward(); await ready;
  assert.equal(f.w.location.pathname, '/about/');
  assert.equal(f.w.document.querySelector('.menu-toggle').getAttribute('aria-expanded'), 'false');
});

test('contact preselection and normal form submissions remain functional', async t => {
  const f = await fixture(t, '/services/crm-development/');
  const ready = f.loaded(); f.click('a[href="/contact/?service=crm-development"]'); await ready;
  const form = f.w.document.querySelector('#inquiry-form');
  assert.equal(form.elements.project_type.value, 'CRM');
  assert.equal(form.action, 'https://formspree.io/f/xdkeodjk');
  form.elements.name.value = 'Test Name';
  form.elements.email.value = 'test@example.test';
  form.elements.description.value = 'An isolated navigation test. This is never sent.';
  form.elements.consent.checked = true;
  assert.equal(form.checkValidity(), true);
  const event = new f.w.Event('submit', {bubbles: true, cancelable: true});
  form.dispatchEvent(event); // No actual submit or network request.
  assert.equal(event.defaultPrevented, false);
  assert.equal(form.querySelector('[type=submit]').disabled, true);
  f.w.dispatchEvent(new f.w.Event('pageshow'));
  assert.equal(form.querySelector('[type=submit]').disabled, false);
  assert.equal(f.requests.length, 1);
});

test('downloads, external links, modified clicks and anchors are not intercepted', async t => {
  const f = await fixture(t, '/about/');
  assert.equal(f.click('a[download]').defaultPrevented, false);
  assert.equal(f.click('a[href^="https://www.linkedin.com"]').defaultPrevented, false);
  assert.equal(f.click('#main-nav a[href="/projects/"]', {ctrlKey: true}).defaultPrevented, false);
  assert.equal(f.click('a[href="#main"]').defaultPrevented, false);
  assert.equal(f.requests.length, 0);
});

test('a newer click cancels an older pending navigation', async t => {
  const f = await fixture(t);
  const pending = [];
  f.w.fetch = (url, options) => new Promise(resolve => pending.push({url, options, resolve}));
  f.click('#main-nav a[href="/projects/"]');
  f.click('#main-nav a[href="/about/"]');
  assert.equal(pending[0].options.signal.aborted, true);
  const ready = f.loaded(); pending[1].resolve(f.answer(pending[1].url)); await ready;
  pending[0].resolve(f.answer(pending[0].url)); await tick();
  assert.equal(f.w.location.pathname, '/about/');
  assert.match(f.w.document.querySelector('h1').textContent, /Hi, I’m Krunal/);
  assert.equal(f.w.document.body.classList.contains('page-loading'), false);
});

test('failed requests and incompatible HTML fall back to native navigation', async t => {
  for (const mode of ['network', '404', 'incompatible']) {
    const f = await fixture(t);
    f.w.fetch = async url => {
      if (mode === 'network') throw new Error('Offline');
      if (mode === '404') return {ok: false, headers: {get: () => 'text/html'}};
      return f.answer(url, '<html><head><title>Other site</title></head><body>No compatible content</body></html>');
    };
    const oldMain = f.w.document.querySelector('#main');
    f.click('#main-nav a[href="/projects/"]'); await tick();
    assert.equal(f.w.document.querySelector('#main'), oldMain);
    assert.equal(f.w.document.body.classList.contains('page-loading'), false);
    // JSDOM records attempts at location.assign instead of navigating externally.
    assert.ok(f.errors.some(error => /navigation/.test(error.message)), mode);
  }
});
