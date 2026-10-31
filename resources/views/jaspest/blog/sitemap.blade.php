{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>{{ url('/') }}</loc></url>
    <url><loc>{{ route('jaspest.blog.index') }}</loc></url>
    @foreach($posts as $post)
        <url><loc>{{ route('jaspest.blog.show', $post->slug) }}</loc><lastmod>{{ $post->updated_at->toIso8601String() }}</lastmod></url>
    @endforeach
</urlset>
