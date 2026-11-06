<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JaspestBlogAdminController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::orderByDesc('updated_at')->paginate(15);

        return view('jaspest.blog.admin.index', compact('posts'));
    }

    public function create(): View
    {
        $post = new BlogPost(['status' => 'draft', 'author_name' => 'Tim JASPEST']);

        return view('jaspest.blog.admin.form', compact('post'));
    }

    public function store(Request $request): RedirectResponse
    {
        $post = BlogPost::create($this->validated($request));

        return redirect()->route('jaspest.blog.admin.edit', $post)
            ->with('status', 'Blog berhasil disimpan.');
    }

    public function edit(BlogPost $post): View
    {
        return view('jaspest.blog.admin.form', compact('post'));
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $post->update($this->validated($request, $post));

        return back()->with('status', 'Perubahan blog berhasil disimpan.');
    }

    private function validated(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:160'],
            'slug' => ['required', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($post?->getKey())],
            'category' => ['required', 'string', 'max:60'],
            'excerpt' => ['required', 'string', 'max:300'],
            'body' => ['required', 'string', 'min:40', 'max:60000'],
            'author_name' => ['required', 'string', 'max:100'],
            'cover' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            'cover_alt' => ['nullable', 'string', 'max:180'],
            'meta_title' => ['nullable', 'string', 'max:100'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'published_at' => ['nullable', 'date'],
        ]);

        unset($data['cover']);
        if ($request->hasFile('cover')) {
            $data['cover_path'] = 'storage/'.$request->file('cover')->store('jaspest-blog', 'public');
        }
        if ($data['status'] === 'published' && empty($data['published_at'])) {
            $data['published_at'] = now();
        }

        return $data;
    }
}
