import Alpine from 'alpinejs';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { Flip } from 'gsap/Flip';
import Lenis from 'lenis';
import { createIcons, icons } from 'lucide';

gsap.registerPlugin(ScrollTrigger, SplitText, Flip);
window.Alpine = Alpine;
Alpine.start();

function activateIcons() {
  createIcons({ icons, attrs: { 'stroke-width': 1.8 } });
}
activateIcons();

const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reduceMotion && window.matchMedia('(pointer:fine)').matches) {
  const lenis = new Lenis({ duration: 1.05, wheelMultiplier: 0.9, smoothWheel: true, prevent: node => node?.matches?.('.sidebar-nav') });
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add((time) => lenis.raf(time * 1000));
  gsap.ticker.lagSmoothing(0);
  window.addEventListener('beforeunload', () => lenis.destroy(), { once: true });
}

if (!reduceMotion) {
  // Text split by word, not by character: expressive but still readable.
  const animate = () => {
    document.querySelectorAll('[data-word-reveal]').forEach(el => {
      SplitText.create(el, {
        type: 'words',
        autoSplit: true,
        onSplit(self) {
          return gsap.from(self.words, {
            opacity: 0,
            y: 22,
            duration: 0.7,
            stagger: 0.045,
            ease: 'power3.out',
            scrollTrigger: { trigger: el, start: 'top 92%', once: true },
          });
        },
      });
    });
    document.querySelectorAll('[data-reveal]').forEach(el => {
      gsap.fromTo(el, { autoAlpha: 0, y: 23 }, {
        autoAlpha: 1, y: 0, duration: .78, ease: 'power3.out',
        scrollTrigger: { trigger: el, start: 'top 91%', once: true },
      });
    });
    document.querySelectorAll('[data-counter]').forEach(el => {
      const total = Number(el.dataset.counter || 0);
      if (!Number.isFinite(total) || total === 0) return;
      const state = { number: 0 };
      gsap.to(state, {
        number: total, duration: 1.0, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 95%', once: true },
        onUpdate: () => { el.textContent = String(Math.round(state.number)); },
      });
    });
    document.querySelectorAll('[data-stagger]').forEach(el => {
      const cards = el.querySelectorAll('.reveal-child');
      if (!cards.length) return;
      gsap.fromTo(cards, { autoAlpha: 0, y: 22 }, {
        autoAlpha: 1, y: 0, duration: .65, stagger: .065, ease: 'power2.out',
        scrollTrigger: { trigger: el, start: 'top 91%', once: true },
      });
    });
  };
  if (document.fonts) document.fonts.ready.then(animate);
  else animate();
}

// Hero login uses only actual user-provided local photos; nothing is fabricated.
const slides = Array.from(document.querySelectorAll('[data-login-slide]'));
if (slides.length > 1 && !reduceMotion) {
  let current = 0;
  setInterval(() => {
    slides[current].classList.remove('active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('active');
  }, 6500);
}

// Mobile navigation.
const mobileButton = document.querySelector('[data-mobile-menu]');
const publicNav = document.querySelector('[data-public-nav]');
mobileButton?.addEventListener('click', () => {
  const expanded = publicNav?.classList.toggle('is-open') ?? false;
  mobileButton.setAttribute('aria-expanded', String(expanded));
});

// Prevent choosing a participant enrolled in a different contest when entering results.
const resultCompetition = document.querySelector('[data-result-competition]');
const resultParticipant = document.querySelector('[data-result-participant]');
function filterResultParticipants() {
  if (!resultCompetition || !resultParticipant) return;
  resultParticipant.value = '';
  Array.from(resultParticipant.options).forEach(opt => {
    opt.hidden = Boolean(opt.value) && opt.dataset.competitionId !== resultCompetition.value;
  });
}
resultCompetition?.addEventListener('change', filterResultParticipants);
filterResultParticipants();

// Discreet FLIP layout motion for expanding handbook cards.
if (!reduceMotion) {
  document.querySelectorAll('.guide-card').forEach(card => {
    const summary = card.querySelector('summary');
    summary?.addEventListener('click', event => {
      event.preventDefault();
      const state = Flip.getState(card);
      card.open = !card.open;
      Flip.from(state, { duration: .34, ease: 'power2.out', scale: false, absolute: false });
    });
  });
}
