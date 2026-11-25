const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

const source = fs.readFileSync('resources/js/public-audio.js', 'utf8');

function harness(playOutcomes = ['success']) {
  const documentListeners = new Map();

  class FakeElement {
    closest() { return null; }
  }

  class FakeButton extends FakeElement {
    constructor() {
      super();
      this.attributes = {};
      this.label = { textContent: 'Putar' };
    }
    closest(selector) {
      return selector === '[data-rt03-anthem-toggle]' ? this : null;
    }
    setAttribute(name, value) { this.attributes[name] = value; }
    querySelector(selector) {
      return selector === '[data-rt03-anthem-label]' ? this.label : null;
    }
  }

  class FakeAudio extends FakeElement {
    constructor() {
      super();
      this.paused = true;
      this.ended = false;
      this.error = null;
      this.currentTime = 0;
      this.volume = 1;
      this.playCalls = 0;
      this.listeners = new Map();
      this.outcomes = [...playOutcomes];
    }
    addEventListener(event, fn) {
      const listeners = this.listeners.get(event) || [];
      listeners.push(fn);
      this.listeners.set(event, listeners);
    }
    emit(name) {
      for (const listener of this.listeners.get(name) || []) listener();
    }
    play() {
      this.playCalls += 1;
      const next = this.outcomes.shift() || 'success';
      if (next === 'block' || next === 'error') {
        const failure = new Error('Media could not play');
        failure.name = next === 'block' ? 'NotAllowedError' : 'NotSupportedError';
        return Promise.reject(failure);
      }
      this.paused = false;
      this.ended = false;
      this.emit('play');
      this.emit('playing');
      return Promise.resolve();
    }
    pause() {
      this.paused = true;
      this.emit('pause');
    }
  }

  class FakeWrapper {
    constructor() {
      this.classes = new Set();
      this.classList = {
        toggle: (name, on) => { if (on) this.classes.add(name); else this.classes.delete(name); },
      };
      this.status = { textContent: '' };
      this.button = new FakeButton();
    }
    querySelectorAll(selector) {
      if (selector === '[data-rt03-anthem-status]') return [this.status];
      if (selector === '[data-rt03-anthem-toggle]') return [this.button];
      return [];
    }
  }

  const audio = new FakeAudio();
  const wrappers = [new FakeWrapper(), new FakeWrapper()];
  const document = {
    querySelector(selector) {
      return selector === 'body[data-rt03-public-router] > [data-rt03-anthem-audio]'
        ? audio : null;
    },
    querySelectorAll(selector) {
      return selector === '[data-rt03-anthem-player]' ? wrappers : [];
    },
    addEventListener(name, fn) {
      const listeners = documentListeners.get(name) || [];
      listeners.push(fn);
      documentListeners.set(name, listeners);
    },
  };

  vm.runInNewContext(source, { document, Element: FakeElement, HTMLAudioElement: FakeAudio }, {
    filename: 'resources/js/public-audio.js',
  });

  function dispatch(type, target = new FakeElement(), opts = {}) {
    const event = {
      type, target, isTrusted: opts.trusted ?? true,
      key: opts.key, preventDefault() { this.defaultPrevented = true; },
    };
    for (const fn of documentListeners.get(type) || []) fn(event);
    return event;
  }

  const flush = async () => {
    await Promise.resolve();
    await Promise.resolve();
  };

  return { audio, wrappers, document, dispatch, flush, FakeElement, FakeWrapper };
}

test('audio attempts automatic playback on a public page when browser permits', async () => {
  const h = harness();
  await h.flush();
  assert.equal(h.audio.playCalls, 1);
  assert.equal(h.audio.paused, false);
  assert.equal(h.audio.volume, 0.38);
  assert.equal(h.wrappers[0].button.label.textContent, 'Jeda');
  assert.equal(h.wrappers[1].classes.has('is-awaiting-gesture'), false);
});

