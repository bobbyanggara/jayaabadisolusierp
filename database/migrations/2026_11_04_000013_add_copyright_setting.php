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

        $exists = DB::table('jaspest_content_items')
            ->where('type', 'settings')
            ->where('slug', 'teks-copyright')
            ->exists();

        if ($exists) {
            return;
        }

        $now = now();
        DB::table('jaspest_content_items')->insert([
            'type' => 'settings',
            'title' => 'Teks Hak Cipta',
            'slug' => 'teks-copyright',
            'category' => 'Identitas',
            'description' => '© {tahun} {nama} · {perusahaan}',
            'body' => null,
            'image_path' => null,
            'image_alt' => null,
            'status' => 'active',
            'data' => null,
            'sort_order' => 3,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Jangan hapus pengaturan yang mungkin sudah diubah oleh administrator.
    }
};
