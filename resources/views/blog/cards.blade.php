<div class="articles-grid">
    @forelse($posts as $cardPost)
        <a class="article-card" href="{{ route('jaspest.blog.show', $cardPost->slug) }}">
            <div class="article-image"><img src="{{ $cardPost->coverUrl() }}" alt="{{ $cardPost->cover_alt ?: $cardPost->title }}" loading="lazy" width="1536" height="1024"></div>
            <div class="article-info">
                <span class="article-tag">{{ $cardPost->category }}</span>
                <h3>{{ $cardPost->title }}</h3>
                <p>{{ $cardPost->excerpt }}</p>
                <div class="article-meta"><span>{{ $cardPost->readingMinutes() }} menit baca</span><span class="article-read">Baca blog</span></div>
            </div>
        </a>
    @empty
        <p class="blog-empty">Tulisan blog akan hadir di halaman ini.</p>
    @endforelse
</div>