test('autoplay rejection prompts and the first trusted interaction starts music', async () => {
  const h = harness(['block', 'success']);
  await h.flush();
  assert.equal(h.audio.paused, true);
  assert.equal(h.wrappers[1].classes.has('is-awaiting-gesture'), true);
  assert.match(h.wrappers[0].status.textContent, /autoplay/);
  h.dispatch('pointerdown');
  await h.flush();
  assert.equal(h.audio.playCalls, 2);
  assert.equal(h.audio.paused, false);
  assert.equal(h.wrappers[1].classes.has('is-awaiting-gesture'), false);
});

test('synthetic interactions do not trigger a retry of blocked autoplay', async () => {
  const h = harness(['block', 'success']);
  await h.flush();
  h.dispatch('pointerdown', new h.FakeElement(), { trusted: false });
  h.dispatch('click', new h.FakeElement(), { trusted: false });
  await h.flush();
  assert.equal(h.audio.playCalls, 1);
  assert.equal(h.audio.paused, true);
});

test('explicit pause persists across navigation and unrelated clicks', async () => {
  const h = harness();
  await h.flush();
  h.dispatch('click', h.wrappers[1].button);
  assert.equal(h.audio.paused, true);
  assert.equal(h.wrappers[0].status.textContent, 'Musik dijeda');
  h.dispatch('pointerdown');
  h.dispatch('click');
  h.dispatch('keydown', new h.FakeElement(), { key: 'Enter' });
  h.dispatch('rt03:public:after-swap');
  await h.flush();
  assert.equal(h.audio.playCalls, 1);
  assert.equal(h.audio.paused, true);
  h.dispatch('click', h.wrappers[0].button);
  await h.flush();
  assert.equal(h.audio.playCalls, 2);
  assert.equal(h.audio.paused, false);
});

test('buffering is reported instead of falsely claiming to be audible; pause still works', async () => {
  const h = harness();
  await h.flush();
  h.audio.emit('waiting');
  assert.match(h.wrappers[0].status.textContent, /Menunggu audio/);
  assert.equal(h.wrappers[1].classes.has('is-buffering'), true);
  h.dispatch('click', h.wrappers[0].button);
  assert.equal(h.audio.paused, true);
  assert.equal(h.wrappers[1].classes.has('is-buffering'), false);
  assert.equal(h.wrappers[0].status.textContent, 'Musik dijeda');
});

test('media failure shows actionable error and never loops background retries', async () => {
  const h = harness(['error']);
  await h.flush();
  assert.equal(h.wrappers[1].classes.has('has-error'), true);
  assert.match(h.wrappers[0].status.textContent, /Audio tidak dapat diputar/);
  h.dispatch('click');
  h.dispatch('keydown', new h.FakeElement(), { key: 'Enter' });
  await h.flush();
  assert.equal(h.audio.playCalls, 1);
});

test('ended track is not restarted by navigation but can be replayed explicitly', async () => {
  const h = harness();
  await h.flush();
  h.audio.paused = true;
  h.audio.ended = true;
  h.audio.emit('ended');
  h.dispatch('pointerdown');
  h.dispatch('click');
  h.dispatch('rt03:public:after-swap');
  await h.flush();
  assert.equal(h.audio.playCalls, 1);
  assert.equal(h.wrappers[0].button.label.textContent, 'Ulang');
  h.dispatch('click', h.wrappers[0].button);
  await h.flush();
  assert.equal(h.audio.playCalls, 2);
  assert.equal(h.audio.currentTime, 0);
  assert.equal(h.audio.paused, false);
});

test('player controls in newly swapped main are synchronized without replacing audio', async () => {
  const h = harness(['block', 'success']);
  await h.flush();
  const another = new h.FakeWrapper();
  h.wrappers.push(another);
  h.dispatch('rt03:public:after-swap');
  assert.equal(another.classes.has('is-awaiting-gesture'), true);
  h.dispatch('click', another.button);
  await h.flush();
  assert.equal(h.audio.paused, false);
  assert.equal(another.button.attributes['aria-pressed'], 'true');
  assert.equal(h.audio.playCalls, 2);
});
