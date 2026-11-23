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
  }, 5000);
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


// PANITIA HUB: local-only preview of selected avatar. Upload still requires
// an explicit save and server-side validation; private photo routes stay auth-only.
const profilePhotoPicker = document.querySelector('[data-profile-photo-input]');
const profilePhotoPreview = document.querySelector('[data-profile-photo-preview]');
const profilePhotoName = document.querySelector('[data-profile-photo-name]');
let avatarObjectUrl = null;

profilePhotoPicker?.addEventListener('change', () => {
  if (avatarObjectUrl) {
    URL.revokeObjectURL(avatarObjectUrl);
    avatarObjectUrl = null;
  }
  profilePhotoPicker.setCustomValidity('');
  const photo = profilePhotoPicker.files?.[0];
  if (!photo) return;

  if (!['image/jpeg', 'image/png', 'image/webp'].includes(photo.type)
      || photo.size > 2 * 1024 * 1024) {
    profilePhotoPicker.setCustomValidity('Gunakan JPG, PNG atau WebP maksimal 2 MB.');
    profilePhotoPicker.reportValidity();
    profilePhotoPicker.value = '';
    return;
  }

  avatarObjectUrl = URL.createObjectURL(photo);
  if (profilePhotoPreview) {
    profilePhotoPreview.src = avatarObjectUrl;
    profilePhotoPreview.hidden = false;
  }
  if (profilePhotoName) profilePhotoName.textContent = photo.name;
});

window.addEventListener('pagehide', () => {
  if (avatarObjectUrl) URL.revokeObjectURL(avatarObjectUrl);
}, { once: true });

// Password visibility is strictly local UI state; never persisted or logged.
document.querySelectorAll('[data-toggle-password]').forEach(button => {
  button.addEventListener('click', () => {
    const input = button.parentElement?.querySelector('input');
    if (!input) return;
    const reveal = input.type === 'password';
    input.type = reveal ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(reveal));
    button.setAttribute('aria-label', reveal ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
  });
});
