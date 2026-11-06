<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_CTA = 'Mari bicarakan kebutuhan Anda';

    private function decode(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return is_string($value) ? (json_decode($value, true) ?: []) : [];
    }

    /**
     * Teks tombol Tentang Kami digabung ke satu bagian bersama judul, isi, foto, dan label foto.
     * Nilainya disimpan pada kolom "data" (kunci "cta") milik bagian tentang-jaspest.
     */
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $items = fn () => DB::table('jaspest_content_items')->where('type', 'pages');
        $now = now();

        $about = $items()->where('slug', 'tentang-jaspest')->first();
        $link = $items()->where('slug', 'tentang-tautan')->first();

        if ($about) {
            $data = $this->decode($about->data);
            $data['cta'] = filled($link?->title) ? $link->title : ($data['cta'] ?? self::DEFAULT_CTA);

            $items()->where('id', $about->id)->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }

        $items()->where('slug', 'tentang-tautan')->delete();

        foreach (['video-tiktok' => 3, 'kontak-konsultasi' => 4] as $slug => $order) {
            $items()->where('slug', $slug)->update(['sort_order' => $order]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $now = now();
        $about = DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', 'tentang-jaspest')->first();
        $data = $about ? $this->decode($about->data) : [];

        $exists = DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', 'tentang-tautan')->exists();
        if (! $exists) {
            DB::table('jaspest_content_items')->insert([
                'type' => 'pages',
                'title' => filled($data['cta'] ?? null) ? $data['cta'] : self::DEFAULT_CTA,
                'slug' => 'tentang-tautan',
                'category' => 'Tentang · Tautan ajakan',
                'description' => null,
                'body' => null,
                'image_path' => null,
                'image_alt' => null,
                'status' => 'active',
                'sort_order' => 5,
                'data' => json_encode([], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($about) {
            unset($data['cta']);
            DB::table('jaspest_content_items')->where('id', $about->id)->update([
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
        }

        foreach (['video-tiktok' => 6, 'kontak-konsultasi' => 10] as $slug => $order) {
            DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', $slug)->update(['sort_order' => $order]);
        }
    }
};
