<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        // Google Maps share/embed URLs can exceed the old 500-character field size.
        Schema::table('jaspest_content_items', function (Blueprint $table): void {
            $table->text('description')->nullable()->change();
        });

        $now = now();
        $settings = [
            [
                'title' => 'Alamat Kantor',
                'slug' => 'alamat-kantor',
                'category' => 'Lokasi Kantor',
                'description' => 'Jl. Taruna Kav.IX No.126 Blk N - O, Sritanjung, Wage, Kec. Taman, Kabupaten Sidoarjo, Jawa Timur 61257',
                'sort_order' => 5,
            ],
            [
                'title' => 'Nomor Telepon Kantor',
                'slug' => 'telepon-kantor',
                'category' => 'Lokasi Kantor',
                'description' => '(031) 85582382',
                'sort_order' => 6,
            ],
            [
                'title' => 'Link Google Maps',
                'slug' => 'link-google-maps',
                'category' => 'Lokasi Kantor',
                'description' => null,
                'sort_order' => 7,
            ],
        ];

        foreach ($settings as $setting) {
            $exists = DB::table('jaspest_content_items')
                ->where('type', 'settings')
                ->where('slug', $setting['slug'])
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('jaspest_content_items')->insert($setting + [
                'type' => 'settings',
                'body' => null,
                'image_path' => null,
                'image_alt' => null,
                'status' => 'active',
                'data' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Do not delete editable office settings on rollback; they may have been
        // populated or modified by an administrator after the migration ran.
    }
};
