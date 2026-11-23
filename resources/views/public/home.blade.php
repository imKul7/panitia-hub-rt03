@extends('layouts.public')
@section('title','Beranda')
@section('content')
<!-- RT03-HERO-REDESIGN-V2 -->
<section class="public-hero rt03-hero-v2">
  <div class="hero-visual rt03-hero-visual">
    <img class="rt03-hero-photo" src="{{ asset('images/rt03/hero-kemerdekaan.jpg') }}" alt="Foto kebersamaan warga dan panitia RT 03" width="1920" height="1080" fetchpriority="high" decoding="async">
    <div class="rt03-hero-shade" aria-hidden="true"></div>
    <div class="rt03-hero-mark" aria-label="17 Agustus"><span>17</span><small>/</small><span>08</span></div>
    <div class="rt03-hero-caption"><span>RT 03 / DOKUMENTASI KEGIATAN</span><span>INDONESIA MERDEKA</span></div>
  </div>
  <div class="hero-copy rt03-hero-copy">
    <span class="eyebrow public-eyebrow"><span class="eyebrow-line"></span> MERAYAKAN KEBERSAMAAN / {{ $period?->year ?? '2027' }}</span>
    <h1 data-word-reveal>Semangat yang<br>menyatukan <em>kita.</em></h1>
    <p>Bukan sekadar perayaan 17 Agustus. Ini cerita tentang lingkungan, kebersamaan, dan generasi yang saling mendukung.</p>
    <div class="hero-actions">
      <a href="{{ route('public.section', 'jadwal') }}" class="btn btn-red">Jelajahi jadwal <i data-lucide="arrow-up-right"></i></a>
      <a href="{{ route('public.section', 'lomba') }}" class="inline-link">Lihat perlombaan <i data-lucide="arrow-right"></i></a>
    </div>
    <span class="rt03-hero-footnote">BERSAMA · BERGERAK · BERKARYA</span>
  </div>
</section>
<section class="public-section intro-section"><div class="section-heading" data-reveal><span class="eyebrow">01 / APA YANG AKAN HADIR?</span><h2>Ruang untuk semua<br><span class="text-red">jadi bagian cerita.</span></h2></div><div class="feature-grid" data-stagger>
<a class="feature-tile reveal-child" href="{{route('public.section','lomba')}}"><span class="feature-number">01</span><div class="feature-glyph"><i data-lucide="trophy"></i></div><h3>Perlombaan</h3><p>Beragam lomba untuk memeriahkan hari kemerdekaan.</p><span class="tile-arrow"><i data-lucide="arrow-up-right"></i></span></a>
<a class="feature-tile reveal-child" href="{{route('public.section','jadwal')}}"><span class="feature-number">02</span><div class="feature-glyph"><i data-lucide="calendar-days"></i></div><h3>Agenda kegiatan</h3><p>Semua jadwal dalam satu tempat, tanpa informasi yang tercecer.</p><span class="tile-arrow"><i data-lucide="arrow-up-right"></i></span></a>
<a class="feature-tile reveal-child" href="{{route('public.section','hasil')}}"><span class="feature-number">03</span><div class="feature-glyph"><i data-lucide="award"></i></div><h3>Hasil & juara</h3><p>Pengumuman hasil resmi yang sudah diverifikasi panitia.</p><span class="tile-arrow"><i data-lucide="arrow-up-right"></i></span></a>
</div></section>
<!-- RT03-NEIGHBORHOOD-SECTION-V2 -->
<section class="public-neighborhood rt03-neighborhood-v2" aria-labelledby="rt03-neighborhood-heading">
  <div class="neighborhood-media">
    <img src="{{ asset('images/rt03/lingkungan-rt03.jpeg') }}" alt="Dokumentasi kebersamaan warga di lingkungan RT 03" width="1600" height="1200" loading="lazy" decoding="async">
    <span class="neighborhood-media-tag">LINGKUNGAN RT 03</span>
  </div>
  <div class="neighborhood-content">
    <span class="eyebrow"><span class="eyebrow-line"></span> LINGKUNGAN KITA</span>
    <h2 id="rt03-neighborhood-heading">Tempat cerita<br><span class="text-red">kita bermula.</span></h2>
    <p>Di lingkungan ini kita bertemu, bekerja sama, dan merayakan kemerdekaan. Setiap tahun, semangat kebersamaan itu kita teruskan.</p>
    <span class="neighborhood-footnote">RT 03 / BERSAMA UNTUK GENERASI BERIKUTNYA</span>
  </div>
