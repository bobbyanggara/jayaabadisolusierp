<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    /** Pemutar TikTok hanya memakai link; sampul unggahan lama dibersihkan. */
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $row = DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', 'video-tiktok')->first();
        if (! $row || blank($row->image_path)) {
            return;
        }

        try {
            Storage::disk(config('jaspest_admin.content_disk', 'public'))->delete($row->image_path);
        } catch (Throwable) {
            // File sudah tidak ada atau disk tidak tersedia; data tetap dibersihkan.
        }

        DB::table('jaspest_content_items')->where('id', $row->id)->update([
            'image_path' => null,
            'image_alt' => null,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Sampul yang sudah dihapus tidak dapat dipulihkan.
    }
};
