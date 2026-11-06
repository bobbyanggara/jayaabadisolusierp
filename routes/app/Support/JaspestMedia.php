<?php

namespace App\Support;

/**
 * Membuat URL gambar upload (disk "public") yang selalu benar di host/port mana pun.
 *
 * Storage::disk('public')->url() bergantung pada APP_URL. Bila APP_URL tidak sama dengan
 * alamat yang dipakai browser (mis. http://localhost vs http://127.0.0.1:8000), gambar
 * upload tidak tampil. URL relatif root ("/storage/...") tidak punya masalah itu.
 */
final class JaspestMedia
{
    public static function url(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (preg_match('#^(https?:)?//#i', $path) === 1) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, 8);
        }

        // Gunakan URL root-relative, bukan url(): APP_URL dapat berbeda dari host/port
        // yang sedang dibuka pengguna (contoh localhost vs 127.0.0.1).
        // Endpoint khusus ini juga tidak bergantung pada symlink public/storage.
        return '/jaspest-media/'.implode('/', array_map('rawurlencode', explode('/', $path)));
    }

    /**
     * Placeholder bawaan untuk jenis hama / metode penanganan yang belum punya foto.
     * Mengembalikan null untuk tipe lain.
     */
    public static function placeholder(string $type, ?string $slug): ?string
    {
        if (! in_array($type, ['pests', 'methods'], true)) {
            return null;
        }

        $slug = strtolower(preg_replace('/[^a-z0-9-]/i', '', (string) $slug) ?? '');
        $dir = 'jaspest/assets/placeholders/';

        if ($slug !== '' && is_file(public_path($dir.$type.'-'.$slug.'.svg'))) {
            return asset($dir.$type.'-'.$slug.'.svg');
        }

        return asset($dir.$type.'-default.svg');
    }
}
