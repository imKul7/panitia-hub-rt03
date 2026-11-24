@props(['title'=>'Tambah data','label'=>'Tambah baru','openOnError'=>false])
<div x-data="{open: {{ $openOnError ? 'true' : 'false' }}}" class="entry-modal-wrap">
  <button type="button" class="btn btn-red" @click="open=true"><i data-lucide="plus"></i> {{ $label }}</button>
  <template x-teleport="body">
    <div x-show="open" x-transition.opacity class="modal-overlay" x-cloak @keydown.escape.window="open=false" @click.self="open=false" role="presentation">
      <section class="modal-dialog" role="dialog" aria-modal="true" aria-label="{{ $title }}" x-transition.scale.90>
        <div class="modal-heading"><div><span class="eyebrow">PANITIA HUB / FORMULIR</span><h2>{{ $title }}</h2></div><button type="button" class="icon-button" aria-label="Tutup" @click="open=false"><i data-lucide="x"></i></button></div>
        <div class="modal-content">{{ $slot }}</div>
      </section>
    </div>
  </template>
</div>
