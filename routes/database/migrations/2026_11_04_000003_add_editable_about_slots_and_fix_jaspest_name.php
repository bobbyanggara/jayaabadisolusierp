<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('jaspest_content_items')) {
            $this->syncPageSlots();
            $this->fixBrandSpelling('jaspest_content_items', ['title', 'description', 'body', 'category', 'image_alt']);
        }

        if (Schema::hasTable('blog_posts')) {
            $this->fixBrandSpelling('blog_posts', ['title', 'excerpt', 'body', 'category', 'cover_alt', 'meta_title', 'meta_description']);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_content_items')) {
            return;
        }

        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->whereIn('slug', collect($this->slots())->filter(fn (array $slot): bool => isset($slot['title']))->pluck('slug')->all())
            ->delete();

        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->where('slug', 'kontak-konsultasi')
            ->where('sort_order', 10)
            ->update(['sort_order' => 3]);
    }

    /**
     * Slot halaman bawaan. Slot yang punya "title" dibuat bila belum ada;
     * slot lama (hero, tentang, kontak) hanya diberi petunjuk pengisian.
     */
    private function slots(): array
    {
        return [
            [
                'slug' => 'beranda-hero',
                'hint' => 'Nama / Judul → judul besar di bagian paling atas website. Deskripsi Singkat → paragraf di bawah judul. Gambar dan Teks Alternatif Gambar → foto utama di sisi kanan. Kolom lain tidak dipakai.',
            ],
            [
                'slug' => 'tentang-jaspest',
                'hint' => 'Nama / Judul → judul bagian Tentang Kami. Deskripsi Singkat → paragraf pembuka (tampil lebih tebal). Isi Konten → paragraf berikutnya; pisahkan antar paragraf dengan satu baris kosong. Gambar dan Teks Alternatif Gambar → foto di sisi kiri.',
            ],
            [
                'slug' => 'tentang-stempel',
                'title' => 'JASPEST',
                'category' => 'Tentang · Label pada gambar',
                'description' => 'Sejak 2008 di Sidoarjo',
                'body' => null,
                'sort_order' => 3,
                'hint' => 'Nama / Judul → baris atas pada label biru di atas foto. Deskripsi Singkat → baris tebal di bawahnya.',
            ],
            [
                'slug' => 'tentang-keterangan-gambar',
                'title' => 'Ilustrasi pemeriksaan hunian',
                'category' => 'Tentang · Keterangan gambar',
                'description' => null,
                'body' => null,
                'sort_order' => 4,
                'hint' => 'Nama / Judul → teks kecil di bawah foto.',
            ],
            [
                'slug' => 'tentang-visi',
                'title' => 'Visi',
                'category' => 'Tentang · Visi',
                'description' => 'Menjadi mitra pest control terdepan yang terpercaya, berintegritas, dan berorientasi pada kepuasan pelanggan.',
                'body' => null,
                'sort_order' => 5,
                'hint' => 'Nama / Judul → judul kartu. Deskripsi Singkat → isi visi (maksimal 500 karakter).',
            ],
            [
                'slug' => 'tentang-misi',
                'title' => 'Misi',
                'category' => 'Tentang · Misi',
                'description' => null,
                'body' => implode("\n", [
                    'Memberikan pelayanan pest control yang aman dan efektif dengan standar kualitas tertinggi.',
                    'Menjunjung tinggi integritas, kejujuran, dan transparansi dalam setiap aspek operasional dan hubungan pelanggan.',
                    'Mengembangkan kompetensi tenaga ahli yang profesional dan beretika tinggi.',
                ]),
                'sort_order' => 6,
                'hint' => 'Nama / Judul → judul kartu. Isi Konten → daftar misi; tulis satu poin per baris, nomor tampil otomatis.',
            ],
            [
                'slug' => 'tentang-motto',
                'title' => '“WE CARE AND SERVE”',
                'category' => 'Motto Perusahaan',
                'description' => 'Kami peduli dan memberikan pelayanan terbaik.',
                'body' => null,
                'sort_order' => 7,
                'hint' => 'Kategori / Ringkasan → label kecil di atas motto. Nama / Judul → teks motto (tampil persis seperti yang diketik, termasuk tanda kutip). Deskripsi Singkat → kalimat pendukung di bawahnya.',
            ],
            [
                'slug' => 'tentang-tautan',
                'title' => 'Mari bicarakan kebutuhan Anda',
                'category' => 'Tentang · Tautan ajakan',
                'description' => null,
                'body' => null,
                'sort_order' => 8,
                'hint' => 'Nama / Judul → teks pada tombol di bawah paragraf; tombol mengarah ke formulir konsultasi.',
            ],
            [
                'slug' => 'kontak-konsultasi',
                'hint' => 'Nama / Judul → judul bagian formulir konsultasi. Deskripsi Singkat → kalimat pengantar di bawah judul.',
            ],
        ];
    }

    private function syncPageSlots(): void
    {
        $now = now();

        foreach ($this->slots() as $slot) {
            $existing = DB::table('jaspest_content_items')
                ->where('type', 'pages')
                ->where('slug', $slot['slug'])
                ->first();

            if ($existing) {
                $data = is_string($existing->data) ? (json_decode($existing->data, true) ?: []) : [];

                if (empty($data['hint'])) {
                    $data['hint'] = $slot['hint'];
                    DB::table('jaspest_content_items')->where('id', $existing->id)->update([
                        'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                        'updated_at' => $now,
                    ]);
                }

                continue;
            }

            if (! isset($slot['title'])) {
                continue;
            }

            DB::table('jaspest_content_items')->insert([
                'type' => 'pages',
                'title' => $slot['title'],
                'slug' => $slot['slug'],
                'category' => $slot['category'],
                'description' => $slot['description'],
                'body' => $slot['body'],
                'image_path' => null,
                'image_alt' => null,
                'status' => 'active',
                'sort_order' => $slot['sort_order'],
                'data' => json_encode(['hint' => $slot['hint']], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // Slot kontak dipindah ke urutan terakhir agar slot Tentang Kami berkelompok.
        DB::table('jaspest_content_items')
            ->where('type', 'pages')
            ->where('slug', 'kontak-konsultasi')
            ->where('sort_order', 3)
            ->update(['sort_order' => 10]);
    }

    /**
     * Ubah penulisan "JAS PEST" (terpisah) menjadi "JASPEST" pada data yang sudah tersimpan.
     */
    private function fixBrandSpelling(string $table, array $columns): void
    {
        $columns = array_values(array_filter($columns, fn (string $column): bool => Schema::hasColumn($table, $column)));

        if ($columns === []) {
            return;
        }

        foreach (DB::table($table)->select(array_merge(['id'], $columns))->get() as $row) {
            $changes = [];

            foreach ($columns as $column) {
                $value = $row->{$column};

                if (! is_string($value) || $value === '') {
                    continue;
                }

                $fixed = preg_replace('/\bJAS\s*PEST\b/iu', 'JASPEST', $value);

                if ($fixed !== null && $fixed !== $value) {
                    $changes[$column] = $fixed;
                }
            }

            if ($changes !== []) {
                DB::table($table)->where('id', $row->id)->update($changes);
            }
        }
    }
};
