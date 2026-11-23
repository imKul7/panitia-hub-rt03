<!DOCTYPE html>
<html lang="id">
<head>
<!-- PANITIAHUB-FAVICON -->
  <link rel="icon" type="image/jpeg" href="{{ asset('images/rt03/logo.jpeg') }}?v=20261123">
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f4f5f7">
  <title>@yield('title','Dashboard') · PANITIA HUB RT 03</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="app-body" x-data="{sidebar:false}">
  @php($period = \App\Support\Workspace::period())
  <aside class="app-sidebar" :class="{'is-open':sidebar}" aria-label="Menu utama">
    <a href="{{route('dashboard')}}" class="brand app-brand"><span class="brand-mark">@php($rtLogo = collect(['logo.png','logo.jpg','logo.jpeg','logo.webp'])->first(fn($f) => file_exists(public_path('images/rt03/'.$f))))@if($rtLogo)<img src="{{ asset('images/rt03/'.$rtLogo) }}" alt="Logo RT 03">@else<span>03</span>@endif</span><span class="brand-text"><strong>PANITIA HUB</strong><small>WORKSPACE / {{ $period->year }}</small></span></a>
    <div class="sidebar-overline">WORKSPACE</div>
    <nav class="sidebar-nav" data-lenis-prevent>
      @foreach([
      ['dashboard','Dashboard','dashboard'],['people.index','Struktur Panitia','users'],['tasks.index','Tugas & Progres','check-square'],['notes.index','Catatan Divisi','notebook'],
      ['competitions.index','Manajemen Lomba','trophy'],['registrations.index','Peserta Lomba','contact'],['results.index','Hasil Lomba','award'],['prizes.index','Hadiah Lomba','gift'],['inventory.index','Inventaris','package'],
      ['schedules.index','Jadwal & Acara','calendar'],['announcements.index','Pengumuman','megaphone'],['gallery.index','Galeri Foto','images'],['documents.index','Sekretariat','folder'],
      ['guides.index','Panduan & SOP','book'],['mentor.index','Ruang Mentor','message'],['archive.index','Arsip Tahunan','archive'],['profile.index','Profil Saya','user-circle'],
      ] as $nav)
        <a href="{{ route($nav[0]) }}" class="sidebar-link {{ request()->routeIs($nav[0])?'active':'' }}" @click="sidebar=false"><span class="sidebar-icon"><i data-lucide="{{ $nav[2] }}"></i></span><span>{{ $nav[1] }}</span></a>
      @endforeach
      @if(\App\Support\Access::can(auth()->user(),'manage-periods'))
      <a href="{{route('periods.index')}}" class="sidebar-link {{ request()->routeIs('periods.*')?'active':'' }}" @click="sidebar=false"><span class="sidebar-icon"><i data-lucide="settings"></i></span><span>Kelola Periode</span></a>
      @endif
      @if(\App\Support\Access::can(auth()->user(),'expenses-request'))
      <a href="{{route('expenses.index')}}" class="sidebar-link {{ request()->routeIs('expenses.*')?'active':'' }}" @click="sidebar=false"><span class="sidebar-icon"><i data-lucide="receipt"></i></span><span>Pengajuan Dana</span></a>
      @endif
      @if(\App\Support\Access::can(auth()->user(),'finance-view'))
      <div class="sidebar-overline sidebar-overline-nested">TERBATAS</div>
      <a href="{{route('finance.index')}}" class="sidebar-link {{ request()->routeIs('finance.*')?'active':'' }}" @click="sidebar=false"><span class="sidebar-icon"><i data-lucide="wallet"></i></span><span>Keuangan</span><i data-lucide="lock" class="sidebar-lock"></i></a>
      @endif
    </nav>
    <div class="sidebar-bottom"><span class="sidebar-bottom-dot"></span><div><strong>Periode {{ $period->year }}</strong><small>{{ $period->status === 'active'?'Sedang berjalan':'Persiapan' }}</small></div></div>
  </aside>
  <div class="sidebar-scrim" x-show="sidebar" x-cloak @click="sidebar=false"></div>
  <div class="app-main">
    <header class="app-topbar"><div class="topbar-start"><button type="button" class="icon-button mobile-toggle" aria-label="Buka menu" @click="sidebar=true"><i data-lucide="menu"></i></button><span class="breadcrumb-root">PANITIA HUB</span><i data-lucide="chevron-right" class="breadcrumb-chevron"></i><span class="breadcrumb-current">@yield('title','Dashboard')</span></div><div class="topbar-actions"><span class="topbar-year"><span class="live-dot"></span> {{ $period->year }}</span><a href="{{route('public.home')}}" target="_blank" rel="noopener" class="icon-button" title="Lihat portal warga"><i data-lucide="external-link"></i></a><a href="{{route('profile.index')}}" class="account-pill"><x-account-avatar :user="auth()->user()" size="small" /><span class="account-name">{{ auth()->user()->name }}</span></a><form method="post" action="{{route('logout')}}">@csrf<button class="icon-button" type="submit" title="Keluar"><i data-lucide="log-out"></i></button></form></div></header>
    <main class="page-content">
      @if(session('success'))<div role="status" class="notice notice-success"><i data-lucide="check-circle-2"></i><span>{{session('success')}}</span></div>@endif
      @if($errors->any())<div role="alert" class="notice notice-error"><i data-lucide="alert-circle"></i><div><strong>Periksa kembali formulir.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
      @yield('content')
    </main>
    <footer class="app-footer">PANITIA HUB RT 03 <span>•</span> Dikelola bersama, diteruskan bersama.</footer>
  </div>

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
</body></html>
