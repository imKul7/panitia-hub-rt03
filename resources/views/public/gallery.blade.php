@extends('layouts.public')
@section('title','Galeri')
@section('content')

<section class="listing-hero rt03-listing-hero rt03-listing-hero--galeri" aria-labelledby="rt03-page-title">
  <div class="rt03-listing-light" aria-hidden="true">
    <span class="rt03-listing-orb rt03-listing-orb--red"></span>
    <span class="rt03-listing-orb rt03-listing-orb--gold"></span>
  </div>
  <div class="rt03-listing-content">
    <span class="eyebrow"><span class="rt03-listing-flag" aria-hidden="true"></span> DOKUMENTASI / RT 03 / {{ $period?->year ?? '2027' }}</span>
    <h1 id="rt03-page-title" data-word-reveal>Momen bersama<span class="text-red">.</span></h1>
    <p>Kenangan sederhana dari kebersamaan warga.</p>
    <span class="rt03-listing-bottomline">BERSAMA <span>·</span> BERGERAK <span>·</span> BERKARYA</span>
  </div>
  <span class="rt03-listing-watermark" aria-hidden="true">03<span>.</span></span>
  <span class="rt03-listing-corner" aria-hidden="true">17 / 08</span>
</section>
<section class="public-section listing-section"><div class="listing-top"><span class="eyebrow">FOTO RESMI YANG DIIZINKAN</span><span class="results-count">{{ $items->total() }} foto</span></div><div class="gallery-grid public-gallery" data-stagger>@forelse($items as $item)<figure class="gallery-card reveal-child"><img src="{{route('gallery.photo',$item)}}" alt="{{$item->title}}" loading="lazy" decoding="async"><figcaption>{{$item->title}}</figcaption></figure>@empty<div class="empty-state empty-wide">Belum ada foto resmi yang dipublikasikan panitia.</div>@endforelse</div>@if($items->hasPages())<nav class="pagination-row" aria-label="Halaman galeri">{{ $items->links() }}</nav>@endif</section>
@endsection
