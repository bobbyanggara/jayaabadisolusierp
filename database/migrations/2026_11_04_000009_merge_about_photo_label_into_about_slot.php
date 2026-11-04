<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DEFAULT_LABEL_TOP = 'JASPEST';
    private const DEFAULT_LABEL_BOLD = 'Sejak 2008 di Sidoarjo';
    private const DEFAULT_CAPTION = 'Ilustrasi pemeriksaan hunian';

    /**
     * Label pada foto Tentang Kami digabung ke satu bagian bersama judul, isi, dan foto
     * (kolom "category" = baris atas label, "description" = baris tebal). Keterangan di bawah foto dihapus.
     */
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $items = fn () => DB::table('jaspest_content_items')->where('type', 'pages');
        $now = now();

        $about = $items()->where('slug', 'tentang-jaspest')->first();
        $stamp = $items()->where('slug', 'tentang-stempel')->first();

        if ($about) {
            $update = ['updated_at' => $now];

            // Sisa data lama: paragraf pembuka yang masih di kolom deskripsi digabung ke isi lebih dulu.
            if (filled($about->description)) {
                $update['body'] = trim(trim((string) $about->description)."\n\n".trim((string) $about->body));
            }

            $update['category'] = filled($stamp?->title) ? $stamp->title : self::DEFAULT_LABEL_TOP;
            $update['description'] = filled($stamp?->description) ? $stamp->description : self::DEFAULT_LABEL_BOLD;

            $items()->where('id', $about->id)->update($update);
        }

        $items()->whereIn('slug', ['tentang-stempel', 'tentang-keterangan-gambar'])->delete();

        foreach (['tentang-tautan' => 3, 'video-tiktok' => 4, 'kontak-konsultasi' => 5] as $slug => $order) {
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

        $rows = [
            [
                'slug' => 'tentang-stempel',
                'title' => filled($about?->category) ? $about->category : self::DEFAULT_LABEL_TOP,
                'category' => 'Tentang · Label pada gambar',
                'description' => filled($about?->description) ? $about->description : self::DEFAULT_LABEL_BOLD,
                'sort_order' => 3,
            ],
            [
                'slug' => 'tentang-keterangan-gambar',
                'title' => self::DEFAULT_CAPTION,
                'category' => 'Tentang · Keterangan gambar',
                'description' => null,
                'sort_order' => 4,
            ],
        ];

        foreach ($rows as $row) {
            $exists = DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', $row['slug'])->exists();
            if ($exists) {
                continue;
            }

            DB::table('jaspest_content_items')->insert($row + [
                'type' => 'pages',
                'body' => null,
                'image_path' => null,
                'image_alt' => null,
                'status' => 'active',
                'data' => json_encode([], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($about) {
            DB::table('jaspest_content_items')->where('id', $about->id)->update([
                'category' => null,
                'description' => null,
                'updated_at' => $now,
            ]);
        }

        foreach (['tentang-tautan' => 5, 'video-tiktok' => 6, 'kontak-konsultasi' => 10] as $slug => $order) {
            DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', $slug)->update(['sort_order' => $order]);
        }
    }
};
