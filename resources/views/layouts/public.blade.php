<!DOCTYPE html>
<html lang="id">
<head>
<!-- PANITIAHUB-FAVICON -->
  <link rel="icon" type="image/jpeg" href="{{ asset('images/rt03/logo.jpeg') }}?v=20261123">
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#f8f6f3"><meta name="description" content="Informasi kegiatan kemerdekaan RT 03: jadwal, lomba, pengumuman, dan hasil resmi panitia.">
  <title>@yield('title','Beranda') · PANITIA HUB RT 03</title>
  @vite(['resources/css/app.css','resources/js/app.js'])
  {{-- Quick same-origin navigation is handled by public-navigation.js.
       If JavaScript is unavailable, links still perform normal Laravel GET requests. --}}
</head>
<body class="public-body rt03-independence-theme {{ request()->routeIs('public.home') ? 'rt03-public-home' : 'rt03-public-inner' }}" data-rt03-public-router>
  <div class="public-topline"><span>WARGA RT 03</span><span>BERSAMA • BERGERAK • BERKARYA</span></div>
  <header class="public-header">
    <a class="brand" href="{{ route('public.home') }}" aria-label="PANITIA HUB RT 03, beranda">
      <span class="brand-mark">@php($rtLogo = collect(['logo.png','logo.jpg','logo.jpeg','logo.webp'])->first(fn($f) => file_exists(public_path('images/rt03/'.$f))))@if($rtLogo)<img src="{{ asset('images/rt03/'.$rtLogo) }}" alt="Logo RT 03">@else<span>03</span>@endif</span><span class="brand-text"><strong>PANITIA HUB</strong><small>RUKUN TETANGGA 03</small></span>
    </a>
    <button class="mobile-menu-btn" type="button" data-mobile-menu aria-label="Buka navigasi" aria-expanded="false"><span></span><span></span><span></span></button>
    <nav class="public-nav" data-public-nav aria-label="Navigasi warga">
      <a class="{{ request()->routeIs('public.home')?'is-active':'' }}" href="{{ route('public.home') }}">Beranda</a>
      <a class="{{ request()->is('informasi/lomba')?'is-active':'' }}" href="{{ route('public.section','lomba') }}">Lomba</a>
      <a class="{{ request()->is('informasi/jadwal')?'is-active':'' }}" href="{{ route('public.section','jadwal') }}">Jadwal</a>
      <a class="{{ request()->is('informasi/pengumuman')?'is-active':'' }}" href="{{ route('public.section','pengumuman') }}">Pengumuman</a>
      <a class="{{ request()->is('informasi/hasil')?'is-active':'' }}" href="{{ route('public.section','hasil') }}">Hasil</a><a class="{{request()->routeIs('public.gallery')?'is-active':''}}" href="{{route('public.gallery')}}">Galeri</a>
    </nav>
    <a href="{{ route('login') }}" class="btn btn-navy btn-small header-login">Area Panitia <span aria-hidden="true">↗</span></a>
  </header>
  <main id="main">@yield('content')</main>
  <footer class="public-footer"><div class="footer-inner"><div><div class="brand brand--light"><span class="brand-mark">@php($rtLogo = collect(['logo.png','logo.jpg','logo.jpeg','logo.webp'])->first(fn($f) => file_exists(public_path('images/rt03/'.$f))))@if($rtLogo)<img src="{{ asset('images/rt03/'.$rtLogo) }}" alt="Logo RT 03">@else<span>03</span>@endif</span><span class="brand-text"><strong>PANITIA HUB</strong><small>UNTUK GENERASI SELANJUTNYA</small></span></div><p>Dibangun untuk kerja sama yang lebih rapi, hangat, dan berkelanjutan.</p></div><div class="footer-links"><a href="{{route('public.section','jadwal')}}">Jadwal</a><a href="{{route('public.section','lomba')}}">Lomba</a><a href="{{route('public.section','pengumuman')}}">Pengumuman</a><a href="{{route('public.gallery')}}">Galeri</a><a href="{{route('login')}}">Masuk Panitia</a></div></div><div class="footer-bottom">© {{ date('Y') }} PANITIA HUB RT 03 <span>Dirancang untuk komunitas, bukan sekadar acara.</span></div></footer>
</body></html>
