<?php

use App\Http\Controllers\JaspestMediaController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'jaspest.landing')->name('home');

// Cadangan penyaji gambar upload bila symlink public/storage belum dibuat.
// Tanpa grup "web" agar tidak memulai sesi atau memasang cookie untuk file statis.
Route::get('/jaspest-media/{path}', [JaspestMediaController::class, 'show'])
    ->where('path', '.+')
    ->withoutMiddleware('web')
    ->name('jaspest.media.file');

Route::get('/storage/{path}', [JaspestMediaController::class, 'show'])
    ->where('path', '.+')
    ->withoutMiddleware('web')
    ->name('jaspest.media');
