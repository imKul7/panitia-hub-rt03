@props(['resource', 'record', 'label' => 'Hapus'])
<form method="POST" action="{{ route('records.destroy', ['resource' => $resource, 'id' => $record->getKey()]) }}" class="delete-form" onsubmit="return confirm('Hapus data ini? Tindakan ini tidak dapat dibatalkan dan tercatat dalam log aktivitas.')">
    @csrf
    @method('DELETE')
    <button type="submit" class="btn btn-delete btn-small" aria-label="{{ $label }}"><i data-lucide="trash-2"></i> {{ $label }}</button>
</form>
