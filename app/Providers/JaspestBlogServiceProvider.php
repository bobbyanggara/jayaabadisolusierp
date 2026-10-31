<?php

namespace App\Providers;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use App\Models\BlogPost;
use Illuminate\Support\ServiceProvider;

class JaspestBlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/jaspest_blog.php', 'jaspest_blog');
    }

    public function boot(): void
    {
        if (! Gate::has('manage-blog')) {
            Gate::define('manage-blog', fn (Authenticatable $user): bool => in_array(
                (string) $user->getAuthIdentifier(), config('jaspest_blog.editor_user_ids', []), true
            ));
        }

        $this->loadRoutesFrom(__DIR__.'/../../routes/jaspest-blog.php');

        View::composer('jaspest.landing', function (\Illuminate\View\View $view): void {
            $view->with('blogPosts', BlogPost::published()->orderByDesc('published_at')->limit(3)->get());
            // Sumber FAQ hanya diarahkan ke tulisan yang masih terbit.
            $view->with('jaspestPublishedArticles', BlogPost::published()
                ->whereIn('slug', ['persiapan-konsultasi', 'foto-temuan', 'rumah-dan-bisnis'])
                ->get(['slug'])->map(fn (BlogPost $post): array => ['id' => $post->slug])->values()->all());
        });
    }
}
