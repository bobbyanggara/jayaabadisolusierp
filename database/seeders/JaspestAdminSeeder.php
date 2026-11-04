<?php

namespace Database\Seeders;

use App\Models\JaspestContentItem;
use Illuminate\Database\Seeder;

class JaspestAdminSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            // Bagian halaman utama: hanya kolom yang tampil di website yang diisi (lihat PAGE_SLOTS di JaspestAdminApiController).
            ['type' => 'pages', 'title' => 'Rumah nyaman. Bisnis tetap jalan.', 'slug' => 'beranda-hero', 'description' => 'Jaga ruang yang berarti bagi Anda. Konsultasikan kebutuhan pengendalian hama rumah dan bisnis bersama JASPEST.', 'sort_order' => 1],
            ['type' => 'pages', 'title' => 'CV. JAYA ABADI SOLUSI', 'slug' => 'tentang-jaspest', 'category' => 'JASPEST', 'description' => 'Sejak 2008 di Sidoarjo', 'data' => ['cta' => 'Mari bicarakan kebutuhan Anda'], 'body' => "JASPEST adalah perusahaan yang sudah berpengalaman lebih dari 15 tahun di bidang Jasa Pest Control (pengendalian hama).\n\nJASPEST berdiri di Sidoarjo sejak tahun 2008. Kami hadir sebagai mitra terpercaya bagi sektor residensial (rumah tinggal) maupun komersial (bisnis/industri).\n\nKami menerapkan Sistem IPM (Integrated Pest Management) dalam pengendalian hama yang ramah lingkungan, aman, dan efektif. Bagi kami, mewujudkan kepuasan pelanggan adalah prioritas utama dalam setiap layanan yang kami berikan.", 'sort_order' => 2],
            ['type' => 'pages', 'title' => 'Video kegiatan JASPEST', 'slug' => 'video-tiktok', 'status' => 'draft', 'sort_order' => 3],
            ['type' => 'pages', 'title' => 'Mulai dari kebutuhan Anda', 'slug' => 'kontak-konsultasi', 'description' => 'Isi pengajuan agar tim JASPEST dapat memahami kebutuhan dan menghubungi Anda.', 'sort_order' => 4],

            ['type' => 'services', 'title' => 'Pest Control', 'slug' => 'pest-control', 'category' => 'Pengendalian hama umum', 'description' => 'Bantu jaga kenyamanan ruang Anda dari gangguan hama yang tidak diinginkan.', 'body' => 'Ceritakan jenis gangguan atau temuan hama di lokasi Anda. Tim JASPEST akan membahas kebutuhan pengendalian hama yang sesuai dengan kondisi rumah maupun tempat usaha Anda.', 'data' => ['icon' => 'bug'], 'sort_order' => 1],
            ['type' => 'services', 'title' => 'Rodent Control', 'slug' => 'rodent-control', 'category' => 'Pengendalian tikus', 'description' => 'Pendekatan pengendalian tikus untuk kebutuhan hunian dan lingkungan usaha.', 'body' => 'Layanan untuk kebutuhan pengendalian tikus di hunian atau tempat usaha. Sampaikan lokasi dan temuan yang Anda jumpai agar tim dapat memahami kebutuhan Anda.', 'data' => ['icon' => 'mouse'], 'sort_order' => 2],
            ['type' => 'services', 'title' => 'Termite Control', 'slug' => 'termite-control', 'category' => 'Pengendalian rayap', 'description' => 'Diskusikan kebutuhan perlindungan bangunan dan material Anda dari rayap.', 'body' => 'Sampaikan temuan yang diduga berkaitan dengan rayap, bagian bangunan yang terdampak, serta perkiraan luas lokasi.', 'data' => ['icon' => 'shield'], 'sort_order' => 3],
            ['type' => 'services', 'title' => 'Fumigasi', 'slug' => 'fumigasi', 'category' => 'Kebutuhan fumigasi', 'description' => 'Konsultasikan kebutuhan fumigasi untuk barang, komoditas, dan lokasi usaha.', 'body' => 'Jelaskan jenis barang atau komoditas, perkiraan luasan, dan kebutuhan fumigasi Anda.', 'data' => ['icon' => 'warehouse'], 'sort_order' => 4],
            ['type' => 'services', 'title' => 'Disinfeksi', 'slug' => 'disinfeksi', 'category' => 'Kebutuhan disinfeksi', 'description' => 'Bantu rawat lingkungan Anda melalui layanan disinfeksi sesuai kebutuhan.', 'body' => 'Layanan disinfeksi untuk kebutuhan rumah atau lingkungan bisnis.', 'data' => ['icon' => 'sparkles'], 'sort_order' => 5],

            ['type' => 'pests', 'title' => 'Rayap', 'slug' => 'rayap', 'category' => 'Hama kayu dan bangunan', 'description' => 'Ciri aktivitas rayap, tanda serangan, dan area yang berisiko.', 'body' => 'Periksa jalur tanah, kayu yang rapuh, dan perubahan pada kusen atau furnitur. Jangan membongkar area yang dicurigai sebelum inspeksi.', 'sort_order' => 1],
            ['type' => 'pests', 'title' => 'Tikus', 'slug' => 'tikus', 'category' => 'Hama pengerat', 'description' => 'Jejak, kotoran, jalur masuk, dan risiko kontaminasi.', 'body' => 'Perhatikan kotoran, bekas gigitan, suara di plafon, serta akses makanan dan air.', 'sort_order' => 2],
            ['type' => 'pests', 'title' => 'Kecoa', 'slug' => 'kecoa', 'category' => 'Hama area lembap', 'description' => 'Area persembunyian dan potensi perkembangbiakan kecoa.', 'body' => 'Kecoa sering ditemukan di area lembap, celah, saluran, dan tempat penyimpanan makanan.', 'sort_order' => 3],
            ['type' => 'pests', 'title' => 'Semut', 'slug' => 'semut', 'category' => 'Hama koloni', 'description' => 'Jalur semut, sumber makanan, dan titik sarang.', 'body' => 'Amati jalur pergerakan dan sumber makanan untuk membantu proses identifikasi.', 'sort_order' => 4],

            ['type' => 'methods', 'title' => 'Inspeksi & Identifikasi', 'slug' => 'inspeksi-identifikasi', 'category' => 'Tahap awal penanganan', 'description' => 'Pemeriksaan area untuk mengidentifikasi jenis hama dan tingkat aktivitas.', 'body' => 'Tim melakukan pemeriksaan sesuai akses dan kondisi lokasi sebelum menentukan rekomendasi tindakan.', 'sort_order' => 1],
            ['type' => 'methods', 'title' => 'Spraying', 'slug' => 'spraying', 'category' => 'Aplikasi terarah', 'description' => 'Aplikasi bahan pengendali sesuai hasil inspeksi dan prosedur keselamatan.', 'body' => 'Pelaksanaan dilakukan sesuai label produk, prosedur keselamatan, dan instruksi teknisi berwenang.', 'sort_order' => 2],
            ['type' => 'methods', 'title' => 'Baiting', 'slug' => 'baiting', 'category' => 'Umpan pengendalian', 'description' => 'Penempatan umpan pada titik yang sesuai dengan karakteristik hama.', 'body' => 'Titik penempatan dan monitoring ditentukan setelah identifikasi kebutuhan lokasi.', 'sort_order' => 3],

            // Galeri: sama persis dengan galeri di website (public/jaspest/content.js).
            ['type' => 'gallery', 'title' => 'Perhatian pada setiap sudut hunian', 'slug' => 'perhatian-sudut-hunian', 'category' => 'Rumah', 'description' => 'Ilustrasi pemeriksaan area rumah', 'body' => null, 'data' => ['default_image' => 'assets/residential-inspection.webp'], 'sort_order' => 1],
            ['type' => 'gallery', 'title' => 'Lingkungan usaha yang lebih terjaga', 'slug' => 'lingkungan-usaha-terjaga', 'category' => 'Bisnis', 'description' => 'Ilustrasi pemeriksaan area komersial', 'body' => null, 'data' => ['default_image' => 'assets/hero-pest-control.webp'], 'sort_order' => 2],
            ['type' => 'gallery', 'title' => 'Kebutuhan fasilitas dan pergudangan', 'slug' => 'fasilitas-pergudangan', 'category' => 'Bisnis', 'description' => 'Ilustrasi monitoring di area gudang', 'body' => null, 'data' => ['default_image' => 'assets/commercial-inspection.webp'], 'sort_order' => 3],

            ['type' => 'settings', 'title' => 'Nama Website', 'slug' => 'nama-website', 'category' => 'Identitas', 'description' => 'JASPEST', 'body' => null, 'sort_order' => 1],
            ['type' => 'settings', 'title' => 'Nama Perusahaan', 'slug' => 'nama-perusahaan', 'category' => 'Identitas', 'description' => 'CV Jaya Abadi Solusi', 'body' => null, 'sort_order' => 2],
            ['type' => 'settings', 'title' => 'Tagline Website', 'slug' => 'tagline-website', 'category' => 'Identitas', 'description' => 'Solusi pengendalian hama untuk rumah dan bisnis.', 'body' => null, 'sort_order' => 3],
            ['type' => 'settings', 'title' => 'Teks Hak Cipta', 'slug' => 'teks-copyright', 'category' => 'Identitas', 'description' => '© {tahun} {nama} · {perusahaan}', 'body' => null, 'sort_order' => 3],
            ['type' => 'settings', 'title' => 'Nomor WhatsApp', 'slug' => 'nomor-whatsapp', 'category' => 'Kontak', 'description' => '', 'body' => null, 'status' => 'draft', 'sort_order' => 4],
            ['type' => 'settings', 'title' => 'Alamat Kantor', 'slug' => 'alamat-kantor', 'category' => 'Lokasi Kantor', 'description' => 'Jl. Taruna Kav.IX No.126 Blk N - O, Sritanjung, Wage, Kec. Taman, Kabupaten Sidoarjo, Jawa Timur 61257', 'body' => null, 'sort_order' => 5],
            ['type' => 'settings', 'title' => 'Nomor Telepon Kantor', 'slug' => 'telepon-kantor', 'category' => 'Lokasi Kantor', 'description' => '(031) 85582382', 'body' => null, 'sort_order' => 6],
            ['type' => 'settings', 'title' => 'Email Kantor', 'slug' => 'email-kantor', 'category' => 'Lokasi Kantor', 'description' => 'cv.jayaabadisolusi@yahoo.com', 'body' => null, 'sort_order' => 7],
            ['type' => 'settings', 'title' => 'Link Google Maps', 'slug' => 'link-google-maps', 'category' => 'Lokasi Kantor', 'description' => null, 'body' => null, 'sort_order' => 8],
            ['type' => 'faqs', 'title' => 'Apakah JASPEST melayani rumah dan bisnis?', 'slug' => 'rumah-dan-bisnis', 'category' => 'Layanan', 'description' => 'Jenis lokasi yang dapat dilayani.', 'body' => 'Ya, kebutuhan layanan dapat dikonsultasikan untuk rumah maupun area bisnis.', 'data' => ['keywords' => ['rumah', 'bisnis', 'perusahaan', 'kantor', 'hunian', 'usaha']], 'sort_order' => 1],
            ['type' => 'faqs', 'title' => 'Bagaimana cara mengajukan konsultasi?', 'slug' => 'cara-konsultasi', 'category' => 'Booking', 'description' => 'Cara mengirim formulir pengajuan.', 'body' => 'Isi formulir pengajuan di website, lalu tim JASPEST akan menghubungi Anda melalui WhatsApp atau telepon sesuai persetujuan.', 'data' => ['keywords' => ['cara', 'pengajuan', 'form', 'daftar', 'pesan', 'order', 'konsultasi']], 'sort_order' => 2],
            ['type' => 'faqs', 'title' => 'Apakah perlu mengirim foto temuan?', 'slug' => 'foto-temuan', 'category' => 'Persiapan', 'description' => 'Informasi lampiran foto.', 'body' => 'Foto temuan bersifat opsional, tetapi dapat membantu tim memahami kondisi awal. Ambil foto dari posisi aman.', 'data' => ['keywords' => ['foto', 'gambar', 'upload', 'unggah', 'lampiran']], 'sort_order' => 3],
        ];

        // Migrasi dua item galeri lama agar mengikuti galeri website (foto unggahan, bila ada, tetap dipertahankan).
        $legacyGallery = [
            'inspeksi-residensial' => 0,
            'inspeksi-komersial' => 1,
        ];
        $galleryItems = array_values(array_filter($items, fn (array $item): bool => $item['type'] === 'gallery'));
        foreach ($legacyGallery as $oldSlug => $position) {
            $target = $galleryItems[$position];
            if (JaspestContentItem::where('type', 'gallery')->where('slug', $target['slug'])->exists()) {
                continue;
            }
            JaspestContentItem::where('type', 'gallery')->where('slug', $oldSlug)->first()?->update($target);
        }

        foreach ($items as $item) {
            $item['status'] ??= 'active';
            JaspestContentItem::firstOrCreate(
                ['type' => $item['type'], 'slug' => $item['slug']],
                $item
            );
        }
    }
}
