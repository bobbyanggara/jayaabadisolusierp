<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const HINT = 'Deskripsi Singkat → tempel link video TikTok, contoh https://www.tiktok.com/@akun/video/1234567890123456789 (link pendek vm.tiktok.com juga bisa). Nama / Judul → judul yang tampil pada sampul video. Kategori / Ringkasan → nama sumber, mis. @jaspest; kosongkan agar diambil otomatis dari link. Gambar → sampul sebelum video diputar (opsional). Status: Aktif → video tampil, Draft → disembunyikan.';

    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $exists = DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->where('slug', 'video-tiktok')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();

        // Dibuat sebagai Draft: video baru tampil setelah link diisi dan status diubah ke Aktif.
        DB::table('jaspest_content_items')->insert([
            'type' => 'pages',
            'title' => 'Video kegiatan JASPEST',
            'slug' => 'video-tiktok',
            'category' => null,
            'description' => null,
            'body' => null,
            'image_path' => null,
            'image_alt' => null,
            'status' => 'draft',
            'sort_order' => 9,
            'data' => json_encode(['hint' => self::HINT], JSON_UNESCAPED_UNICODE),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->where('slug', 'video-tiktok')
            ->delete();
    }
};