</section>
<section class="public-section two-column-section"><div class="section-heading" data-reveal><span class="eyebrow">02 / CATAT TANGGALNYA</span><h2>Jangan sampai<br>ketinggalan.</h2><p>Ikuti agenda yang telah dijadwalkan oleh panitia.</p><a class="inline-link" href="{{route('public.section','jadwal')}}">Semua jadwal <i data-lucide="arrow-right"></i></a></div><div class="agenda-list" data-stagger>@forelse($schedules as $schedule)<article class="agenda-row reveal-child"><div class="agenda-date"><strong>{{ $schedule->starts_at->format('d') }}</strong><span>{{ $schedule->starts_at->translatedFormat('M') }}</span></div><div class="agenda-content"><span class="eyebrow">{{strtoupper(str_replace('_',' ',$schedule->category))}}</span><h3>{{$schedule->title}}</h3><p>{{$schedule->location ?: 'Lokasi menyusul'}} · {{$schedule->starts_at->format('H:i')}} WIB</p></div><i data-lucide="arrow-up-right"></i></article>@empty<div class="empty-state">Jadwal resmi akan ditampilkan setelah ditetapkan panitia.</div>@endforelse</div></section>
<section class="public-section notices-section"><div class="section-heading section-heading-row" data-reveal><div><span class="eyebrow">03 / INFORMASI PANITIA</span><h2>Kabar terbaru<span class="text-red">.</span></h2></div><a href="{{route('public.section','pengumuman')}}" class="inline-link">Semua pengumuman <i data-lucide="arrow-right"></i></a></div><div class="notice-cards" data-stagger>@forelse($announcements as $a)<article class="notice-tile reveal-child"><span class="notice-tag">{{strtoupper($a->category)}}</span><h3>{{$a->title}}</h3><p>{{\Illuminate\Support\Str::limit($a->content,140)}}</p><div class="notice-date">{{$a->published_at?->translatedFormat('d F Y')}}</div></article>@empty<article class="notice-tile notice-tile-placeholder"><span class="notice-tag">PAPAN INFORMASI</span><h3>Informasi akan hadir di sini.</h3><p>Panitia akan menampilkan pengumuman resmi setelah persiapan dimulai.</p></article>@endforelse</div></section>

<section class="closing-band rt03-closing-indonesia" aria-label="Semangat kebersamaan RT 03">
  <div class="rt03-closing-atmosphere" aria-hidden="true">
    <span class="rt03-closing-glow rt03-glow-one"></span>
    <span class="rt03-closing-glow rt03-glow-two"></span>
    <span class="rt03-closing-shimmer"></span>
  </div>
  <div class="rt03-closing-copy" data-reveal>
    <span class="eyebrow"><span class="rt03-flag-mark" aria-hidden="true"></span> DARI KITA, UNTUK KITA</span>
    <h2>Setiap generasi punya<br><span>ceritanya sendiri.</span></h2>
    <p>Dan setiap cerita dimulai dengan langkah kecil bersama.</p>
    <a class="rt03-closing-link" href="{{ route('public.section', 'jadwal') }}">
      Lihat agenda warga <i data-lucide="arrow-up-right"></i>
    </a>
  </div>
  <span class="closing-symbol" aria-hidden="true">03<span>.</span></span>
  <span class="rt03-closing-signature" aria-hidden="true">MERDEKA · BERSAMA · RT 03</span>
</section>

@endsection
