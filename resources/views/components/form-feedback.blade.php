@php
    $rt03Rejection = session('rt03_rejection');
    $rt03Invalid = $errors->any();
    $rt03AutoOpen = $rt03Invalid || is_array($rt03Rejection);
    $rt03ErrorMessages = $errors->getBag('default')->getMessages();
@endphp
<dialog class="rt03-feedback-dialog" data-rt03-feedback-dialog data-lenis-prevent
        data-rt03-auto-open="{{ $rt03AutoOpen ? '1' : '0' }}"
        data-rt03-feedback-form-target="{{ old('_rt03_form_target', '') }}"
        aria-labelledby="rt03-feedback-title"
        aria-describedby="rt03-feedback-description">
  <div class="rt03-feedback-shell">
    <div class="rt03-feedback-head">
      <span class="rt03-feedback-brand">PANITIA HUB <span>/</span> PEMBERITAHUAN</span>
      <button class="rt03-feedback-close" type="button" data-rt03-feedback-close aria-label="Tutup pemberitahuan">
        <i data-lucide="x" aria-hidden="true"></i>
      </button>
    </div>
    <div class="rt03-feedback-emblem" aria-hidden="true"><i data-lucide="shield-alert"></i></div>
    <div class="rt03-feedback-eyebrow">PERIKSA DATA</div>
    <h2 id="rt03-feedback-title" data-rt03-feedback-title>
      {{ is_array($rt03Rejection) ? ($rt03Rejection['title'] ?? 'Tindakan belum dapat diproses') : 'Data belum sesuai' }}
    </h2>
    <p id="rt03-feedback-description" data-rt03-feedback-description>
      @if(is_array($rt03Rejection))
        {{ $rt03Rejection['reason'] ?? 'Permintaan tidak dapat diproses saat ini.' }}
      @else
        {{ $rt03Invalid ? 'Ada isian yang perlu diperbaiki. Tidak ada data baru yang disimpan oleh formulir ini.' : 'Periksa isian dan aturan kegiatan sebelum melanjutkan.' }}
      @endif
    </p>
    <div class="rt03-feedback-list-wrap" data-rt03-feedback-list-wrap @if(!count($rt03ErrorMessages)) hidden @endif>
      <div class="rt03-feedback-list-title">DETAIL KESALAHAN</div>
      <ul class="rt03-feedback-list" data-rt03-feedback-list>
        @foreach($rt03ErrorMessages as $field => $messages)
          @foreach($messages as $message)
            <li class="rt03-feedback-list-item" data-rt03-feedback-field="{{ $field }}">
              <span class="rt03-feedback-marker" aria-hidden="true">!</span>
              <div>
                <strong>{{ \App\Support\FormFeedback::label($field) }}</strong>
                <span>{{ $message }}</span>
                <small>Saran: {{ \App\Support\FormFeedback::suggestion($field, $message) }}</small>
              </div>
            </li>
          @endforeach
        @endforeach
      </ul>
    </div>
    <div class="rt03-feedback-tip">
      <i data-lucide="lightbulb" aria-hidden="true"></i>
      <div>
        <strong>SARAN PERBAIKAN</strong>
        <p data-rt03-feedback-suggestion>
          {{ is_array($rt03Rejection) ? ($rt03Rejection['suggestion'] ?? 'Periksa kembali aturan kegiatan, lalu ulangi tindakan.') : 'Periksa kolom yang ditandai, pastikan data sesuai divisi, kuota, dan izin akun.' }}
        </p>
      </div>
    </div>
    <div class="rt03-feedback-actions">
      <button type="button" class="rt03-feedback-button rt03-feedback-secondary" data-rt03-feedback-close>Tutup</button>
      <button type="button" class="rt03-feedback-button rt03-feedback-primary" data-rt03-feedback-edit>Perbaiki data</button>
    </div>
    <p class="rt03-feedback-foot">RT 03 · BERSAMA, BERGERAK, BERKARYA</p>
  </div>
</dialog>
