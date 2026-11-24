import Alpine from 'alpinejs';
import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { SplitText } from 'gsap/SplitText';
import { Flip } from 'gsap/Flip';
import Lenis from 'lenis';
// Import only icons used by Blade templates. Importing the complete icon
// catalog adds hundreds of KB of unnecessary JavaScript on every page.
import {
  createIcons,
  CircleAlert, Archive, ArrowLeft, ArrowRight, ArrowUpRight, Award,
  Book, BookOpen, Calendar, CalendarDays, CircleCheck, SquareCheckBig,
  ChevronRight, ChevronDown, Contact, LayoutDashboard, Download,
  ExternalLink, Flag, Folder, Gift, Images, Info, Lock, LogOut,
  Megaphone, Menu, MessageCircle, Notebook, Package, Plus, Receipt,
  Settings, ShieldAlert, Trash2, Trophy, CircleUser, Users, Wallet, X,
  GraduationCap, HeartHandshake, ImageOff, BadgeCheck, Camera, Clock,
  Eye, IdCard, Inbox, KeyRound, LockKeyhole, MapPin, Save, Shield,
  ShieldCheck, Upload, UsersRound, ClipboardList,
} from 'lucide';

const icons = {
  AlertCircle: CircleAlert,
  Archive, ArrowLeft, ArrowRight, ArrowUpRight, Award,
  Book, BookOpen, Calendar, CalendarDays,
  CheckCircle2: CircleCheck,
  CheckSquare: SquareCheckBig,
  ChevronRight, ChevronDown, Contact, Dashboard: LayoutDashboard,
  Download, ExternalLink, Flag, Folder, Gift, Images, Info, Lock,
  LogOut, Megaphone, Menu, Message: MessageCircle, Notebook, Package,
  Plus, Receipt, Settings, ShieldAlert, Trash2, Trophy,
  UserCircle: CircleUser, Users, Wallet, X, GraduationCap,
  HeartHandshake, ImageOff, BadgeCheck, Camera, Clock, Eye, IdCard,
  Inbox, KeyRound, LockKeyhole, MapPin, Save, Shield, ShieldCheck,
  Upload, UsersRound, ClipboardList,
};
import './public-navigation.js';
import './workspace-navigation.js';

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
  if (document.body.classList.contains('public-body')) window.rt03PublicLenis = lenis;
  if (document.body.classList.contains('app-body')) window.rt03WorkspaceLenis = lenis;
  lenis.on('scroll', ScrollTrigger.update);
  gsap.ticker.add((time) => lenis.raf(time * 1000));
  gsap.ticker.lagSmoothing(0);
  window.addEventListener('beforeunload', () => lenis.destroy(), { once: true });
}

// Keep reveal animations working after content-only public navigation.
// Old SplitText observers and ScrollTriggers are disposed before the swap.
const rt03PublicSplits = new Set();
function rt03AnimateReveals(root = document, fast = false) {
  if (reduceMotion) return;
  root.querySelectorAll('[data-word-reveal]').forEach(el => {
    if (el.dataset.rt03Animated) return;
    el.dataset.rt03Animated = '1';
    const split = SplitText.create(el, {
      type: 'words',
      autoSplit: true,
      onSplit(self) {
        return gsap.from(self.words, {
          opacity: 0, y: fast ? 7 : 22,
          duration: fast ? .18 : .7,
          stagger: fast ? .006 : .045,
          ease: 'power3.out',
          scrollTrigger: { trigger: el, start: 'top 92%', once: true },
        });
      },
    });
    if (document.body.classList.contains('public-body')) rt03PublicSplits.add(split);
  });
  root.querySelectorAll('[data-reveal]').forEach(el => {
    if (el.dataset.rt03Animated) return;
    el.dataset.rt03Animated = '1';
    gsap.fromTo(el, { autoAlpha: 0, y: fast ? 8 : 23 }, {
      autoAlpha: 1, y: 0, duration: fast ? .24 : .78, ease: 'power3.out',
      scrollTrigger: { trigger: el, start: 'top 91%', once: true },
    });
  });
  root.querySelectorAll('[data-counter]').forEach(el => {
    if (el.dataset.rt03Animated) return;
    el.dataset.rt03Animated = '1';
    const total = Number(el.dataset.counter || 0);
    if (!Number.isFinite(total) || total === 0) return;
    const state = { number: 0 };
    gsap.to(state, {
      number: total, duration: fast ? .28 : 1.0, ease: 'power2.out',
      scrollTrigger: { trigger: el, start: 'top 95%', once: true },
      onUpdate: () => { el.textContent = String(Math.round(state.number)); },
    });
  });
  root.querySelectorAll('[data-stagger]').forEach(el => {
    if (el.dataset.rt03Animated) return;
    el.dataset.rt03Animated = '1';
    const cards = el.querySelectorAll('.reveal-child');
    if (!cards.length) return;
    gsap.fromTo(cards, { autoAlpha: 0, y: fast ? 8 : 22 }, {
      autoAlpha: 1, y: 0, duration: fast ? .24 : .65,
      stagger: fast ? .012 : .065, ease: 'power2.out',
      scrollTrigger: { trigger: el, start: 'top 91%', once: true },
    });
  });
}

