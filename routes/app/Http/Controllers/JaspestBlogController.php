<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\Response;
use Illuminate\View\View;

class JaspestBlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::published()->orderByDesc('published_at')->orderByDesc('id')->paginate(9);

        return view('jaspest.blog.index', compact('posts'));
    }

    public function show(string $slug): View
    {
        $post = BlogPost::published()->where('slug', $slug)->firstOrFail();
        $related = BlogPost::published()->whereKeyNot($post->id)
            ->orderByDesc('published_at')->limit(2)->get();

        return view('jaspest.blog.show', compact('post', 'related'));
    }

    public function sitemap(): Response
    {
        $posts = BlogPost::published()->select('slug', 'updated_at')->orderByDesc('published_at')->get();

        return response()->view('jaspest.blog.sitemap', compact('posts'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\nDisallow: /admin/\n\nSitemap: ".route('jaspest.sitemap')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
