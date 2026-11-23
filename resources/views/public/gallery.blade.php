@extends('layouts.public')
@section('title','Galeri')
@section('content')
<section class="listing-hero"><span class="eyebrow">DOKUMENTASI / RT 03 / {{ $period?->year ?? '2027' }}</span><h1 data-word-reveal>Momen bersama<span class="text-red">.</span></h1><p>Kenangan sederhana dari kebersamaan warga.</p></section>
<section class="public-section listing-section"><div class="listing-top"><span class="eyebrow">FOTO RESMI YANG DIIZINKAN</span><span class="results-count">{{ $items->count() }} foto</span></div><div class="gallery-grid public-gallery" data-stagger>@forelse($items as $item)<figure class="gallery-card reveal-child"><img src="{{route('gallery.photo',$item)}}" alt="{{$item->title}}" loading="lazy"><figcaption>{{$item->title}}</figcaption></figure>@empty<div class="empty-state empty-wide">Belum ada foto resmi yang dipublikasikan panitia.</div>@endforelse</div></section>
@endsection
