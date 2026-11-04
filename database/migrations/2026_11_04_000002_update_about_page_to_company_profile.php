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

        // Samakan halaman "Tentang Kami" dengan dokumen profil perusahaan.
        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->where('slug', 'tentang-jaspest')
            ->update([
                'title' => 'CV. JAYA ABADI SOLUSI',
                'description' => 'JASPEST adalah perusahaan yang sudah berpengalaman lebih dari 15 tahun di bidang Jasa Pest Control (pengendalian hama).',
                'body' => "JASPEST berdiri di Sidoarjo sejak tahun 2008. Kami hadir sebagai mitra terpercaya bagi sektor residensial (rumah tinggal) maupun komersial (bisnis/industri).\n\nKami menerapkan Sistem IPM (Integrated Pest Management) dalam pengendalian hama yang ramah lingkungan, aman, dan efektif. Bagi kami, mewujudkan kepuasan pelanggan adalah prioritas utama dalam setiap layanan yang kami berikan.",
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->where('slug', 'tentang-jaspest')
            ->update([
                'title' => 'Kenyamanan Anda dimulai dari ruang yang terjaga.',
                'description' => 'CV Jaya Abadi Solusi hadir untuk membantu kebutuhan pengendalian hama di rumah, tempat usaha, dan lingkungan kerja Anda.',
                'body' => 'Kami memahami bahwa setiap lokasi punya kebutuhan berbeda. Ceritakan kondisinya, lalu diskusikan penanganan langsung bersama tim JASPEST.',
                'updated_at' => now(),
            ]);
    }
};
