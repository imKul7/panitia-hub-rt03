/**
 * PANITIA HUB RT03 — resident anthem player.
 *
 * Try audible playback when the public site opens. Browsers can refuse
 * unmuted autoplay, so retry synchronously on the first trusted pointer or
 * keyboard activation anywhere in the public pages. No forced mute, hacks,
 * infinite retries, or bypasses of browser policy.
 *
 * The audio is in layouts/public.blade.php outside #main. Public navigation
 * swaps only #main, preserving playback and all progress across six pages.
 */
const audio = document.querySelector('body[data-rt03-public-router] > [data-rt03-anthem-audio]');

if (audio instanceof HTMLAudioElement) {
  audio.volume = 0.38;
  let autoWanted = true;
  let userPaused = false;
  let blocked = false;
  let failed = false;

  function isPlaying() {
    return !audio.paused && !audio.ended;
  }

  function updateControls() {
    const playing = isPlaying();
    let message;
    if (playing) message = 'Instrumental Indonesia Raya · sedang diputar';
    else if (failed || audio.error) message = 'Audio tidak dapat diputar. Periksa file MP3.';
    else if (audio.ended) message = 'Musik selesai · tekan Putar untuk mengulang';
    else if (userPaused) message = 'Musik dijeda';
    else if (blocked) message = 'Klik atau sentuh di mana saja untuk mulai';
    else message = 'Menyiapkan musik...';

    document.querySelectorAll('[data-rt03-anthem-player]').forEach(wrapper => {
      wrapper.classList.toggle('is-playing', playing);
      wrapper.querySelectorAll('[data-rt03-anthem-status]').forEach(el => {
        if (el.textContent !== message) el.textContent = message;
      });
      wrapper.querySelectorAll('[data-rt03-anthem-toggle]').forEach(button => {
        button.setAttribute('aria-pressed', String(playing));
        button.setAttribute('aria-label', playing ? 'Jeda musik instrumental' : 'Putar musik instrumental');
        const label = button.querySelector('[data-rt03-anthem-label]');
        const targetText = playing ? 'Jeda' : (audio.ended ? 'Ulang' : 'Putar');
        if (label && label.textContent !== targetText) label.textContent = targetText;
      });
    });
  }

  function startMusic(automatic) {
    if (automatic && (!autoWanted || userPaused || audio.ended || !audio.paused)) return;
    if (audio.error) {
      failed = true;
      autoWanted = false;
      updateControls();
      return;
    }

    // Call play() immediately inside the event, not in a timer/await callback:
    // the transient user activation can expire between tasks.
    let promise;
    try {
      promise = audio.play();
    } catch (error) {
      handleRejection(error, automatic);
      return;
    }
    if (promise && typeof promise.then === 'function') {
      void promise.then(() => {
        blocked = false;
        failed = false;
        autoWanted = false;
        updateControls();
      }).catch(error => handleRejection(error, automatic));
    } else {
      blocked = false;
      autoWanted = false;
      updateControls();
    }
  }

  function handleRejection(error, automatic) {
    // A stale play() attempt must not override a later successful play.
    if (isPlaying() || !audio.paused) return;

    if (error?.name === 'NotAllowedError' && automatic && autoWanted) {
      blocked = true;
      updateControls();
      return;
    }
    if (error?.name === 'AbortError') {
      // Rejected because media was paused/changed; no spurious error UI.
      updateControls();
      return;
    }
    blocked = false;
    if (error?.name === 'NotAllowedError') {
      // A manual click should normally permit playback; if not, keep the
      // instruction visible instead of pretending audio is playing.
      blocked = true;
      autoWanted = true;
    } else {
      failed = true;
      autoWanted = false;
    }
    updateControls();
  }

  const isMusicButton = event => event.target instanceof Element
    && Boolean(event.target.closest('[data-rt03-anthem-toggle]'));

  function retryFromGesture(event) {
    if (!event.isTrusted || !autoWanted || isMusicButton(event)) return;
    if (event.type === 'keydown' && !['Enter', ' ', 'Spacebar'].includes(event.key)) return;
    startMusic(true);
  }

  // Catch the user's first interaction, even if another component intercepts
  // or navigates the click later. Also support mobile touch and keyboard.
  document.addEventListener('pointerdown', retryFromGesture, true);
  document.addEventListener('click', event => {
    const button = event.target instanceof Element
      ? event.target.closest('[data-rt03-anthem-toggle]')
      : null;

    if (!button) {
      retryFromGesture(event);
      return;
    }

    event.preventDefault();
    autoWanted = false;
    blocked = false;
    failed = false;
    if (isPlaying()) {
      userPaused = true; // Subsequent unrelated clicks MUST NOT restart music.
      audio.pause();
    } else {
      userPaused = false;
      if (audio.ended) audio.currentTime = 0;
      startMusic(false);
    }
    updateControls();
  }, true);
  document.addEventListener('keydown', retryFromGesture, true);

  audio.addEventListener('play', () => {
    blocked = false;
    failed = false;
    autoWanted = false;
    updateControls();
  });
  audio.addEventListener('pause', updateControls);
  audio.addEventListener('ended', () => {
    autoWanted = false; // Do not loop/restart on an unrelated click.
    updateControls();
  });
  audio.addEventListener('error', () => {
    autoWanted = false;
    failed = true;
    updateControls();
  });

  // A new main from the instant public router contains fresh hero buttons.
  document.addEventListener('rt03:public:after-swap', updateControls);

  updateControls();
  startMusic(true); // Best effort when the page opens, before any click.
}
