/**
 * Shared RT03 form feedback for the entire internal workspace and login.
 * Server validation and HTML5 invalid inputs use the same branded dialog.
 * Do not intercept valid requests, modify permissions, or submit forms.
 */
const MAX_VISIBLE_ERRORS = 6;
let recentInvalids = new Map();
let invalidScheduled = false;
let pendingFocus = null;

const feedbackDialog = () => document.querySelector('[data-rt03-feedback-dialog]');

function friendlyLabel(field) {
  const names = {
    name: 'Nama', full_name: 'Nama lengkap', title: 'Judul',
    email: 'Alamat email', password: 'Kata sandi',
    password_confirmation: 'Konfirmasi kata sandi',
    assigned_to: 'Penanggung jawab (PIC)', division_id: 'Divisi',
    competition_id: 'Lomba', participant_id: 'Peserta',
    user_id: 'Panitia', role: 'Jabatan', amount: 'Nominal',
    amount_requested: 'Nominal pengajuan', quantity: 'Jumlah',
    quantity_total: 'Jumlah stok', image: 'Foto', photo: 'Foto profil',
    file: 'Berkas', proof: 'Bukti', year: 'Tahun', due_date: 'Tenggat',
    event_date: 'Tanggal acara', transaction_date: 'Tanggal transaksi',
    phone: 'Nomor telepon', content: 'Isi', status: 'Status',
  };
  return names[field] || field.replace(/[_\[\].]/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
}

function controlLabel(input) {
  const explicit = input.labels?.[0]?.querySelector('span')?.textContent?.trim();
  const label = explicit || input.labels?.[0]?.textContent?.trim();
  return label || friendlyLabel(input.name || 'data');
}

function clientReason(input) {
  const v = input.validity;
  if (v.valueMissing) return 'Kolom ini wajib diisi sebelum data dapat disimpan.';
  if (v.typeMismatch) return 'Format nilai belum benar.';
  if (v.tooLong) return \`Teks terlalu panjang. Maksimal \${input.maxLength} karakter.\`;
  if (v.tooShort) return \`Teks terlalu pendek. Minimal \${input.minLength} karakter.\`;
  if (v.rangeUnderflow) return \`Nilai harus minimal \${input.min}.\`;
  if (v.rangeOverflow) return \`Nilai tidak boleh melebihi \${input.max}.\`;
  if (v.stepMismatch || v.badInput) return 'Gunakan angka atau nilai yang sesuai ketentuan kolom.';
  if (v.patternMismatch) return 'Format isian belum sesuai ketentuan kolom.';
  return 'Nilai belum sesuai dengan ketentuan formulir.';
}

function clientAdvice(input) {
  const name = (input.name || '').toLowerCase();
  if (input.type === 'email') return 'Gunakan format, misalnya panitia@email.com.';
  if (input.type === 'file') return 'Periksa format dan ukuran berkas yang diizinkan.';
  if (name.includes('password')) return 'Gunakan kata sandi yang benar, lalu cocokkan konfirmasinya.';
  if (name.includes('division') || name.includes('assigned') || name.includes('role')) {
    return 'Pilih panitia, jabatan, dan divisi yang aktif pada periode ini.';
  }
  if (input.tagName === 'SELECT') return 'Pilih salah satu opsi yang tersedia sebelum melanjutkan.';
  if (input.type === 'number') return 'Gunakan angka yang memenuhi batas minimum dan maksimum.';
  if (input.type === 'date' || input.type === 'datetime-local') {
    return 'Pilih tanggal dan waktu yang valid sesuai jadwal kegiatan.';
  }
  return 'Lengkapi atau perbaiki nilai pada kolom tersebut, lalu coba lagi.';
}

function createErrorItem(field, reason, suggestion) {
  const li = document.createElement('li');
  li.className = 'rt03-feedback-list-item';
  li.dataset.rt03FeedbackField = field;
  const marker = document.createElement('span');
  marker.className = 'rt03-feedback-marker';
  marker.setAttribute('aria-hidden', 'true');
  marker.textContent = '!';
  const box = document.createElement('div');
  const title = document.createElement('strong');
  title.textContent = friendlyLabel(field);
  const explanation = document.createElement('span');
  explanation.textContent = reason;
  const advice = document.createElement('small');
  advice.textContent = 'Saran: ' + suggestion;
  box.append(title, explanation, advice);
  li.append(marker, box);
  return li;
}

function selectedForm(dialog) {
  const forms = [...document.querySelectorAll('form')];
  const oldTarget = dialog.dataset.rt03FeedbackFormTarget;
  if (oldTarget) {
    try {
      const url = new URL(oldTarget, location.href);
      if (url.origin === location.origin) {
        const matched = forms.find(form => form.action === url.href);
        if (matched) return matched;
      }
    } catch { /* Ignore missing or invalid old form metadata. */ }
  }

  const field = dialog.querySelector('[data-rt03-feedback-field]')?.dataset.rt03FeedbackField;
  if (field) {
    const matches = forms.filter(form => [...form.elements].some(input => input.name === field));
    if (matches.length === 1) return matches[0];
  }
  return null;
}

function showDialog(dialog) {
  if (!dialog || dialog.open || typeof dialog.showModal !== 'function') return;
  const confirmation = document.querySelector('[data-rt03-confirm-dialog]');
  if (confirmation?.open) return;
  dialog.showModal();
  dialog.querySelector('[data-rt03-feedback-edit]')?.focus({ preventScroll: true });
}

function setupDialog(dialog) {
  if (!dialog || dialog.dataset.rt03FeedbackReady) return;
  dialog.dataset.rt03FeedbackReady = '1';
  dialog.addEventListener('click', event => {
    const button = event.target instanceof Element
      ? event.target.closest('[data-rt03-feedback-edit],[data-rt03-feedback-close]')
      : null;
    if (button) {
      const editing = button.hasAttribute('data-rt03-feedback-edit');
      const targetForm = editing ? (pendingFocus?.form || selectedForm(dialog)) : null;
      const field = editing ? (pendingFocus?.element
        || (targetForm && [...targetForm.elements].find(input =>
          input.name === dialog.querySelector('[data-rt03-feedback-field]')?.dataset.rt03FeedbackField))) : null;
      dialog.close('dismiss');
      if (editing && targetForm) {
        // Alpine x-teleport retains the parent modal's reactive scope.
        const scope = window.Alpine?.$data?.(targetForm);
        if (scope && Object.hasOwn(scope, 'open')) scope.open = true;
        window.setTimeout(() => {
          if (field?.isConnected && typeof field.focus === 'function') {
            field.focus({ preventScroll: true });
            field.scrollIntoView?.({ block: 'nearest', behavior: 'smooth' });
          } else {
            const input = targetForm.querySelector('input:not([type=hidden]),select,textarea');
            input?.focus({ preventScroll: true });
          }
        }, 80);
      }
      pendingFocus = null;
      return;
    }
    if (event.target === dialog) {
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right
        || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close('dismiss');
    }
  });
  dialog.addEventListener('close', () => { pendingFocus = null; });
  if (dialog.dataset.rt03AutoOpen === '1') {
    dialog.dataset.rt03AutoOpen = '0';
    requestAnimationFrame(() => showDialog(dialog));
  }
}

function displayClientErrors(entries, form) {
  const dialog = feedbackDialog();
  if (!dialog || !entries.length) return;
  setupDialog(dialog);
  const wrap = dialog.querySelector('[data-rt03-feedback-list-wrap]');
  const list = dialog.querySelector('[data-rt03-feedback-list]');
  if (!wrap || !list) return;
  list.replaceChildren(...entries.slice(0, MAX_VISIBLE_ERRORS).map(item =>
    createErrorItem(item.element.name || 'isian', item.reason, item.suggestion)));
  if (entries.length > MAX_VISIBLE_ERRORS) {
    const more = document.createElement('li');
    more.className = 'rt03-feedback-overflow';
    more.textContent = \`Dan \${entries.length - MAX_VISIBLE_ERRORS} kolom lainnya perlu diperbaiki.\`;
    list.append(more);
  }
  wrap.hidden = false;
  dialog.querySelector('[data-rt03-feedback-title]').textContent = 'Periksa isian formulir';
  dialog.querySelector('[data-rt03-feedback-description]').textContent =
    'Beberapa isian belum memenuhi ketentuan. Perbaiki kolom di bawah sebelum menyimpan.';
  dialog.querySelector('[data-rt03-feedback-suggestion]').textContent =
    'Pilih Perbaiki data, lengkapi kolom yang ditandai, lalu tekan Simpan kembali.';
  pendingFocus = { form, element: entries[0].element };
  showDialog(dialog);
}

// Native HTML5 validation runs BEFORE the submit event. Capture invalid
// events and replace only the browser bubble; never submit invalid data.
document.addEventListener('invalid', event => {
  const input = event.target;
  if (!(input instanceof HTMLElement) || !input.form) return;
  if (!feedbackDialog()) return;
  event.preventDefault();
  const form = input.form;
  if (!recentInvalids.has(form)) recentInvalids.set(form, []);
  const entries = recentInvalids.get(form);
  if (!entries.some(entry => entry.element === input)) {
    entries.push({ element: input, reason: clientReason(input), suggestion: clientAdvice(input) });
  }
  if (!invalidScheduled) {
    invalidScheduled = true;
    queueMicrotask(() => {
      invalidScheduled = false;
      const collected = recentInvalids;
      recentInvalids = new Map();
      const [targetForm, details] = collected.entries().next().value || [];
      if (targetForm && details) displayClientErrors(details, targetForm);
    });
  }
}, true);

// Remember exactly which form submitted, so validation redirects reopen the
// correct one even when a page has multiple "Tambah" modals. No credentials
// or field values are stored in this metadata.
document.addEventListener('submit', event => {
  const form = event.target;
  if (!(form instanceof HTMLFormElement) || form.method.toLowerCase() === 'get') return;
  let hidden = form.querySelector('input[name="_rt03_form_target"]');
  if (!hidden) {
    hidden = document.createElement('input');
    hidden.type = 'hidden';
    hidden.name = '_rt03_form_target';
    form.append(hidden);
  }
  hidden.value = form.action;
}, true);

function initializeFeedback() {
  setupDialog(feedbackDialog());
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initializeFeedback, { once: true });
} else {
  queueMicrotask(initializeFeedback);
}
document.addEventListener('rt03:workspace:after-swap', initializeFeedback);
