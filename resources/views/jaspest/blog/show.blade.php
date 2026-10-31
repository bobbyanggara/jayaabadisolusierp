@php
    $pageTitle = $post->meta_title ?: $post->title.' | Blog JASPEST';
    $pageDescription = $post->meta_description ?: $post->excerpt;
    $canonical = route('jaspest.blog.show', $post->slug);
    $ogType = 'article';
    $ogImage = $post->coverUrl();
    $schema = ['@context' => 'https://schema.org', '@graph' => [$post->schema(),
        ['@type' => 'BreadcrumbList', 'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => url('/')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Blog', 'item' => route('jaspest.blog.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $post->title, 'item' => $canonical],
        ]],
    ]];
@endphp
@extends('jaspest.blog.layout')
@section('content')
    <nav class="breadcrumb" aria-label="Jejak halaman"><a href="{{ url('/') }}">Beranda</a><span aria-hidden="true">/</span><a href="{{ route('jaspest.blog.index') }}">Blog</a><span aria-hidden="true">/</span><span aria-current="page">{{ $post->category }}</span></nav>
    <div class="blog-reading-layout">
        <article class="blog-article">
            <div class="eyebrow">{{ $post->category }}</div>
            <h1>{{ $post->title }}</h1>
            <p class="blog-intro">{{ $post->excerpt }}</p>
            <div class="blog-byline"><span>{{ $post->author_name }}</span><time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->locale('id')->translatedFormat('j F Y') }}</time><span>{{ $post->readingMinutes() }} menit baca</span></div>
            <figure class="blog-cover"><img src="{{ $post->coverUrl() }}" width="1536" height="1024" alt="{{ $post->cover_alt ?: $post->title }}" fetchpriority="high"></figure>
            <div class="blog-prose">@foreach($post->paragraphs() as $paragraph)<p>{{ $paragraph }}</p>@endforeach</div>
            <p class="blog-endnote">Kondisi setiap lokasi dapat berbeda. Rincian survei, treatment, dan perawatan lanjutan akan dijelaskan langsung oleh tim JASPEST.</p>
        </article>
        <aside class="blog-sidebar"><h2>Ingin membahas kebutuhan lokasi Anda?</h2><p>Ceritakan kondisi rumah atau bisnis Anda melalui formulir. Tim JASPEST akan membantu membahas kebutuhan penanganannya.</p><a class="button button-red" href="{{ url('/#konsultasi') }}">Ajukan konsultasi</a><a class="text-link blue-text" href="{{ route('jaspest.blog.index') }}">Kembali ke semua blog</a></aside>
    </div>
    @if($related->isNotEmpty())
        <section class="blog-related" aria-labelledby="related-title"><h2 id="related-title">Baca juga</h2>@include('jaspest.blog.cards', ['posts' => $related])</section>
    @endif
@endsection
