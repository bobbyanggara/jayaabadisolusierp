<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cadangan untuk /storage/... bila symlink public/storage belum dibuat
 * (php artisan storage:link) atau hosting melarang symlink.
 * Bila symlink ada, web server melayani file langsung dan controller ini tidak dipakai.
 */
class JaspestMediaController extends Controller
{
    private const PREFIXES = ['jaspest-content/', 'jaspest-blog/'];

    private const MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
    ];

    public function show(string $path): Response
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        if ($path === '' || str_contains($path, "\0") || str_contains($path, '..') || str_contains($path, '//')) {
            abort(404);
        }

        $allowedPrefix = false;
        foreach (self::PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                $allowedPrefix = true;
                break;
            }
        }

        $mime = self::MIME[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? null;

        if (! $allowedPrefix || $mime === null) {
            abort(404);
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($path)) {
            abort(404);
        }

        $root = realpath(rtrim($disk->path(''), '/\\'));
        $real = realpath($disk->path($path));

        if ($root === false || $real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR)) {
            abort(404);
        }

        $bytes = @file_get_contents($real);

        if ($bytes === false || $bytes === '') {
            abort(404);
        }

        // Tentukan tipe dari isi file, bukan dari ekstensi.
        $info = @getimagesizefromstring($bytes);
        $detected = is_array($info) ? strtolower((string) ($info['mime'] ?? '')) : '';

        if (! in_array($detected, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            abort(404);
        }

        // Spasi/BOM liar sebelum "<?php" di file PHP mana pun akan ikut terkirim sebelum byte gambar
        // dan membuat gambar rusak. Buang semua output yang sudah tertampung.
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }

        return response($bytes, 200, [
            'Content-Type' => $detected,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'public, max-age=604800',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
