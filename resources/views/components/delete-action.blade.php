@props(['resource', 'record', 'label' => 'Hapus'])
@php
    $isPhoto = $resource === 'gallery';
    $isRole = $resource === 'members';
    $title = $isRole ? 'Cabut jabatan panitia?' : ($isPhoto ? 'Hapus foto kegiatan?' : 'Hapus data ini?');
    $description = $isRole
        ? 'Penugasan ini akan dinonaktifkan. Akun panitia dan data kegiatan lainnya tetap tersimpan.'
        : ($isPhoto
            ? 'Foto akan dihapus dari galeri dan portal warga, beserta berkas yang terkait. Tindakan ini tidak dapat dibatalkan.'
            : 'Data akan dihapus jika tidak memiliki relasi yang dilindungi. Berkas terkait dapat ikut terhapus. Tindakan ini tidak dapat dibatalkan.');
@endphp
<form method="POST"
      action="{{ route('records.destroy', ['resource' => $resource, 'id' => $record->getKey()]) }}"
      class="delete-form"
      data-rt03-confirm
      data-confirm-title="{{ $title }}"
      data-confirm-message="{{ $description }}"
      data-confirm-action="{{ $isRole ? 'Cabut jabatan' : ($isPhoto ? 'Hapus foto' : 'Ya, hapus') }}"
      data-confirm-variant="danger">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-delete btn-small" aria-label="{{ $label }}" disabled>
        <i data-lucide="trash-2" aria-hidden="true"></i> {{ $label }}
    </button>
</form>
