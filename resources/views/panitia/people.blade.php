@extends('layouts.app')
@section('title','Struktur Panitia')
@section('content')
<div class="page-head">
  <div>
    <span class="eyebrow">ORGANISASI / PERIODE {{ $period->year }}</span>
    <h1>Orang-orang di balik layar<span class="text-red">.</span></h1>
    <p>Setiap akun punya ID login unik. Jabatan, divisi, dan akses tetap mengikuti periode aktif.</p>
  </div>
  @if(\App\Support\Access::can(auth()->user(),'manage-members'))
  <x-entry-modal title="Tambah akun panitia" label="Tambah panitia">
    <form method="post" action="{{ route('people.store') }}" class="form-stack">
      @csrf
      <label class="field"><span>Nama lengkap</span><input name="name" value="{{ old('name') }}" required maxlength="120"></label>
      <label class="field"><span>Email (untuk informasi akun)</span><input type="email" name="email" value="{{ old('email') }}" required maxlength="190"></label>
      <label class="field">
        <span>Kata sandi awal (minimal 8 karakter)</span>
        <span class="rt03-profile-password-field">
          <input type="password" name="password" minlength="8" required autocomplete="new-password">
          <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false" title="Lihat kata sandi"><i data-lucide="eye" aria-hidden="true"></i></button>
        </span>
      </label>
      <div class="form-grid">
        <label class="field"><span>Jabatan</span><select name="role" required>
          @foreach(['ketua'=>'Ketua','wakil'=>'Wakil Ketua','sekretaris'=>'Sekretaris','bendahara'=>'Bendahara','koordinator'=>'Koordinator','anggota'=>'Anggota','mentor'=>'Senior / Mentor','pengurus_rt'=>'Pengurus RT'] as $role=>$label)
          <option value="{{ $role }}" @selected(old('role')===$role)>{{ $label }}</option>
          @endforeach
        </select></label>
        <label class="field"><span>Divisi (wajib untuk koordinator / anggota)</span><select name="division_id">
          <option value="">Tanpa divisi</option>
          @foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string)old('division_id')===(string)$division->id)>{{ $division->name }}</option>@endforeach
        </select></label>
      </div>
      <p class="form-hint">ID Login dibuat otomatis setelah akun disimpan. Berikan ID dan kata sandi awal secara pribadi; kata sandi asli tidak dapat dilihat kembali oleh admin.</p>
      <button type="submit" class="btn btn-red btn-block">Buat akun</button>
    </form>
  </x-entry-modal>
  @endif
</div>

@if(\App\Support\Access::can(auth()->user(),'manage-members'))
<div class="rt03-people-actions">
  <x-entry-modal title="Tambah jabatan ke akun" label="Tetapkan jabatan">
    <form method="post" action="{{ route('people.assign') }}" class="form-stack">
      @csrf
      <label class="field"><span>Akun anggota</span><select name="user_id" required>
        @foreach($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->login_id }})</option>@endforeach
      </select></label>
      <label class="field"><span>Jabatan tambahan</span><select name="role" required>
        @foreach(['ketua'=>'Ketua','wakil'=>'Wakil','sekretaris'=>'Sekretaris','bendahara'=>'Bendahara','koordinator'=>'Koordinator','anggota'=>'Anggota','mentor'=>'Mentor','pengurus_rt'=>'Pengurus RT'] as $role=>$label)
        <option value="{{ $role }}">{{ $label }}</option>@endforeach
      </select></label>
      <label class="field"><span>Divisi (jika koordinator/anggota)</span><select name="division_id">
        <option value="">Tanpa divisi</option>
        @foreach($divisions as $division)<option value="{{ $division->id }}">{{ $division->name }}</option>@endforeach
      </select></label>
      <button type="submit" class="btn btn-red btn-block">Simpan jabatan</button>
    </form>
  </x-entry-modal>
</div>
@endif

