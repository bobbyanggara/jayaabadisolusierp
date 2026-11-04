<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SLUGS = ['tentang-visi', 'tentang-misi', 'tentang-motto'];

    /**
     * Visi, Misi, dan Motto dihapus dari halaman utama. Barisnya ikut dihapus
     * agar tidak lagi muncul di Manajemen Konten pada dashboard admin.
     */
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->whereIn('slug', self::SLUGS)
            ->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $now = now();
        $rows = [
            ['slug' => 'tentang-visi', 'title' => 'Visi', 'category' => 'Tentang · Visi', 'sort_order' => 5,
                'description' => 'Menjadi mitra pest control terdepan yang terpercaya, berintegritas, dan berorientasi pada kepuasan pelanggan.', 'body' => null],
            ['slug' => 'tentang-misi', 'title' => 'Misi', 'category' => 'Tentang · Misi', 'sort_order' => 6, 'description' => null,
                'body' => implode("\n", [
                    'Memberikan pelayanan pest control yang aman dan efektif dengan standar kualitas tertinggi.',
                    'Menjunjung tinggi integritas, kejujuran, dan transparansi dalam setiap aspek operasional dan hubungan pelanggan.',
                    'Mengembangkan kompetensi tenaga ahli yang profesional dan beretika tinggi.',
                ])],
            ['slug' => 'tentang-motto', 'title' => '“WE CARE AND SERVE”', 'category' => 'Motto Perusahaan', 'sort_order' => 7,
                'description' => 'Kami peduli dan memberikan pelayanan terbaik.', 'body' => null],
        ];

        foreach ($rows as $row) {
            if (DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', $row['slug'])->exists()) {
                continue;
            }

            DB::table('jaspest_content_items')->insert($row + [
                'type' => 'pages',
                'image_path' => null,
                'image_alt' => null,
                'status' => 'active',
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
};
