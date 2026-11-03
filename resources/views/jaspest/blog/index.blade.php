@php
    $pageTitle = 'Blog JASPEST — Informasi pengendalian hama untuk rumah dan bisnis';
    $pageDescription = 'Panduan sederhana untuk membantu Anda menyiapkan konsultasi pengendalian hama bersama JASPEST.';
    $canonical = $posts->currentPage() > 1 ? $posts->url($posts->currentPage()) : route('jaspest.blog.index');
@endphp
@extends('jaspest.blog.layout')
@section('content')
    <nav class="breadcrumb" aria-label="Jejak halaman"><a href="{{ url('/') }}">Beranda</a><span aria-hidden="true">/</span><span aria-current="page">Blog</span></nav>
    <div class="blog-heading">
        <div class="eyebrow">BLOG JASPEST</div>
        <h1>Informasi untuk rumah dan bisnis Anda.</h1>
        <p>Beberapa panduan sederhana untuk membantu Anda menyiapkan konsultasi bersama tim kami.</p>
    </div>
    @include('jaspest.blog.cards', ['posts' => $posts])
    @if($posts->hasPages())
        <nav class="blog-pagination" aria-label="Halaman blog">
            @if($posts->onFirstPage())<span aria-disabled="true">Sebelumnya</span>@else<a href="{{ $posts->previousPageUrl() }}" rel="prev">Sebelumnya</a>@endif
            <span aria-current="page">{{ $posts->currentPage() }} / {{ $posts->lastPage() }}</span>
            @if($posts->hasMorePages())<a href="{{ $posts->nextPageUrl() }}" rel="next">Berikutnya</a>@else<span aria-disabled="true">Berikutnya</span>@endif
        </nav>
    @endif
@endsection
