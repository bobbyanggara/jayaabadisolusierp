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
            ->where('slug', 'email-kantor')
            ->exists();

        if ($exists) {
            return;
        }

        // Email ditaruh tepat setelah nomor telepon; Link Google Maps digeser satu urutan.
        DB::table('jaspest_content_items')
            ->where('type', 'settings')
            ->where('slug', 'link-google-maps')
            ->where('sort_order', 7)
            ->update(['sort_order' => 8]);

        $now = now();
        DB::table('jaspest_content_items')->insert([
            'type' => 'settings',
            'title' => 'Email Kantor',
            'slug' => 'email-kantor',
            'category' => 'Lokasi Kantor',
            'description' => 'cv.jayaabadisolusi@yahoo.com',
            'body' => null,
            'image_path' => null,
            'image_alt' => null,
            'status' => 'active',
            'data' => null,
            'sort_order' => 7,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        // Jangan hapus pengaturan yang mungkin sudah diubah oleh administrator.
    }
};