if (!reduceMotion) {
  if (document.fonts) {
    // Fast navigation never waits for this promise. It only gates initial
    // typography measuring for GSAP's SplitText.
    document.fonts.ready.then(() => rt03AnimateReveals());
  } else {
    rt03AnimateReveals();
  }
}

if (document.body.classList.contains('public-body')) {
  document.addEventListener('rt03:public:before-swap', () => {
    if (reduceMotion) return;
    ScrollTrigger.getAll().forEach(trigger => trigger.kill());
    rt03PublicSplits.forEach(split => split.revert());
    rt03PublicSplits.clear();
  });

  document.addEventListener('rt03:public:after-swap', () => {
    activateIcons();
    rt03AnimateReveals(document.querySelector('#main'), true);
    if (!reduceMotion) {
      ScrollTrigger.refresh();
    }
    window.rt03PublicLenis?.resize();
  });
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

// Delegated listeners survive in-workspace navigation and keep all page
// controls functional after Alpine renders fresh form/modal markup.
function rt03FilterResultParticipants(form) {
  const competition = form?.querySelector('[data-result-competition]');
  const participant = form?.querySelector('[data-result-participant]');
  if (!competition || !participant) return;
  const chosen = competition.value;
  for (const option of participant.options) {
    if (!option.value) continue;
    option.hidden = option.dataset.competitionId !== chosen;
    option.disabled = option.hidden;
  }
  if (participant.selectedOptions[0]?.disabled) participant.value = '';
}

function rt03InitWorkspaceForms() {
  document.querySelectorAll('[data-result-competition]').forEach(select => {
    rt03FilterResultParticipants(select.closest('form'));
  });
}
rt03InitWorkspaceForms();

document.addEventListener('change', event => {
  const target = event.target;
  if (!(target instanceof Element)) return;
  if (target.matches('[data-result-competition]')) {
    const form = target.closest('form');
    const participant = form?.querySelector('[data-result-participant]');
    if (participant) participant.value = '';
    rt03FilterResultParticipants(form);
    return;
  }
  if (!target.matches('[data-profile-photo-input]')) return;
  const input = target;
  if (window.rt03AvatarObjectUrl) {
    URL.revokeObjectURL(window.rt03AvatarObjectUrl);
    window.rt03AvatarObjectUrl = null;
  }
  input.setCustomValidity('');
  const file = input.files?.[0];
  if (!file) return;
  if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
    || file.size > 2 * 1024 * 1024) {
    input.setCustomValidity('Gunakan JPG, PNG atau WebP maksimal 2 MB.');
    input.reportValidity();
    input.value = '';
    return;
  }
  const preview = document.querySelector('[data-profile-photo-preview]');
  const name = document.querySelector('[data-profile-photo-name]');
  window.rt03AvatarObjectUrl = URL.createObjectURL(file);
  if (preview) {
    preview.src = window.rt03AvatarObjectUrl;
    preview.hidden = false;
  }
  if (name) name.textContent = file.name;
});

