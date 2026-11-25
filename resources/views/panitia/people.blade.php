@extends('layouts.app')
@section('title','Struktur Panitia')
@section('content')
<div class="page-head">
  <div>
    <span class="eyebrow">ORGANISASI / PERIODE {{ $period->year }}</span>
    <h1>Struktur panitia<span class="text-red">.</span></h1>
    <p>Anggota, jabatan, dan akses periode {{ $period->year }}.</p>
  </div>
  @if(\App\Support\Access::can(auth()->user(),'manage-members'))
  <x-entry-modal title="Tambah akun panitia" label="Tambah panitia">
    <form method="post" action="{{ route('people.store') }}" class="form-stack">
      @csrf
      <label class="field"><span>Nama lengkap</span><input name="name" value="{{ old('name') }}" required maxlength="120"></label>
      <label class="field"><span>Email</span><input type="email" name="email" value="{{ old('email') }}" required maxlength="190"></label>
      <label class="field">
        <span>Password awal (min. 8 karakter)</span>
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
        <label class="field"><span>Divisi (koordinator/anggota)</span><select name="division_id">
          <option value="">Tanpa divisi</option>
          @foreach($divisions as $division)<option value="{{ $division->id }}" @selected((string)old('division_id')===(string)$division->id)>{{ $division->name }}</option>@endforeach
        </select></label>
      </div>
      <p class="form-hint">ID RT03 dibuat otomatis. Bagikan ID dan password secara pribadi.</p>
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
<section class="surface-panel rt03-account-directory" aria-labelledby="rt03-accounts-title">
  <div class="rt03-account-compact-header">
    <div class="rt03-account-compact-title">
      <span class="rt03-account-compact-icon" aria-hidden="true"><i data-lucide="shield-check"></i></span>
      <div>
        <span class="eyebrow">SUPER ADMIN</span>
        <h2 id="rt03-accounts-title">Manajemen akun & reset kata sandi</h2>
        <p>ID otomatis · Password tersimpan aman · Reset oleh Super Admin</p>
      </div>
    </div>
    <span class="rt03-account-total">{{ $accounts->total() }} akun</span>
  </div>
  <div class="table-scroll">
    <table class="data-table rt03-account-table">
      <thead><tr><th>ID</th><th>Akun</th><th>Peran aktif</th><th>Status</th><th>Aksi</th></tr></thead>
      <tbody>
        @foreach($accounts as $account)
          <tr>
            <td><span class="rt03-account-code">{{ $account->login_id }}</span></td>
            <td>
              <strong>{{ $account->name }}</strong>
              <span class="table-subtext">{{ $account->email }}</span>
            </td>
            <td>
              @if($account->is_super_admin)
                <span class="rt03-account-badge">Super Admin</span>
              @else
                @forelse($account->memberships->take(2) as $member)
                  <span class="rt03-account-role">{{ ucwords(str_replace('_',' ', $member->role)) }}@if($member->division) · {{ $member->division->name }}@endif</span>
                @empty
                  <span class="rt03-account-role rt03-account-role--muted">Belum ditugaskan</span>
                @endforelse
                @if($account->memberships->count() > 2)
                  <span class="rt03-account-role rt03-account-role--muted">+{{ $account->memberships->count()-2 }} jabatan</span>
                @endif
              @endif
            </td>
            <td><span class="rt03-account-status {{ $account->is_active ? 'is-active' : 'is-inactive' }}">{{ $account->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
            <td>
              @if($account->id === auth()->id())
                <a class="rt03-account-self-link" href="{{ route('profile.index') }}">Profil Saya</a>
              @else
                <x-entry-modal title="Reset password {{ $account->login_id }}"
                               label="Reset" icon="key-round"
                               button-class="btn btn-red btn-small rt03-account-reset-trigger">
                  <form method="post" action="{{ route('people.password.reset', $account) }}" class="form-stack">
                    @csrf @method('PATCH')
                    <p class="form-hint">Akun: <strong>{{ $account->name }} · {{ $account->login_id }}</strong></p>
                    <label class="field"><span>Password Super Admin</span>
                      <span class="rt03-profile-password-field"><input type="password" name="current_password" autocomplete="current-password" required>
                        <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                      </span>
                    </label>
                    <label class="field"><span>Password baru (min. 8 karakter)</span>
                      <span class="rt03-profile-password-field"><input type="password" name="password" minlength="8" autocomplete="new-password" required>
                        <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                      </span>
                    </label>
                    <label class="field"><span>Ulangi password baru</span>
                      <span class="rt03-profile-password-field"><input type="password" name="password_confirmation" minlength="8" autocomplete="new-password" required>
                        <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                      </span>
                    </label>
                    <button type="submit" class="btn btn-red btn-block">Simpan password</button>
                  </form>
                </x-entry-modal>
              @endif
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
  @if($accounts->hasPages())
  <div class="pagination-row rt03-compact-pagination">{{ $accounts->links() }}</div>
  @endif
</section>
@endif
@endsection
