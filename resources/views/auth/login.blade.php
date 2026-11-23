<!DOCTYPE html><html lang="id"><head>
<!-- PANITIAHUB-FAVICON -->
  <link rel="icon" type="image/jpeg" href="{{ asset('images/rt03/logo.jpeg') }}?v=20261123"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#11233a"><title>Masuk · PANITIA HUB RT 03</title>@vite(['resources/css/app.css','resources/js/app.js'])</head>
<body class="login-body"><main class="login-shell">
  <section class="login-scene" aria-label="Foto kegiatan panitia RT 03">
    @php($photos = collect(['panitia-01.jpg','panitia-01.jpeg','panitia-01.webp','panitia-02.jpg','panitia-02.jpeg','panitia-02.webp','panitia-03.jpg','panitia-03.jpeg','panitia-03.webp'])->filter(fn($f)=>file_exists(public_path('images/rt03/'.$f)))->values())
    <div class="login-photo-stack" data-login-gallery>
      @forelse($photos as $idx=>$file)
      <div class="login-photo {{ $idx===0?'active':'' }}" data-login-slide style="background-image:url('{{ asset('images/rt03/'.$file) }}')"></div>
      @empty
      <div class="login-art"><div class="login-circle login-circle-a"></div><div class="login-circle login-circle-b"></div><span class="art-year">17<span>08</span></span></div>
      @endforelse
    </div>
    <div class="login-scene-overlay"></div>
    <a href="{{route('public.home')}}" class="login-back"><i data-lucide="arrow-left"></i> Kembali ke portal warga</a>
    <div class="login-scene-copy"><span class="login-scene-kicker"><span class="live-dot"></span> RUANG KERJA KITA</span><h1 data-word-reveal>Bergerak bersama.<br>Mewariskan cerita.</h1><p>Untuk panitia yang belajar, berproses, dan menyiapkan perayaan terbaik bagi lingkungan kita.</p><div class="login-chapter">RT 03 / EST. UNTUK GENERASI BERIKUTNYA</div></div>
  </section>
  <section class="login-form-side"><div class="login-form-content"><div class="brand login-brand"><span class="brand-mark">@php($rtLogo = collect(['logo.png','logo.jpg','logo.jpeg','logo.webp'])->first(fn($f) => file_exists(public_path('images/rt03/'.$f))))@if($rtLogo)<img src="{{ asset('images/rt03/'.$rtLogo) }}" alt="Logo RT 03">@else<span>03</span>@endif</span><span class="brand-text"><strong>PANITIA HUB</strong><small>INTERNAL WORKSPACE</small></span></div><div class="login-intro"><span class="eyebrow">SELAMAT DATANG KEMBALI</span><h2>Masuk ke ruang panitia<span class="red-period">.</span></h2><p>Gunakan akun yang diberikan oleh ketua atau admin panitia.</p></div>
  @if($errors->any())<div class="notice notice-error" role="alert">{{ $errors->first() }}</div>@endif
  <form method="post" action="{{route('login.submit')}}" class="form-stack">@csrf
    <label class="field"><span>Alamat email</span><input type="email" name="email" value="{{old('email')}}" placeholder="nama@email.com" autocomplete="username" required autofocus></label>
    <label class="field"><span>Kata sandi</span><input type="password" name="password" placeholder="Masukkan kata sandi" autocomplete="current-password" required></label>
    <button type="submit" class="btn btn-red btn-block login-submit">Masuk ke Dashboard <i data-lucide="arrow-up-right"></i></button>
  </form><p class="login-help">Belum memiliki akun? Hubungi ketua panitia atau admin RT 03.</p></div><div class="login-form-footer">© {{date('Y')}} PANITIA HUB RT 03 <span>BERSAMA, LEBIH TERARAH.</span></div></section>
</main></body></html>