document.addEventListener('click', event => {
  const button = event.target instanceof Element ? event.target.closest('[data-toggle-password]') : null;
  if (button) {
    const input = button.parentElement?.querySelector('input');
    if (!input) return;
    const visible = input.type === 'password';
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', String(visible));
    button.setAttribute('aria-label', visible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    return;
  }

  const summary = event.target instanceof Element ? event.target.closest('.guide-card summary') : null;
  if (summary && !reduceMotion) {
    const card = summary.closest('.guide-card');
    if (!card) return;
    event.preventDefault();
    const state = Flip.getState(card);
    card.open = !card.open;
    Flip.from(state, { duration: .25, ease: 'power2.out', scale: false, absolute: false });
  }
});

function rt03ClearAvatarObjectUrl() {
  if (window.rt03AvatarObjectUrl) {
    URL.revokeObjectURL(window.rt03AvatarObjectUrl);
    window.rt03AvatarObjectUrl = null;
  }
}
window.addEventListener('pagehide', rt03ClearAvatarObjectUrl);
document.addEventListener('rt03:workspace:before-swap', () => {
  rt03ClearAvatarObjectUrl();
  if (!reduceMotion) {
    // Discard ScrollTriggers for the detached page. Without this, visiting
    // many menus accumulates listeners and forces repeated layout work.
    ScrollTrigger.getAll().forEach(trigger => trigger.kill());
  }
});
document.addEventListener('rt03:workspace:after-swap', () => {
  activateIcons();
  rt03InitWorkspaceForms();
  document.querySelectorAll('form[data-rt03-confirm] button[type="submit"]').forEach(button => {
    button.disabled = false;
  });
  if (!reduceMotion) {
    rt03AnimateReveals(document.querySelector('.app-main > main.page-content'), true);
    ScrollTrigger.refresh();
  }
  window.rt03WorkspaceLenis?.resize();
});

// One branded, accessible confirmation dialog for all committee actions.
// No native window.confirm/alert/prompt, no direct form.submit() bypass, no
// dependency on specific controller routes: Laravel enforces permissions.
const rt03ConfirmDialog = document.querySelector('[data-rt03-confirm-dialog]');
if (rt03ConfirmDialog && typeof rt03ConfirmDialog.showModal === 'function') {
  const rt03ConfirmTitle = rt03ConfirmDialog.querySelector('[data-rt03-confirm-title]');
  const rt03ConfirmMessage = rt03ConfirmDialog.querySelector('[data-rt03-confirm-message]');
  const rt03ConfirmProceed = rt03ConfirmDialog.querySelector('[data-rt03-confirm-proceed]');
  const rt03ConfirmCancel = rt03ConfirmDialog.querySelector('.rt03-confirm-button--cancel');
  let rt03PendingSubmission = null;
  let rt03ApprovedForm = null;
  let rt03ReturnFocus = null;

  // Fail closed: destructive/period actions stay disabled when JS or dialog
  // cannot initialize. No accidental direct submit without confirmation.
  document.querySelectorAll('form[data-rt03-confirm] button[type="submit"]').forEach(button => {
    button.disabled = false;
  });

  document.addEventListener('submit', event => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.matches('[data-rt03-confirm]')) return;
    if (rt03ApprovedForm === form) return;
    event.preventDefault();
    if (rt03ConfirmDialog.open) return;

    const submitter = event.submitter;
    rt03PendingSubmission = {
      form,
      submitter: submitter?.form === form ? submitter : null,
    };
    rt03ReturnFocus = submitter?.form === form ? submitter : document.activeElement;
    rt03ConfirmTitle.textContent = form.dataset.confirmTitle || 'Konfirmasi tindakan';
    rt03ConfirmMessage.textContent = form.dataset.confirmMessage || 'Lanjutkan tindakan ini?';
    rt03ConfirmProceed.textContent = form.dataset.confirmAction || 'Lanjutkan';
    rt03ConfirmDialog.dataset.variant = form.dataset.confirmVariant === 'warning' ? 'warning' : 'danger';
    rt03ConfirmDialog.showModal();
    rt03ConfirmCancel?.focus({ preventScroll: true });
  });

  rt03ConfirmDialog.querySelectorAll('[data-rt03-confirm-cancel]').forEach(button => {
    button.addEventListener('click', () => rt03ConfirmDialog.close('cancel'));
  });

  rt03ConfirmDialog.addEventListener('click', event => {
    if (event.target !== rt03ConfirmDialog) return;
    const box = rt03ConfirmDialog.getBoundingClientRect();
    if (event.clientX < box.left || event.clientX > box.right
        || event.clientY < box.top || event.clientY > box.bottom) {
      rt03ConfirmDialog.close('cancel');
    }
  });

  rt03ConfirmDialog.addEventListener('close', () => {
    rt03PendingSubmission = null;
    if (rt03ReturnFocus?.isConnected) rt03ReturnFocus.focus({ preventScroll: true });
    rt03ReturnFocus = null;
  });

  rt03ConfirmProceed?.addEventListener('click', () => {
    if (!rt03PendingSubmission) return;
    const { form, submitter } = rt03PendingSubmission;
    rt03PendingSubmission = null;
    rt03ConfirmDialog.close('confirm');

    // Re-trigger native HTML validation and the actual submit event, while
    // bypassing only this one confirmation. CSRF/method inputs are retained.
    rt03ApprovedForm = form;
    form.dataset.rt03Confirmed = '1';
    try {
      if (submitter && !submitter.disabled) form.requestSubmit(submitter);
      else form.requestSubmit();
    } finally {
      delete form.dataset.rt03Confirmed;
      rt03ApprovedForm = null;
    }
  });
}
