@extends('layouts.public')
@php($labels=['lomba'=>['Perlombaan','Kenali lomba yang disiapkan panitia.'],'jadwal'=>['Jadwal kegiatan','Catat tanggal pentingnya.'],'pengumuman'=>['Pengumuman','Informasi resmi dari panitia.'],'hasil'=>['Hasil lomba','Daftar juara yang telah diumumkan resmi.']])
@section('title',$labels[$section][0])
@section('content')

<section class="listing-hero rt03-listing-hero rt03-listing-hero--{{ $section }}" aria-labelledby="rt03-page-title">
  <div class="rt03-listing-light" aria-hidden="true">
    <span class="rt03-listing-orb rt03-listing-orb--red"></span>
    <span class="rt03-listing-orb rt03-listing-orb--gold"></span>
  </div>
  <div class="rt03-listing-content">
    <span class="eyebrow"><span class="rt03-listing-flag" aria-hidden="true"></span> RT 03 / INFORMASI RESMI / {{ $period?->year ?? '2027' }}</span>
    <h1 id="rt03-page-title" data-word-reveal>{{ $labels[$section][0] }}<span class="text-red">.</span></h1>
    <p>{{ $labels[$section][1] }}</p>
    <span class="rt03-listing-bottomline">BERSAMA <span>·</span> BERGERAK <span>·</span> BERKARYA</span>
  </div>
  <span class="rt03-listing-watermark" aria-hidden="true">03<span>.</span></span>
  <span class="rt03-listing-corner" aria-hidden="true">17 / 08</span>
</section>
<section class="public-section listing-section"><div class="listing-top"><span class="eyebrow">DITERBITKAN PANITIA</span><span class="results-count">{{ $items->count() }} informasi</span></div><div class="listing-grid" data-stagger>
@forelse($items as $item)
  <article class="listing-card reveal-child">
    @if($section==='lomba')<div class="listing-card-top"><span class="notice-tag">{{$item->category}}</span>@if($item->quota)<span class="tiny-status">KUOTA {{$item->quota}}</span>@endif</div><h2>{{$item->name}}</h2><p>{{$item->rules?:'Aturan lomba akan diinformasikan oleh panitia.'}}</p><div class="listing-card-bottom"><i data-lucide="calendar"></i> {{$item->starts_at?->translatedFormat('d M Y · H:i') ?? 'Jadwal menyusul'}} <span>· {{$item->location??'Lokasi menyusul'}}</span></div><small class="register-note">Pendaftaran dilakukan langsung melalui panitia.</small>
    @elseif($section==='jadwal')<div class="listing-card-top"><span class="notice-tag">{{str_replace('_',' ',$item->category)}}</span></div><h2>{{$item->title}}</h2><p>{{$item->description ?: 'Agenda telah dijadwalkan oleh panitia.'}}</p><div class="listing-card-bottom"><i data-lucide="clock"></i> {{$item->starts_at->translatedFormat('d M Y · H:i')}} WIB <span>· {{$item->location??'Lokasi menyusul'}}</span></div>
    @elseif($section==='pengumuman')<div class="listing-card-top"><span class="notice-tag">{{$item->category}}</span><span class="tiny-status">{{$item->published_at?->translatedFormat('d M Y')}}</span></div><h2>{{$item->title}}</h2><p class="pre-line">{{$item->content}}</p>
    @elseif($section==='hasil')<div class="listing-card-top"><span class="notice-tag">HASIL RESMI</span><span class="tiny-status">JUARA {{$item->rank_position}}</span></div><h2>{{$item->competition?->name}}</h2><p class="winner-name">{{$item->public_display_name}}</p><div class="listing-card-bottom">Diumumkan oleh panitia RT 03.</div>
    @endif
  </article>
@empty<div class="empty-state empty-wide"><i data-lucide="inbox"></i><h2>Belum ada informasi</h2><p>Panitia belum mempublikasikan data pada halaman ini.</p></div>@endforelse
</div></section>
@endsection
