<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $now = now();
        $items = [
            ['title' => 'ISO 9001', 'slug' => 'iso-9001', 'category' => 'Standar Sistem Manajemen Mutu', 'description' => 'Identitas standar sistem manajemen mutu.', 'image_alt' => 'Logo ISO 9001', 'sort_order' => 1, 'default_image' => 'assets/certifications/iso-9001.png'],
            ['title' => 'ASPPHAMI', 'slug' => 'aspphami', 'category' => 'Keanggotaan Asosiasi', 'description' => 'Asosiasi Perusahaan Pengendalian Hama Indonesia.', 'image_alt' => 'Logo ASPPHAMI — Asosiasi Perusahaan Pengendalian Hama Indonesia', 'sort_order' => 2, 'default_image' => 'assets/certifications/aspphami.png'],
        ];

        foreach ($items as $item) {
            if (DB::table('jaspest_content_items')->where('type', 'certifications')->where('slug', $item['slug'])->exists()) {
                continue;
            }

            DB::table('jaspest_content_items')->insert([
                'type' => 'certifications', 'title' => $item['title'], 'slug' => $item['slug'],
                'category' => $item['category'], 'description' => $item['description'], 'body' => null,
                'image_path' => null, 'image_alt' => $item['image_alt'], 'status' => 'active',
                'sort_order' => $item['sort_order'],
                'data' => json_encode(['default_image' => $item['default_image'], 'seeded_default' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) return;
        DB::table('jaspest_content_items')->where('type', 'certifications')->whereIn('slug', ['iso-9001', 'aspphami'])->delete();
    }
};
