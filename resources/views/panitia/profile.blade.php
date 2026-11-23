@extends('layouts.app')
@section('title', 'Profil Saya')

@section('content')
@php
    $initial = mb_strtoupper(mb_substr($user->name, 0, 1));
    $primaryRole = $user->is_super_admin
        ? 'Super Admin'
        : ($memberships->first() ? ucwords(str_replace('_', ' ', $memberships->first()->role)) : 'Panitia');
@endphp

<div class="rt03-profile-page">
    <div class="rt03-profile-page-heading">
        <div>
            <span class="eyebrow">PENGATURAN AKUN / {{ $period->year }}</span>
            <h1>Profil Saya<span class="text-red">.</span></h1>
            <p>Identitas, jabatan, dan keamanan akun dalam satu tempat.</p>
        </div>
        <span class="rt03-profile-account-state"><span></span> Akun aktif</span>
    </div>

    <section class="rt03-profile-hero" aria-labelledby="rt03-profile-name">
        <div class="rt03-profile-hero-texture" aria-hidden="true">03</div>
        <div class="rt03-profile-hero-top">
            <span>PERSONAL WORKSPACE</span>
            <span>RT 03 <span class="rt03-profile-hero-separator">/</span> {{ $period->year }}</span>
        </div>
        <div class="rt03-profile-hero-identity">
            <x-account-avatar :user="$user" size="large" />
            <div class="rt03-profile-hero-intro">
                <span class="rt03-profile-hero-role">{{ $primaryRole }}</span>
                <h2 id="rt03-profile-name">{{ $user->name }}</h2>
                <p>{{ $user->email }}</p>
            </div>
        </div>
        <div class="rt03-profile-hero-bottom">
            <span><i data-lucide="users-round" aria-hidden="true"></i> Kepanitiaan RT 03</span>
            <span><i data-lucide="calendar-days" aria-hidden="true"></i> Bergabung {{ $user->created_at?->translatedFormat('d M Y') }}</span>
        </div>
    </section>

    <div class="rt03-profile-layout">
        <div class="rt03-profile-column rt03-profile-column--main">
            <section class="rt03-profile-panel" aria-labelledby="rt03-profile-data-title">
                <div class="rt03-profile-panel-head">
                    <div class="rt03-profile-panel-icon"><i data-lucide="id-card" aria-hidden="true"></i></div>
                    <div>
                        <span class="eyebrow">INFORMASI PRIBADI</span>
                        <h2 id="rt03-profile-data-title">Data akun</h2>
                        <p>Perbarui informasi yang digunakan di ruang panitia.</p>
                    </div>
                </div>
                <form method="post" action="{{ route('profile.update') }}" class="rt03-profile-form">
                    @csrf
                    @method('PATCH')
                    <label class="field">
                        <span>Nama lengkap</span>
                        <input name="name" value="{{ old('name', $user->name) }}" maxlength="120" autocomplete="name" required>
                    </label>
                    <label class="field">
                        <span>Email akun</span>
                        <input value="{{ $user->email }}" type="email" readonly aria-describedby="rt03-profile-email-info">
                    </label>
                    <p class="rt03-profile-field-hint" id="rt03-profile-email-info">
                        <i data-lucide="lock-keyhole" aria-hidden="true"></i> Perubahan email dilakukan oleh pengelola akun.
                    </p>
                    <label class="field">
                        <span>Nomor telepon <span class="rt03-profile-optional">(opsional)</span></span>
                        <input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" maxlength="30" autocomplete="tel" placeholder="Contoh: 08xxxxxxxxxx">
                    </label>
                    <div class="rt03-profile-form-footer">
                        <button class="btn btn-red" type="submit"><i data-lucide="save" aria-hidden="true"></i> Simpan perubahan</button>
                    </div>
                </form>
            </section>

            <section class="rt03-profile-panel" aria-labelledby="rt03-profile-role-title">
                <div class="rt03-profile-panel-head">
                    <div class="rt03-profile-panel-icon"><i data-lucide="users" aria-hidden="true"></i></div>
                    <div>
                        <span class="eyebrow">STRUKTUR KEPANITIAAN</span>
                        <h2 id="rt03-profile-role-title">Jabatan saya</h2>
                        <p>Penugasan yang aktif pada periode {{ $period->year }}.</p>
                    </div>
                </div>
                <div class="rt03-profile-role-list">
                    @if($user->is_super_admin)
                        <div class="rt03-profile-role-row">
                            <span class="rt03-profile-role-symbol"><i data-lucide="shield-check" aria-hidden="true"></i></span>
                            <div><strong>Super Admin</strong><span>Pengelola sistem dan seluruh periode</span></div>
                            <span class="rt03-profile-role-active">Aktif</span>
                        </div>
                    @endif
                    @forelse($memberships as $membership)
                        <div class="rt03-profile-role-row">
                            <span class="rt03-profile-role-symbol"><i data-lucide="badge-check" aria-hidden="true"></i></span>
                            <div>
                                <strong>{{ ucwords(str_replace('_', ' ', $membership->role)) }}</strong>
                                <span>{{ $membership->division?->name ?? 'Pengurus inti / Pendamping' }}</span>
                            </div>
                            <span class="rt03-profile-role-active">Aktif</span>
                        </div>
                    @empty
                        @unless($user->is_super_admin)
                            <p class="rt03-profile-muted">Tidak ada penugasan aktif yang ditampilkan.</p>
                        @endunless
                    @endforelse
                </div>
            </section>
        </div>

        <div class="rt03-profile-column rt03-profile-column--side">
            <section class="rt03-profile-panel" aria-labelledby="rt03-profile-photo-title">
                <div class="rt03-profile-panel-head">
                    <div class="rt03-profile-panel-icon"><i data-lucide="camera" aria-hidden="true"></i></div>
                    <div>
                        <span class="eyebrow">PERSONALISASI</span>
                        <h2 id="rt03-profile-photo-title">Foto profil</h2>
                        <p>Opsional. Tetap bisa menggunakan inisial nama.</p>
                    </div>
                </div>
                <div class="rt03-profile-photo-body">
                    <div class="rt03-profile-photo-demo">
                        <div class="rt03-profile-photo-demo-frame">
                            <span class="rt03-profile-photo-demo-initial" aria-hidden="true">{{ $initial }}</span>
                            <img data-profile-photo-preview
                                @if($user->avatar_path)
                                    src="{{ route('profile.photo', ['v' => $user->updated_at?->timestamp ?? 0]) }}"
                                @else
                                    hidden
                                @endif
                                alt="Pratinjau foto profil">
                        </div>
                        <div class="rt03-profile-photo-details">
                            <strong>Foto akun</strong>
                            <span data-profile-photo-name>{{ $user->avatar_path ? 'Foto terpasang' : 'Belum ada foto' }}</span>
                            <small>JPG, PNG, WebP · Maks. 2 MB</small>
                        </div>
                    </div>
                    <form method="post" action="{{ route('profile.photo.store') }}" enctype="multipart/form-data" class="rt03-profile-photo-form">
                        @csrf
                        <label class="rt03-profile-upload-label" for="rt03-profile-photo-input">
                            <i data-lucide="upload" aria-hidden="true"></i> Pilih foto
                        </label>
                        <input id="rt03-profile-photo-input" type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                               data-profile-photo-input required>
                        <button type="submit" class="btn btn-red rt03-profile-upload-submit">Simpan foto</button>
                    </form>
                    @if($user->avatar_path)
                        <form method="post" action="{{ route('profile.photo.destroy') }}" class="rt03-profile-remove-form"
                              data-rt03-confirm data-confirm-title="Hapus foto profil?" data-confirm-message="Foto akun akan dihapus dan avatar kembali menjadi inisial nama. Informasi profil lainnya tidak berubah." data-confirm-action="Hapus foto" data-confirm-variant="danger">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rt03-profile-remove-button" disabled>
                                <i data-lucide="trash-2" aria-hidden="true"></i> Hapus foto
                            </button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="rt03-profile-panel" aria-labelledby="rt03-profile-security-title">
                <div class="rt03-profile-panel-head">
                    <div class="rt03-profile-panel-icon"><i data-lucide="shield" aria-hidden="true"></i></div>
                    <div>
                        <span class="eyebrow">KEAMANAN AKUN</span>
                        <h2 id="rt03-profile-security-title">Kata sandi</h2>
                        <p>Gunakan kata sandi unik untuk menjaga akses akun.</p>
                    </div>
                </div>
                <form method="post" action="{{ route('profile.password') }}" class="rt03-profile-form rt03-profile-password-form">
                    @csrf
                    @method('PATCH')
                    <label class="field">
                        <span>Kata sandi sekarang</span>
                        <span class="rt03-profile-password-field">
                            <input type="password" name="current_password" autocomplete="current-password" required>
                            <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                        </span>
                    </label>
                    <label class="field">
                        <span>Kata sandi baru</span>
                        <span class="rt03-profile-password-field">
                            <input type="password" name="password" autocomplete="new-password" minlength="12" required placeholder="Minimal 12 karakter">
                            <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                        </span>
                    </label>
                    <label class="field">
                        <span>Konfirmasi kata sandi baru</span>
                        <span class="rt03-profile-password-field">
                            <input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" required>
                            <button type="button" data-toggle-password aria-label="Tampilkan kata sandi" aria-pressed="false"><i data-lucide="eye" aria-hidden="true"></i></button>
                        </span>
                    </label>
                    <div class="rt03-profile-form-footer">
                        <button class="btn btn-navy" type="submit"><i data-lucide="key-round" aria-hidden="true"></i> Ganti kata sandi</button>
                    </div>
                </form>
                <div class="rt03-profile-security-tip">
                    <i data-lucide="info" aria-hidden="true"></i>
                    <span>Jangan bagikan kata sandi kepada panitia lain, termasuk mentor.</span>
                </div>
            </section>
        </div>
    </div>
</div>
@endsection
