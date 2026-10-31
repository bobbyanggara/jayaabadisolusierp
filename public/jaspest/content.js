/* Konten awal JASPEST. Nantinya diganti dengan konten terbit dari admin Laravel. */
window.JASPEST_CONTENT = {
  brand: { name: 'JASPEST', company: 'Jaya Abadi Solusi', tagline: 'Solusi pengendalian hama untuk rumah dan bisnis.', logo: null },
  services: [
    { id: 'pest-control', name: 'Pest Control', icon: 'bug', label: 'Pengendalian hama umum', description: 'Bantu jaga kenyamanan ruang Anda dari gangguan hama yang tidak diinginkan.', detail: 'Ceritakan jenis gangguan atau temuan hama di lokasi Anda. Tim JASPEST akan membahas kebutuhan pengendalian hama yang sesuai dengan kondisi rumah maupun tempat usaha Anda.' },
    { id: 'rodent-control', name: 'Rodent Control', icon: 'mouse', label: 'Pengendalian tikus', description: 'Pendekatan pengendalian tikus untuk kebutuhan hunian dan lingkungan usaha.', detail: 'Layanan untuk kebutuhan pengendalian tikus di hunian atau tempat usaha. Sampaikan lokasi dan temuan yang Anda jumpai agar tim dapat memahami kebutuhan Anda. Metode dan rincian penanganan dijelaskan langsung oleh tim.' },
    { id: 'termite-control', name: 'Termite Control', icon: 'shield', label: 'Pengendalian rayap', description: 'Diskusikan kebutuhan perlindungan bangunan dan material Anda dari rayap.', detail: 'Sampaikan temuan yang diduga berkaitan dengan rayap, bagian bangunan yang terdampak, serta perkiraan luas lokasi. Tim JASPEST akan menghubungi Anda untuk membahas kebutuhan dan rincian penanganan.' },
    { id: 'fumigasi', name: 'Fumigasi', icon: 'warehouse', label: 'Kebutuhan fumigasi', description: 'Konsultasikan kebutuhan fumigasi untuk barang, komoditas, dan lokasi usaha.', detail: 'Jelaskan jenis barang atau komoditas, perkiraan luasan, dan kebutuhan fumigasi Anda. Kesesuaian layanan serta rincian pelaksanaannya akan dibahas langsung bersama tim JASPEST.' },
    { id: 'disinfeksi', name: 'Disinfeksi', icon: 'sparkles', label: 'Kebutuhan disinfeksi', description: 'Bantu rawat lingkungan Anda melalui layanan disinfeksi sesuai kebutuhan.', detail: 'Layanan disinfeksi untuk kebutuhan rumah atau lingkungan bisnis. Isi pengajuan dengan kondisi dan kebutuhan lokasi Anda. Cakupan serta rincian penanganan akan dijelaskan oleh tim melalui kontak yang Anda berikan.' }
  ],
  pests: [
    { id: 'rayap', name: 'Rayap', category: 'Hama kayu dan bangunan', description: 'Ciri aktivitas rayap, tanda serangan, dan area yang berisiko.', detail: 'Periksa jalur tanah, kayu yang rapuh, dan perubahan pada kusen atau furnitur. Jangan membongkar area yang dicurigai sebelum inspeksi.', image: null },
    { id: 'tikus', name: 'Tikus', category: 'Hama pengerat', description: 'Jejak, kotoran, jalur masuk, dan risiko kontaminasi.', detail: 'Perhatikan kotoran, bekas gigitan, suara di plafon, serta akses makanan dan air.', image: null },
    { id: 'kecoa', name: 'Kecoa', category: 'Hama area lembap', description: 'Area persembunyian dan potensi perkembangbiakan kecoa.', detail: 'Kecoa sering ditemukan di area lembap, celah, saluran, dan tempat penyimpanan makanan.', image: null },
    { id: 'semut', name: 'Semut', category: 'Hama koloni', description: 'Jalur semut, sumber makanan, dan titik sarang.', detail: 'Amati jalur pergerakan dan sumber makanan untuk membantu proses identifikasi.', image: null }
  ],
  methods: [
    { id: 'inspeksi-identifikasi', name: 'Inspeksi & Identifikasi', category: 'Tahap awal penanganan', description: 'Pemeriksaan area untuk mengidentifikasi jenis hama dan tingkat aktivitas.', detail: 'Tim melakukan pemeriksaan sesuai akses dan kondisi lokasi sebelum menentukan rekomendasi tindakan.' },
    { id: 'spraying', name: 'Spraying', category: 'Aplikasi terarah', description: 'Aplikasi bahan pengendali sesuai hasil inspeksi dan prosedur keselamatan.', detail: 'Pelaksanaan dilakukan sesuai label produk, prosedur keselamatan, dan instruksi teknisi berwenang.' },
    { id: 'baiting', name: 'Baiting', category: 'Umpan pengendalian', description: 'Penempatan umpan pada titik yang sesuai dengan karakteristik hama.', detail: 'Titik penempatan dan monitoring ditentukan setelah identifikasi kebutuhan lokasi.' }
  ],
  gallery: [
    { id: 'g1', category: 'Rumah', title: 'Perhatian pada setiap sudut hunian', subtitle: 'Ilustrasi pemeriksaan area rumah', image: '/jaspest/assets/residential-inspection.webp' },
    { id: 'g2', category: 'Bisnis', title: 'Lingkungan usaha yang lebih terjaga', subtitle: 'Ilustrasi pemeriksaan area komersial', image: '/jaspest/assets/hero-pest-control.webp' },
    { id: 'g3', category: 'Bisnis', title: 'Kebutuhan fasilitas dan pergudangan', subtitle: 'Ilustrasi monitoring di area gudang', image: '/jaspest/assets/commercial-inspection.webp' }
  ],
  tiktokVideos: [{ url: 'https://www.tiktok.com/@pestigo.id/video/7647494281975876884', title: 'Video uji TikTok', sourceLabel: '@pestigo.id', isTest: true, cover: '/jaspest/assets/commercial-inspection.webp' }],
  articles: [
    { id: 'persiapan-konsultasi', publishedAt: '2026-10-30', author: 'Tim JASPEST', tag: 'Panduan konsultasi', title: 'Apa yang perlu disiapkan sebelum konsultasi?', excerpt: 'Informasi sederhana yang membantu tim memahami kebutuhan lokasi Anda.', image: '/jaspest/assets/hero-pest-control.webp', minutes: '2 menit baca', keywords: ['konsultasi','persiapan','luas','luasan','alamat'], paragraphs: ['Sebelum mengajukan konsultasi, siapkan nama lengkap, nomor WhatsApp yang aktif, alamat lokasi, serta perkiraan luasan dalam meter persegi. Perkiraan awal membantu tim memahami gambaran kebutuhan Anda.', 'Pilih kebutuhan layanan dan tentukan apakah lokasi berupa rumah atau bisnis. Untuk bisnis, sertakan nama perusahaan. Ceritakan temuan yang Anda lihat melalui kolom keterangan.', 'Anda dapat melampirkan foto temuan jika tersedia. Tidak perlu mendekati atau menyentuh hama untuk mendapatkan foto. Gunakan dokumentasi yang bisa Anda ambil dari posisi aman.', 'Setelah pengajuan dikirim, tim JASPEST akan menghubungi nomor yang Anda cantumkan untuk membahas kebutuhan. Rincian survei, treatment, dan perawatan lanjutan dijelaskan langsung oleh tim.'] },
    { id: 'rumah-dan-bisnis', publishedAt: '2026-10-30', author: 'Tim JASPEST', tag: 'Kenali kebutuhan', title: 'Kebutuhan rumah dan bisnis, apa yang perlu diceritakan?', excerpt: 'Bantu tim mengenali lingkungan dan kebutuhan penggunaan ruang Anda.', image: '/jaspest/assets/residential-inspection.webp', minutes: '2 menit baca', keywords: ['rumah','bisnis','perusahaan','hunian','kantor'], paragraphs: ['Untuk kebutuhan rumah, ceritakan bagian bangunan yang mengalami gangguan dan kapan temuan mulai terlihat. Anda juga dapat menyebutkan informasi penggunaan ruang yang perlu diketahui tim.', 'Untuk kebutuhan bisnis, sertakan nama perusahaan dan jelaskan fungsi area yang memerlukan penanganan. Informasi jam aktivitas atau kondisi khusus lokasi dapat ditulis pada kolom keterangan.', 'Pilih layanan yang paling mendekati kebutuhan Anda. Jika belum mengetahui jenis layanan atau hama, pilih opsi konsultasi dahulu. Tim JASPEST akan membantu membahasnya melalui WhatsApp atau kontak langsung.'] },
    { id: 'foto-temuan', publishedAt: '2026-10-30', author: 'Tim JASPEST', tag: 'Tips dokumentasi', title: 'Foto temuan yang membantu proses konsultasi', excerpt: 'Sertakan gambaran lokasi dan temuan tanpa perlu mengambil risiko.', image: '/jaspest/assets/commercial-inspection.webp', minutes: '2 menit baca', keywords: ['foto','gambar','temuan','lampiran','dokumentasi'], paragraphs: ['Jika memungkinkan, ambil foto yang memperlihatkan temuan dan satu foto area di sekitarnya. Gambaran lokasi membantu tim memahami konteks keterangan Anda.', 'Gunakan pencahayaan yang cukup. Hindari menyentuh hama, memindahkan sarang, atau memasuki area berbahaya hanya untuk mengambil gambar. Foto bersifat opsional dan Anda tetap dapat mengajukan konsultasi tanpa foto.', 'Dokumentasi yang dilampirkan pada pengajuan digunakan untuk membantu pembahasan kebutuhan Anda. Foto pengajuan tidak secara otomatis menjadi galeri publik JASPEST.'] }
  ],
  faqs: [
    { id: 'layanan', question: 'Layanan apa saja yang tersedia?', answer: 'JASPEST menyediakan Pest Control, Rodent Control, Termite Control, Fumigasi, dan Disinfeksi. Pilih layanan yang mendekati kebutuhan Anda atau pilih konsultasi dahulu.', keywords: ['layanan','jasa','tersedia','pest','rodent','rayap','tikus','fumigasi','disinfeksi'], sourceType: 'services', sourceLabel: 'Layanan JASPEST' },
    { id: 'pengajuan', question: 'Bagaimana cara mengajukan konsultasi?', answer: 'Isi formulir pengajuan dengan nama, WhatsApp aktif, alamat, perkiraan luasan, kebutuhan, jenis lokasi, dan keterangan. Foto temuan boleh dilampirkan jika ada. Centang persetujuan agar tim JASPEST dapat menghubungi Anda.', keywords: ['cara','pengajuan','form','daftar','pesan','order','konsultasi','hubungi'], sourceType: 'article', sourceId: 'persiapan-konsultasi', sourceLabel: 'Panduan sebelum konsultasi' },
    { id: 'harga', question: 'Bagaimana mengetahui biaya layanan?', answer: 'Kebutuhan dan penawaran layanan dibahas langsung bersama tim JASPEST. Isi formulir pengajuan agar tim dapat memahami kondisi lokasi dan menghubungi Anda.', keywords: ['harga','biaya','tarif','bayar','berapa','murah','mahal','gratis'], sourceType: 'form', sourceLabel: 'Ajukan konsultasi' },
    { id: 'foto', question: 'Apakah saya wajib mengunggah foto?', answer: 'Foto temuan bersifat opsional. Anda tetap dapat mengajukan konsultasi tanpa foto. Jika melampirkan foto, ambil dari posisi yang aman tanpa menyentuh hama atau mendekati area berbahaya.', keywords: ['foto','gambar','upload','unggah','lampiran','wajib'], sourceType: 'article', sourceId: 'foto-temuan', sourceLabel: 'Tips foto temuan' },
    { id: 'bisnis', question: 'Apakah melayani rumah dan bisnis?', answer: 'Ya. Pada formulir, pilih Rumah atau Bisnis sesuai lokasi Anda. Jika memilih Bisnis, isi juga nama perusahaan agar tim dapat memahami kebutuhan pengajuan.', keywords: ['rumah','bisnis','perusahaan','kantor','hunian','usaha','gudang'], sourceType: 'article', sourceId: 'rumah-dan-bisnis', sourceLabel: 'Kebutuhan rumah dan bisnis' },
    { id: 'penanganan', question: 'Bagaimana rincian penanganannya?', answer: 'Rincian survei, treatment, dan perawatan lanjutan dijelaskan langsung oleh tim JASPEST melalui WhatsApp atau kontak langsung, sesuai kebutuhan lokasi Anda.', keywords: ['survei','survey','treatment','metode','cara kerja','penanganan','perawatan','jadwal','lama','aman','obat','kimia','garansi'], sourceType: 'form', sourceLabel: 'Bicarakan dengan tim' },
    { id: 'laporan', question: 'Di mana saya bisa melihat laporan pekerjaan?', answer: 'Portal klien JASPEST sedang dipersiapkan untuk akses laporan pekerjaan. Informasi terkait pekerjaan Anda dapat dibahas langsung bersama tim JASPEST.', keywords: ['laporan','portal','klien','login','hasil','teknisi','android'], sourceType: 'portal', sourceLabel: 'Informasi portal klien' }
  ]
};

// Jika halaman dirender Laravel, modul admin menyuplai konten aktif dari MySQL.
// Hanya properti yang benar-benar dikirim backend yang menggantikan konten bawaan.
const dynamicContent = window.JASPEST_DYNAMIC_CONTENT || {};
['services', 'gallery', 'faqs', 'pests', 'methods'].forEach((key) => {
  if (Array.isArray(dynamicContent[key])) window.JASPEST_CONTENT[key] = dynamicContent[key];
});
if (dynamicContent.brand && typeof dynamicContent.brand === 'object') {
  window.JASPEST_CONTENT.brand = Object.assign({}, window.JASPEST_CONTENT.brand, dynamicContent.brand);
}

if (Array.isArray(window.JASPEST_PUBLISHED_ARTICLES)) {
  window.JASPEST_CONTENT.articles = window.JASPEST_PUBLISHED_ARTICLES;
}

window.JASPEST_CONFIG = Object.assign({
  previewMode: true,
  enquiryEndpoint: null,
  portalUrl: null,
  whatsappNumber: null,
  maxPhotoCount: 5,
  maxPhotoBytes: 5 * 1024 * 1024
}, window.JASPEST_CONFIG || {});
