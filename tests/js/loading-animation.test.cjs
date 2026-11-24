const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('resources/js/loading-animation.js', 'utf8');

/** Simulate browser events/timers without a DOM package or network access. */
function createHarness() {
  class FakeElement {
    constructor(anchor = null) { this.anchor = anchor; }
    closest(selector) { return selector === 'a[href]' ? this.anchor : null; }
  }
  class FakeForm extends FakeElement {
    constructor(download = false) {
      super();
      this.method = 'post';
      this.target = '';
      this.dataset = {};
      this.download = download;
    }
    matches(selector) {
      return this.download
        && selector.includes('[data-rt03-download]');
    }
  }

  const listeners = new Map();
  const windowListeners = new Map();
  const timers = new Map();
  let clock = 0;
  let nextId = 0;
  const label = { textContent: '' };
  const overlay = { hidden: true, querySelector: () => label };
  const window = {
    setTimeout(fn, delay) {
      const id = ++nextId;
      timers.set(id, { when: clock + delay, fn });
      return id;
    },
    clearTimeout(id) { timers.delete(id); },
    addEventListener(type, fn) {
      const list = windowListeners.get(type) || [];
      list.push(fn);
      windowListeners.set(type, list);
    },
  };
  const document = {
    readyState: 'complete',
    querySelector(selector) {
      return selector === '[data-rt03-loading-overlay]' ? overlay : null;
    },
    addEventListener(type, fn) {
      const list = listeners.get(type) || [];
      list.push(fn);
      listeners.set(type, list);
    },
  };
  const location = new URL('http://127.0.0.1:8000/app/keuangan');
  vm.runInNewContext(source, {
    window, document, location, URL, Element: FakeElement,
    HTMLFormElement: FakeForm,
  }, { filename: 'resources/js/loading-animation.js' });

  function emit(target, type, event = {}) {
    const map = target === 'window' ? windowListeners : listeners;
    for (const fn of map.get(type) || []) fn(event);
  }
  function step(ms) {
    const end = clock + ms;
    while (true) {
      const scheduled = [...timers.entries()]
        .filter(([, entry]) => entry.when <= end)
        .sort((a, b) => a[1].when - b[1].when)[0];
      if (!scheduled) break;
      const [id, entry] = scheduled;
      timers.delete(id);
      clock = entry.when;
      entry.fn();
    }
    clock = end;
  }

  function click(path, attributes = [], target = '') {
    const attr = new Set(attributes);
    const anchor = {
      href: new URL(path, location.href).href,
      target,
      hasAttribute: (name) => attr.has(name),
      closest: (selector) => {
        if (selector.includes('[data-rt03-download]') && (
          attr.has('data-rt03-download') || attr.has('data-rt03-no-loading')
        )) return anchor;
        return null;
      },
    };
    emit('document', 'click', {
      defaultPrevented: false, button: 0, target: new FakeElement(anchor),
      metaKey: false, ctrlKey: false, shiftKey: false, altKey: false,
    });
  }
  return {
    click, step, emit, label, overlay, timers, FakeForm,
    submit(form) {
      emit('document', 'submit', { target: form, defaultPrevented: false });
    },
  };
}

test('finance and secretariat attachment endpoints do not open the loader', () => {
  const h = createHarness();
  h.click('/app/keuangan/bukti/5');
  h.step(250);
  assert.equal(h.overlay.hidden, true);
  h.click('/app/dokumen/9/unduh');
  h.step(20000);
  assert.equal(h.overlay.hidden, true);
});

test('annotated future downloads and download attributes do not show loading', () => {
  const h = createHarness();
  h.click('/app/download/custom', ['data-rt03-download']);
  h.click('/app/download/second', ['data-rt03-no-loading']);
  h.click('/app/download/third', ['download']);
  h.click('/app/page', [], '_blank');
  h.step(20000);
  assert.equal(h.overlay.hidden, true);
});

test('ordinary workspace navigation still shows and dismisses loading', () => {
  const h = createHarness();
  h.click('/app/tugas');
  h.step(160);
  assert.equal(h.overlay.hidden, false);
  assert.equal(h.label.textContent, 'Membuka halaman…');
  h.emit('window', 'pagehide');
  assert.equal(h.overlay.hidden, true);
});

test('fallback releases stuck link loading without blocking any navigation', () => {
  const h = createHarness();
  h.click('/app/unknown-page');
  h.step(200);
  assert.equal(h.overlay.hidden, false);
  h.step(11800);
  assert.equal(h.overlay.hidden, true);
  assert.equal(h.timers.size, 0);
});

test('SPA completion cancels the previous loader and stale timeout', () => {
  const h = createHarness();
  h.click('/app/tugas');
  h.step(100);
  h.emit('document', 'rt03:loading:start', { detail: { label: 'Membuka halaman…' } });
  h.step(160);
  assert.equal(h.overlay.hidden, false);
  h.emit('document', 'rt03:loading:end');
  assert.equal(h.overlay.hidden, true);
  h.step(15000);
  assert.equal(h.overlay.hidden, true);
});

test('POST form submissions still show progress unless explicitly marked download', () => {
  const h = createHarness();
  h.submit(new h.FakeForm(true));
  h.step(200);
  assert.equal(h.overlay.hidden, true);
  h.submit(new h.FakeForm());
  h.step(140);
  assert.equal(h.overlay.hidden, false);
  assert.equal(h.label.textContent, 'Memproses permintaan…');
  h.emit('window', 'pageshow');
  assert.equal(h.overlay.hidden, true);
});
