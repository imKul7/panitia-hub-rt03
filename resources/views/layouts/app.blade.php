<!DOCTYPE html>
<html lang="id">
<head>
<!-- PANITIAHUB-FAVICON -->
  <link rel="icon" type="image/jpeg" href="{{ asset('images/rt03/logo.jpeg') }}?v=20261123">
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#c91f37">
  <title>@yield('title','Dashboard') · PANITIA HUB RT 03</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="app-body" x-data="{sidebar:false}" data-rt03-workspace-router>
  @php($period = \App\Support\Workspace::period())
  @php($navigation = \App\Support\RoleMenu::forUser(auth()->user(), $period))
  <aside class="app-sidebar" :class="{'is-open':sidebar}" aria-label="Menu utama">
    <a href="{{route('dashboard')}}" class="brand app-brand"><span class="brand-mark">@php($rtLogo = collect(['logo.png','logo.jpg','logo.jpeg','logo.webp'])->first(fn($f) => file_exists(public_path('images/rt03/'.$f))))@if($rtLogo)<img src="{{ asset('images/rt03/'.$rtLogo) }}" alt="Logo RT 03">@else<span>03</span>@endif</span><span class="brand-text"><strong>PANITIA HUB</strong><small>WORKSPACE / {{ $period->year }}</small></span></a>
    <section class="rt03-role-identity" aria-label="Peran saat ini">
      <span class="rt03-role-identity-kicker"><i data-lucide="badge-check" aria-hidden="true"></i> PERAN AKTIF</span>
      <strong>{{ $navigation['label'] }}</strong>
      <span>{{ $navigation['description'] }}</span>
    </section>
    <div class="sidebar-overline">MENU UTAMA</div>
    <nav class="sidebar-nav" data-lenis-prevent aria-label="Navigasi sesuai peran">
      @foreach($navigation['primary'] as $item)
        <a href="{{ route($item['route']) }}"
           class="sidebar-link {{ request()->routeIs($item['route'])?'active':'' }}"
           title="{{ $item['hint'] }}"
           @if(request()->routeIs($item['route'])) aria-current="page" @endif
           @click="sidebar=false">
          <span class="sidebar-icon"><i data-lucide="{{ $item['icon'] }}" aria-hidden="true"></i></span>
          <span class="rt03-sidebar-link-content"><span>{{ $item['label'] }}</span><small>{{ $item['hint'] }}</small></span>
        </a>
      @endforeach
      @if(count($navigation['more']) > 0)
        @php($showOther = collect($navigation['more'])->contains(fn ($item) => request()->routeIs($item['route'])))
        <details class="rt03-sidebar-more" @if($showOther) open @endif>
          <summary class="rt03-sidebar-more-trigger">
            <i data-lucide="book-open" aria-hidden="true"></i>
            <span>Menu lainnya</span>
            <span class="rt03-sidebar-more-count">{{ count($navigation['more']) }}</span>
            <i data-lucide="chevron-down" aria-hidden="true" class="rt03-sidebar-more-chevron"></i>
          </summary>
          <div class="rt03-sidebar-more-items">
            @foreach($navigation['more'] as $item)
              <a href="{{ route($item['route']) }}"
                 class="sidebar-link {{ request()->routeIs($item['route'])?'active':'' }}"
                 title="{{ $item['hint'] }}"
                 @if(request()->routeIs($item['route'])) aria-current="page" @endif
                 @click="sidebar=false">
                <span class="sidebar-icon"><i data-lucide="{{ $item['icon'] }}" aria-hidden="true"></i></span>
                <span class="rt03-sidebar-link-content"><span>{{ $item['label'] }}</span><small>{{ $item['hint'] }}</small></span>
              </a>
            @endforeach
          </div>
        </details>
      @endif
    </nav>
    <div class="sidebar-bottom"><span class="sidebar-bottom-dot"></span><div><strong>Periode {{ $period->year }}</strong><small>{{ $period->status === 'active'?'Sedang berjalan':'Persiapan' }}</small></div></div>
  </aside>
  <div class="sidebar-scrim" x-show="sidebar" x-cloak @click="sidebar=false"></div>
  <div class="app-main">
    <div class="rt03-workspace-progress" data-rt03-workspace-progress role="status" aria-live="polite" aria-atomic="true">
      <span class="rt03-workspace-progress-bar" aria-hidden="true"></span>
      <span class="rt03-workspace-progress-label" data-rt03-workspace-progress-label></span>
    </div>
    <header class="app-topbar"><div class="topbar-start"><button type="button" class="icon-button mobile-toggle" aria-label="Buka menu" @click="sidebar=true"><i data-lucide="menu"></i></button><span class="breadcrumb-root">PANITIA HUB</span><i data-lucide="chevron-right" class="breadcrumb-chevron"></i><span class="breadcrumb-current">@yield('title','Dashboard')</span></div><div class="topbar-actions"><span class="topbar-year"><span class="live-dot"></span> {{ $period->year }}</span><a href="{{route('public.home')}}" target="_blank" rel="noopener" class="icon-button" title="Lihat portal warga"><i data-lucide="external-link"></i></a><a href="{{route('profile.index')}}" class="account-pill"><x-account-avatar :user="auth()->user()" size="small" /><span class="account-name">{{ auth()->user()->name }}</span></a><form method="post" action="{{route('logout')}}">@csrf<button class="icon-button" type="submit" title="Keluar"><i data-lucide="log-out"></i></button></form></div></header>
    <main class="page-content">
      @if(session('success'))<div role="status" class="notice notice-success"><i data-lucide="check-circle-2"></i><span>{{session('success')}}</span></div>@endif
      @if($errors->any())<div role="alert" class="notice notice-error"><i data-lucide="alert-circle"></i><div><strong>Periksa kembali formulir.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
      @if(is_array(session('rt03_rejection')))<div role="alert" class="notice notice-error">
        <i data-lucide="alert-circle" aria-hidden="true"></i>
        <div><strong>{{ session('rt03_rejection.title', 'Tindakan belum dapat diproses') }}</strong>
          <p>{{ session('rt03_rejection.reason', 'Permintaan ditolak oleh sistem.') }}</p>
          <p><strong>Saran:</strong> {{ session('rt03_rejection.suggestion', 'Periksa data dan izin akun.') }}</p>
        </div>
      </div>@endif
      @yield('content')
    </main>
    <footer class="app-footer">PANITIA HUB RT 03 <span>•</span> Dikelola bersama, diteruskan bersama.</footer>
  </div>

    {{-- Validasi lintas halaman dan semua role: modal terpusat. --}}
    <x-form-feedback />

    {{-- Dialog konfirmasi terpusat: submit asli tetap melalui route, CSRF, dan policy Laravel. --}}
    <dialog class="rt03-confirm-dialog"
            data-rt03-confirm-dialog
            data-lenis-prevent
            aria-labelledby="rt03-confirm-title"
            aria-describedby="rt03-confirm-message">
      <div class="rt03-confirm-shell">
        <div class="rt03-confirm-heading">
          <span class="rt03-confirm-overline">PANITIA HUB <span aria-hidden="true">/</span> KONFIRMASI</span>
          <button type="button" class="rt03-confirm-close" data-rt03-confirm-cancel aria-label="Tutup konfirmasi">
            <i data-lucide="x" aria-hidden="true"></i>
          </button>
        </div>
        <div class="rt03-confirm-emblem" aria-hidden="true">
          <i data-lucide="shield-alert"></i>
        </div>
        <h2 id="rt03-confirm-title" data-rt03-confirm-title>Konfirmasi tindakan</h2>
        <p id="rt03-confirm-message" data-rt03-confirm-message>Periksa kembali tindakan yang akan dijalankan.</p>
        <div class="rt03-confirm-advice">
          <i data-lucide="info" aria-hidden="true"></i>
          <span>Tindakan akan diperiksa kembali oleh sistem sesuai izin akun dan periode.</span>
        </div>
        <div class="rt03-confirm-actions">
          <button type="button" class="rt03-confirm-button rt03-confirm-button--cancel" data-rt03-confirm-cancel>Batal</button>
          <button type="button" class="rt03-confirm-button rt03-confirm-button--proceed" data-rt03-confirm-proceed>Konfirmasi</button>
        </div>
      </div>
    </dialog>
<x-loading-animation />
</body></html>