<div class="people-grid" data-stagger>
  @forelse($members as $member)
  <article class="person-card reveal-child">
    <div class="person-avatar">{{ mb_strtoupper(mb_substr($member->user?->name ?? 'P',0,1)) }}</div>
    <div>
      <span class="eyebrow">{{ strtoupper(str_replace('_',' ',$member->role)) }}</span>
      <h2>{{ $member->user?->name }}</h2>
      <p>{{ $member->division?->name ?? 'Pengurus inti / Pendamping' }}</p>
      @if(\App\Support\Access::can(auth()->user(),'manage-members') && $member->user)
        <p class="rt03-member-login-id">ID: {{ $member->user->login_id }}</p>
      @endif
    </div>
    @if(\App\Support\Access::can(auth()->user(),'manage-members') && $member->user_id !== auth()->id())
      <x-delete-action resource="members" :record="$member" label="Cabut jabatan" />
    @endif
    <span class="person-edge"></span>
  </article>
  @empty
  <div class="empty-state empty-wide">Belum ada anggota yang ditugaskan. Tambahkan akun panitia.</div>
  @endforelse
</div>

@if(auth()->user()->is_super_admin && $accounts)
<section class="surface-panel table-panel rt03-account-directory">
  <div class="panel-heading">
    <div><span class="eyebrow">HANYA SUPER ADMIN</span><h2>Manajemen akun & reset kata sandi</h2></div>
    <span class="muted-small">{{ $accounts->total() }} akun terdaftar</span>
  </div>
  <p class="rt03-account-guidance">Semua akun memiliki ID login dan email. Password asli tidak dapat dilihat karena disimpan sebagai hash. Super Admin dapat menetapkan password baru setelah mengonfirmasi kata sandinya sendiri.</p>
  <div class="table-scroll"><table class="data-table">
    <thead><tr><th>ID LOGIN</th><th>NAMA / EMAIL</th><th>STATUS</th><th>KEAMANAN AKUN</th></tr></thead>
    <tbody>
      @foreach($accounts as $account)
      <tr>
        <td><strong class="rt03-account-code">{{ $account->login_id }}</strong></td>
        <td><strong>{{ $account->name }}</strong><span class="table-subtext">{{ $account->email }}</span></td>
        <td><span class="status {{ $account->is_active ? 'status-done' : 'status-blocked' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span>@if($account->is_super_admin)<span class="rt03-account-badge">SUPER ADMIN</span>@endif</td>
        <td>
          @if($account->id === auth()->id())
            <span class="muted-small">Akun sendiri: ganti melalui Profil Saya</span>
          @else
            <details class="inline-details rt03-account-reset">
              <summary>Reset kata sandi</summary>
              <form method="post" action="{{ route('people.password.reset', $account) }}" class="form-stack rt03-account-reset-form">
                @csrf
                @method('PATCH')
                <p class="rt03-account-reset-explain">Mengganti kata sandi <strong>{{ $account->login_id }}</strong>. Akun tersebut perlu login kembali jika masih aktif di perangkat lain.</p>
                <label class="field"><span>Kata sandi Super Admin saat ini</span><span class="rt03-profile-password-field">
                  <input type="password" name="current_password" required autocomplete="current-password">
                  <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false" title="Lihat kata sandi"><i data-lucide="eye" aria-hidden="true"></i></button>
                </span></label>
                <label class="field"><span>Kata sandi baru (minimal 8 karakter)</span><span class="rt03-profile-password-field">
                  <input type="password" name="password" required minlength="8" autocomplete="new-password">
                  <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false" title="Lihat kata sandi"><i data-lucide="eye" aria-hidden="true"></i></button>
                </span></label>
                <label class="field"><span>Konfirmasi kata sandi baru</span><span class="rt03-profile-password-field">
                  <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
                  <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false" title="Lihat kata sandi"><i data-lucide="eye" aria-hidden="true"></i></button>
                </span></label>
                <button type="submit" class="btn btn-red btn-small">Simpan password baru</button>
              </form>
            </details>
          @endif
        </td>
      </tr>
      @endforeach
    </tbody>
  </table></div>
  <div class="pagination-row">{{ $accounts->links() }}</div>
</section>
@endif
@endsection
