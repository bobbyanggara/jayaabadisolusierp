<?php

use App\Http\Controllers\JaspestBlogAdminController;
use App\Http\Controllers\JaspestBlogController;
use App\Http\Middleware\EnsureJaspestBlogEditor;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function (): void {
    Route::get('/blog', [JaspestBlogController::class, 'index'])->name('jaspest.blog.index');
    Route::get('/blog/{slug}', [JaspestBlogController::class, 'show'])
        ->where('slug', '[a-z0-9]+(?:-[a-z0-9]+)*')->name('jaspest.blog.show');
    Route::get('/sitemap.xml', [JaspestBlogController::class, 'sitemap'])->name('jaspest.sitemap');
    Route::get('/robots.txt', [JaspestBlogController::class, 'robots']);

    Route::prefix('admin/blog')->name('jaspest.blog.admin.')->middleware(EnsureJaspestBlogEditor::class)->group(function (): void {
        Route::get('/', [JaspestBlogAdminController::class, 'index'])->name('index');
        Route::get('/create', [JaspestBlogAdminController::class, 'create'])->name('create');
        Route::post('/', [JaspestBlogAdminController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{post}/edit', [JaspestBlogAdminController::class, 'edit'])->name('edit');
        Route::put('/{post}', [JaspestBlogAdminController::class, 'update'])->middleware('throttle:20,1')->name('update');
    });
});
