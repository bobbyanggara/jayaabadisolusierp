<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Bagian halaman utama yang tampil di website (sama dengan PAGE_SLOTS di JaspestAdminApiController). */
    private const SLUGS = [
        'beranda-hero', 'tentang-jaspest', 'tentang-stempel', 'tentang-keterangan-gambar',
        'tentang-tautan', 'video-tiktok', 'kontak-konsultasi',
    ];

    /** Kolom yang benar-benar dipakai website untuk tiap bagian. Kolom lain dikosongkan. */
    private const USED = [
        'beranda-hero' => ['title', 'description'],
        'tentang-jaspest' => ['title', 'body'],
        'tentang-stempel' => ['title', 'description'],
        'tentang-keterangan-gambar' => ['title'],
        'tentang-tautan' => ['title'],
        'video-tiktok' => ['title', 'description', 'category'],
        'kontak-konsultasi' => ['title', 'description'],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        $now = now();

        // Tentang Kami: paragraf pembuka dan isi digabung dalam satu kolom (tampil sama di website).
        $about = DB::table('jaspest_content_items')->where('type', 'pages')->where('slug', 'tentang-jaspest')->first();
        if ($about && filled($about->description)) {
            DB::table('jaspest_content_items')->where('id', $about->id)->update([
                'body' => trim(trim((string) $about->description)."\n\n".trim((string) $about->body)),
                'description' => null,
                'updated_at' => $now,
            ]);
        }

        foreach (self::USED as $slug => $used) {
            $unused = array_diff(['category', 'description', 'body'], $used);
            if ($unused === []) {
                continue;
            }

            DB::table('jaspest_content_items')
                ->where('type', 'pages')
                ->where('slug', $slug)
                ->update(array_fill_keys($unused, null) + ['updated_at' => $now]);
        }

        // Selain video, semua bagian selalu tampil di website; status tidak lagi bisa diatur.
        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->whereIn('slug', array_diff(self::SLUGS, ['video-tiktok']))
            ->where('status', '<>', 'active')
            ->update(['status' => 'active', 'updated_at' => $now]);
    }

    public function down(): void
    {
        // Pembersihan data; isi lama yang tidak dipakai website tidak dipulihkan.
    }
};
