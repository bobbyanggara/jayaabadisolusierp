<!doctype html>
<html lang="id" data-theme="light">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="theme-color" content="#f5f7f9">
  <title>Admin Dashboard | JASPEST</title>
  <link rel="stylesheet" href="{{ asset('jaspest/admin/admin-console.css') }}?v=20261103-25">
  <link rel="icon" type="image/png" href="{{ asset('jaspest/assets/logo-jas.png') }}">
</head>
@php
  $adminName = trim((string) auth()->user()?->name) ?: 'Admin';
  $adminInitials = mb_strtoupper(collect(preg_split('/\s+/', $adminName))->filter()->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
  $canManageAccounts = \Illuminate\Support\Facades\Gate::allows('manage-jaspest-accounts');
  $adminRoleLabel = [
    'direksi' => 'Direksi',
    'finance' => 'Finance',
    'logistik' => 'Logistik',
    'marketing' => 'Marketing',
  ][(string) auth()->user()?->role] ?? 'Administrator';
@endphp
<body class="jas-admin">
  <div class="admin-shell">
    <aside class="sidebar" id="sidebar">
      <a class="admin-brand" href="#dashboard" aria-label="CV Jaya Abadi Solusi, dashboard admin"><span class="brand-tile"><img src="{{ asset('jaspest/assets/logo-jas.png') }}" alt="" width="458" height="368"></span><span class="brand-copy"><strong>JASPEST</strong><small>Admin Panel</small></span></a>
      <nav class="side-nav" aria-label="Navigasi admin">
        <div class="nav-group-title">Ruang Kerja</div>
        <button class="nav-link active" data-page="dashboard"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5M5.5 10v9.5h13V10"/></svg><span>Dashboard</span></button>
        <button class="nav-link" data-page="bookings"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM4 14h5l1 2h4l1-2h5"/></svg><span>Data Booking</span><b class="nav-count" id="bookingNavCount">0</b></button>
        <div class="nav-dropdown" id="masterNavGroup">
          <button class="nav-link nav-parent" id="masterNavToggle" type="button" data-nav-toggle="master" aria-expanded="false" aria-controls="masterSubmenu">
            <svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="9" cy="8" r="3"/><path d="M3 20v-1a6 6 0 0 1 12 0v1M16 5.5a3 3 0 0 1 0 5.8M18 14a5 5 0 0 1 3 4.5V20"/></svg>
            <span>Master Data</span>
            <svg class="nav-chevron" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
          </button>
          <div class="nav-submenu" id="masterSubmenu" hidden>
            @if($canManageAccounts)
            <button class="nav-link nav-sub-link" type="button" data-page="accounts"><span class="nav-sub-indicator" aria-hidden="true"></span><span>Data Karyawan</span></button>
            @endif
            <button class="nav-link nav-sub-link" type="button" data-page="technicians"><span class="nav-sub-indicator" aria-hidden="true"></span><span>Data Teknisi</span></button>
            <button class="nav-link nav-sub-link" type="button" data-page="clients"><span class="nav-sub-indicator" aria-hidden="true"></span><span>Data Klien</span></button>
            <button class="nav-link nav-sub-link" type="button" data-page="stock-catalog"><span class="nav-sub-indicator" aria-hidden="true"></span><span>Data Stok &amp; Bahan</span></button>
          </div>
        </div>
        <button class="nav-link" data-page="inventory"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="m3.5 7.8 8.5 4.4 8.5-4.4M12 12.2V21M8 5l9 4.5"/></svg><span>Inventaris & Gudang</span></button>
        <div class="nav-group-title nav-group-spaced">Kelola Website</div>
        <button class="nav-link" data-page="pages"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M7 3h7l4 4v14H7zM14 3v4h4"/></svg><span>Konten Halaman</span></button>
        <button class="nav-link" data-page="services"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4v16M4 12h16"/></svg><span>Layanan</span></button>
        <button class="nav-link" data-page="pests"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 8a3 3 0 0 1 6 0v8a3 3 0 0 1-6 0zM12 8v11M5 10l4 2M19 10l-4 2M5 17l4-2M19 17l-4-2M10 5 8 3M14 5l2-2"/></svg><span>Jenis Hama</span></button>
        <button class="nav-link" data-page="methods"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 7h14M5 12h14M5 17h14M9 5v4M15 10v4M8 15v4"/></svg><span>Metode Penanganan</span></button>
        <button class="nav-link" data-page="articles"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20l1-4L16 5l3 3L8 19zM14 7l3 3"/></svg><span>Blog & Artikel</span></button>
        <button class="nav-link" data-page="gallery"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M9 9.5h.01"/></svg><span>Galeri</span></button>
        <button class="nav-link" data-page="faqs"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9.5a2.5 2.5 0 1 1 3.5 2.3c-.7.4-1 .9-1 1.7M12 17h.01"/></svg><span>FAQ</span></button>
        <button class="nav-link" data-page="settings"><svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M18.4 5.6l-2.1 2.1M7.7 16.3l-2.1 2.1"/></svg><span>Pengaturan Website</span></button>
      </nav>
    </aside>

    <main class="main-area">
      <header class="topbar">
        <div class="topbar-left"><button class="mobile-menu" id="mobileMenu" type="button" aria-label="Buka menu" aria-controls="sidebar" aria-expanded="false"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg></button><div class="breadcrumb"><strong id="breadcrumbCurrent">Ringkasan</strong><small>Admin Panel · JASPEST</small></div></div>
        <div class="topbar-right"><div class="account-stack"><div class="profile-pill"><span class="avatar">{{ $adminInitials }}</span><span class="profile-text"><strong>{{ $adminName }}</strong><small>{{ $adminRoleLabel }}</small></span><form method="POST" action="{{ route('jaspest.logout') }}" class="logout-form">@csrf<button type="submit" class="logout-btn"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/></svg><span>Keluar</span></button></form></div><a href="/" class="back-site" target="_blank" rel="noopener"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 4h6v6M20 4l-9 9M18 13v6a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h6"/></svg><span>Lihat website</span></a></div></div>
      </header>

      <div class="page-wrap">
        <section class="page-view active" id="page-dashboard">
          <div class="page-heading"><div><h1>Ringkasan</h1><p>Pengajuan konsultasi yang perlu Anda tangani hari ini.</p></div><div class="heading-date"><small>Hari ini</small><strong id="todayDate">31 Oktober 2026</strong></div></div>
          <div class="demo-notice" id="demoNotice" role="alert" hidden><span>!</span><div><strong>Data belum dapat dimuat</strong><small>Muat ulang halaman atau hubungi pengembang.</small></div></div>
          <div class="stats-grid"><button type="button" class="stat-card" data-go="bookings"><span class="stat-icon"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M3 12h18"/></svg></span><span class="stat-body"><strong id="statNew">0</strong><span class="stat-label">Baru</span><small>Belum ada tindakan admin</small></span></button><button type="button" class="stat-card" data-go="bookings"><span class="stat-icon"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 0 1 15.5-6.2L21 8M21 3v5h-5M21 12a9 9 0 0 1-15.5 6.2L3 16M3 21v-5h5"/></svg></span><span class="stat-body"><strong id="statFollow">0</strong><span class="stat-label">Follow-up</span><small>Sudah ditindaklanjuti</small></span></button><button type="button" class="stat-card" data-go="bookings"><span class="stat-icon"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 13l2.5-7h11L20 13M4 13v6h16v-6M4 13h5l1 2h4l1-2h5"/></svg></span><span class="stat-body"><strong id="statConverted">0</strong><span class="stat-label">Deal Order</span><small>Pengajuan berhasil dikonversi</small></span></button><button type="button" class="stat-card" data-go="bookings"><span class="stat-icon"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16v14H4zM8 9h8M8 13h8M8 17h4"/></svg></span><span class="stat-body"><strong id="statTotal">0</strong><span class="stat-label">Total pengajuan</span><small id="statTotalHint">Sejak website aktif</small></span></button></div>
          <div class="dashboard-grid">
            <article class="panel chart-panel"><div class="panel-heading"><div><h2>Pengajuan 7 hari terakhir</h2><p>Batang merah menandai hari ini</p></div><span class="chart-legend"><i></i> Pengajuan</span></div><div class="chart-area"><div class="chart-bars" id="chartBars"></div></div></article>
            <article class="panel action-panel"><div class="panel-heading"><div><h2>Tindak Lanjut</h2><p>Ringkasan pengajuan pelanggan</p></div></div><div class="attention-row"><span class="attention-icon amber-bg"></span><div><strong id="attentionFollow">0 klien follow-up</strong><small id="attentionFollowHint">Pelanggan yang sudah ditindaklanjuti</small></div><button data-go="bookings" class="mini-arrow" aria-label="Buka data booking"><span>Buka</span><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></div><div class="attention-row"><span class="attention-icon order-bg"></span><div><strong id="attentionConverted">0 Deal Order</strong><small>Pengajuan yang berhasil dikonversi</small></div><button data-go="bookings" class="mini-arrow" aria-label="Buka data booking"><span>Buka</span><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></div></article>
          </div>
          <article class="panel table-panel"><div class="panel-heading table-heading"><div><h2>Pengajuan terbaru</h2><p>Lima pengajuan terakhir dari website</p></div><button class="btn btn-secondary btn-sm" data-go="bookings"><span>Lihat semua</span><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg></button></div><div class="table-scroll"><table><thead><tr><th>Klien</th><th>Kebutuhan</th><th>Masuk</th><th>Status</th><th></th></tr></thead><tbody id="recentRows"></tbody></table></div></article>
        </section>

        <section class="page-view" id="page-bookings">
          <div class="page-heading"><div><h1>Data Booking</h1><p>Kelola pengajuan pelanggan dan transaksi layanan.</p></div><div class="heading-actions"><button class="btn btn-primary" id="addManualBooking"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Tambah Order</span></button><button class="btn btn-export" id="exportBookings"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9zM14 3v6h6M8 13l4 6m0-6-4 6M14 13h3m-3 3h3"/></svg><span>Unduh Excel</span></button></div></div>
          <div class="booking-summary"><div class="summary-filter" data-filter="all" role="button" tabindex="0" aria-pressed="false" title="Klik untuk menyaring: Total data"><span class="summary-dot blue-dot"></span><span>Total data</span><strong id="bookingTotal">0</strong></div><div class="summary-filter" data-filter="new" role="button" tabindex="0" aria-pressed="false" title="Klik untuk menyaring: Baru"><span class="summary-dot neutral-dot"></span><span>Baru</span><strong id="bookingNew">0</strong></div><div class="summary-filter" data-filter="followup" role="button" tabindex="0" aria-pressed="false" title="Klik untuk menyaring: Follow-up"><span class="summary-dot amber-dot"></span><span>Follow-up</span><strong id="bookingFollow">0</strong></div><div class="summary-filter" data-filter="converted" role="button" tabindex="0" aria-pressed="false" title="Klik untuk menyaring: Deal Order"><span class="summary-dot order-dot"></span><span>Deal Order</span><strong id="bookingConverted">0</strong></div><div class="summary-filter" data-filter="lost" role="button" tabindex="0" aria-pressed="false" title="Klik untuk menyaring: Tidak Deal"><span class="summary-dot lost-dot"></span><span>Tidak Deal</span><strong id="bookingLost">0</strong></div></div>
          <article class="panel booking-panel"><div class="booking-toolbar"><div class="search-box"><input type="search" id="bookingSearch" placeholder="Cari nama, WhatsApp, atau kebutuhan..." title="Tekan / untuk mencari"></div><select id="statusFilter" aria-label="Filter status"><option value="all">Semua status</option><option value="new">Baru</option><option value="followup">Follow-up</option><option value="converted">Deal Order</option><option value="lost">Tidak Deal</option></select><select id="typeFilter" aria-label="Filter jenis lokasi"><option value="all">Semua lokasi</option><option value="rumah">Rumah</option><option value="bisnis">Bisnis</option></select><div class="date-filter" role="group" aria-label="Filter tanggal masuk"><span class="date-filter-title">Tanggal masuk</span><label class="date-field"><span>Dari</span><input type="date" id="dateFrom" aria-label="Tanggal masuk dari"></label><span class="date-sep" aria-hidden="true">–</span><label class="date-field"><span>Sampai</span><input type="date" id="dateTo" aria-label="Tanggal masuk sampai"></label><button type="button" class="date-clear" id="dateClear" hidden><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 6 12 12M6 18 18 6"/></svg><span>Hapus tanggal</span></button></div></div><div class="table-scroll"><table class="booking-table"><thead><tr><th>Klien</th><th>Kontak</th><th>Kebutuhan</th><th>Lokasi</th><th>Masuk</th><th>Status</th><th class="align-right">Aksi</th></tr></thead><tbody id="bookingRows"></tbody></table><div id="emptyBookings" class="empty-state" hidden><span>⌕</span><strong>Data tidak ditemukan</strong><small>Coba ubah kata pencarian atau filter.</small><button type="button" class="btn btn-secondary btn-sm" id="resetBookingFilters" hidden><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12a9 9 0 1 0 2.64-6.36L3 8M3 3v5h5"/></svg><span>Reset filter</span></button></div></div><div class="table-footer"><span id="tableCount">Memuat data…</span><span id="bookingSourceNote"></span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="bookingsPrev" aria-label="Halaman sebelumnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button><span id="bookingPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="bookingsNext" aria-label="Halaman berikutnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button></div></div></article>
          <p class="privacy-footnote">Foto temuan ditampilkan hanya pada detail pengajuan dan tidak disertakan dalam pesan WhatsApp.</p>
        </section>

        <section class="page-view" id="page-accounts" @unless($canManageAccounts) hidden @endunless>
            <div class="page-heading">
              <div><h1>Data Karyawan</h1><p>Buat dan kelola akun login internal JASPEST berdasarkan divisi perusahaan.</p></div>
              <div class="heading-actions"><button class="btn btn-primary" id="addAccount"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Buat akun</span></button></div>
            </div>
            <div class="booking-summary account-summary" role="group" aria-label="Ringkasan akun">
              <div class="summary-filter" data-account-filter="all" role="button" tabindex="0" aria-pressed="true"><span class="summary-dot blue-dot"></span><span>Semua akun</span><strong id="accountTotal">0</strong></div>
              <div class="summary-filter" data-account-filter="active" role="button" tabindex="0" aria-pressed="false"><span class="summary-dot green-dot"></span><span>Aktif</span><strong id="accountActive">0</strong></div>
              <div class="summary-filter" data-account-filter="inactive" role="button" tabindex="0" aria-pressed="false"><span class="summary-dot red-dot"></span><span>Nonaktif</span><strong id="accountInactive">0</strong></div>
            </div>
            <article class="panel booking-panel master-data-panel">
              <div class="booking-toolbar"><div class="search-box"><input type="search" id="accountSearch" placeholder="Cari nama akun, username, nomor telepon, atau divisi…" autocomplete="off"></div><select id="accountStatusFilter" aria-label="Filter status akun"><option value="all">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select><span class="content-total" id="accountCount">0 akun</span></div>
              <div class="table-scroll"><table class="master-data-table"><thead><tr><th>Nama akun</th><th>Divisi / role</th><th>Nomor telepon</th><th>Status akun</th><th class="align-right">Aksi</th></tr></thead><tbody id="accountRows"></tbody></table>
                <div id="emptyAccounts" class="empty-state" hidden><span>♙</span><strong>Belum ada akun divisi</strong><small>Buat akun login untuk Direksi, Finance, Logistik, atau Marketing.</small><button type="button" class="btn btn-secondary btn-sm" id="emptyAddAccount">Buat akun</button></div>
              </div>
              <div class="table-footer"><span id="accountTableCount">Memuat data…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="accountsPrev" aria-label="Halaman sebelumnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button><span id="accountPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="accountsNext" aria-label="Halaman berikutnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button></div></div>
            </article>
            <p class="privacy-footnote">Data Master khusus untuk akun login. Username menjadi identitas masuk, role menentukan divisi, dan password disimpan dalam bentuk hash. Akun nonaktif tidak dapat login.</p>
        </section>

        <section class="page-view" id="page-technicians">
          <div class="page-heading">
            <div><h1>Data teknisi</h1><p>Kelola profil operasional teknisi, keahlian, area layanan, dan kesiapan penugasan.</p></div>
            <div class="heading-actions"><button class="btn btn-primary" id="addTechnician"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Tambah teknisi</span></button></div>
          </div>
          <div class="booking-summary technician-summary" role="group" aria-label="Ringkasan teknisi">
            <div class="summary-filter" data-technician-filter="all" role="button" tabindex="0" aria-pressed="true"><span class="summary-dot blue-dot"></span><span>Semua teknisi</span><strong id="technicianTotal">0</strong></div>
            <div class="summary-filter" data-technician-filter="available" role="button" tabindex="0" aria-pressed="false"><span class="summary-dot green-dot"></span><span>Tersedia</span><strong id="technicianAvailable">0</strong></div>
            <div class="summary-filter" data-technician-filter="assigned" role="button" tabindex="0" aria-pressed="false"><span class="summary-dot amber-dot"></span><span>Sedang Bertugas</span><strong id="technicianAssigned">0</strong></div>
            <div class="summary-filter" data-technician-filter="off_duty" role="button" tabindex="0" aria-pressed="false"><span class="summary-dot red-dot"></span><span>Di Luar Tugas</span><strong id="technicianOffDuty">0</strong></div>
          </div>
          <article class="panel booking-panel master-data-panel">
            <div class="booking-toolbar"><div class="search-box"><input type="search" id="technicianSearch" placeholder="Cari nama, email, kode teknisi, keahlian, atau area…" autocomplete="off"></div><select id="technicianStatusFilter" aria-label="Filter status teknisi"><option value="all">Semua status</option><option value="available">Tersedia</option><option value="assigned">Sedang Bertugas</option><option value="off_duty">Di Luar Tugas</option></select><span class="content-total" id="technicianCount">0 data</span></div>
            <div class="table-scroll"><table class="master-data-table"><thead><tr><th>Teknisi</th><th>Spesialisasi</th><th>Email</th><th>Area layanan</th><th>Status operasional</th><th class="align-right">Aksi</th></tr></thead><tbody id="technicianRows"></tbody></table>
              <div id="emptyTechnicians" class="empty-state" hidden><span>⚒</span><strong>Belum ada data teknisi</strong><small>Tambahkan teknisi secara manual tanpa membuat akun login.</small><button type="button" class="btn btn-secondary btn-sm" id="emptyAddTechnician">Tambah teknisi</button></div>
            </div>
            <div class="table-footer"><span id="technicianTableCount">Memuat data…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="techniciansPrev" aria-label="Halaman sebelumnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button><span id="technicianPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="techniciansNext" aria-label="Halaman berikutnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button></div></div>
          </article>
          <p class="privacy-footnote">Data teknisi berdiri sendiri dan tidak membutuhkan akun login maupun data dari Master Karyawan.</p>
        </section>

        <section class="page-view" id="page-clients">
          <div class="page-heading">
            <div><h1>Data Klien</h1><p>Terbentuk otomatis dari setiap booking yang dikonfirmasi menjadi Deal Order. Tidak ada penambahan klien secara manual.</p></div>
          </div>
          <div class="booking-summary client-summary" role="group" aria-label="Ringkasan klien">
            <div class="summary-filter"><span class="summary-dot blue-dot"></span><span>Total klien</span><strong id="clientTotal">0</strong></div>
            <div class="summary-filter"><span class="summary-dot green-dot"></span><span>Klien aktif</span><strong id="clientActive">0</strong></div>
            <div class="summary-filter"><span class="summary-dot amber-dot"></span><span>Order tertaut</span><strong id="clientLinkedOrders">0</strong></div>
          </div>

          <article class="panel booking-panel master-data-panel client-panel client-directory-panel" id="clientDirectoryPanel">
            <div class="panel-heading client-panel-heading"><div><h2>Daftar klien</h2><p>Identitas diambil dari booking Deal Order. Nomor WhatsApp yang sama dikelompokkan dalam satu profil dengan seluruh riwayat order.</p></div><span class="client-queue-count" id="clientDirectoryCount">0 klien</span></div>
            <div class="booking-toolbar client-toolbar"><div class="search-box"><input type="search" id="clientSearch" placeholder="Cari nama, kode klien, perusahaan, atau WhatsApp…" autocomplete="off"></div><select id="clientStatusFilter" aria-label="Filter status klien"><option value="all">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select><span class="content-total" id="clientCount">0 klien</span></div>
            <div class="table-scroll"><table class="master-data-table"><thead><tr><th>Klien</th><th>Perusahaan</th><th>WhatsApp</th><th>Jumlah order</th><th>Nilai order</th><th>Status</th><th class="align-right">Aksi</th></tr></thead><tbody id="clientRows"></tbody></table>
              <div id="emptyClients" class="empty-state" hidden><span>♙</span><strong>Belum ada klien dari Deal Order</strong><small>Data klien akan muncul otomatis setelah booking dikonfirmasi menjadi Deal Order.</small></div>
            </div>
            <div class="table-footer"><span id="clientTableCount">Memuat data…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="clientsPrev" aria-label="Halaman klien sebelumnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg></button><span id="clientPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="clientsNext" aria-label="Halaman klien berikutnya" disabled><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg></button></div></div>
          </article>
          <p class="privacy-footnote">Data klien bersumber dari booking Deal Order. Satu nomor WhatsApp menjadi satu profil; booking berikutnya dengan nomor yang sama otomatis ditambahkan ke riwayat order klien tersebut. Tidak tersedia fitur tambah klien manual.</p>
        </section>

        <section class="page-view" id="page-stock-catalog">
          <div class="page-heading stock-catalog-heading">
            <div><h1>Data Stok &amp; Bahan</h1><p>Lihat persediaan aset, peralatan, dan bahan operasional JASPEST tanpa mengubah data inventaris.</p></div>
          </div>
          <div class="stock-catalog-readonly" role="note">
            <svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5h.01"/></svg>
            <span><strong>Mode lihat saja.</strong> Data mengikuti saldo pada modul Inventaris &amp; Gudang. Halaman ini tidak menyediakan tambah, edit, hapus, atau transaksi stok.</span>
          </div>
          <div class="inventory-stat-grid stock-catalog-stat-grid" role="group" aria-label="Ringkasan data stok dan bahan">
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-blue"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5zM3.5 7.8 12 12.2l8.5-4.4M12 12.2V21"/></svg></span><div><small>Barang terdaftar</small><strong id="stockCatalogStatItems">0</strong><span>SKU aktif</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-green"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM8 7V4h8v3M8 12h8M8 16h5"/></svg></span><div><small>Memiliki stok gudang</small><strong id="stockCatalogStatStocked">0</strong><span>SKU dengan saldo gudang</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-amber"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg></span><div><small>Perlu perhatian</small><strong id="stockCatalogStatLow">0</strong><span>Di bawah batas minimum</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-red"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><div><small>Batch kedaluwarsa dekat</small><strong id="stockCatalogStatExpiry">0</strong><span>Sudah lewat atau ≤ 30 hari</span></div></article>
          </div>
          <article class="panel booking-panel stock-catalog-panel">
            <div class="panel-heading stock-catalog-panel-heading"><div><h2>Daftar stok dan bahan</h2><p>Saldo gudang dan saldo teknisi ditampilkan terpisah sesuai satuan masing-masing barang.</p></div><span class="stock-catalog-view-tag"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M2.5 12s3.4-6 9.5-6 9.5 6 9.5 6-3.4 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.5"/></svg> Hanya lihat</span></div>
            <div class="booking-toolbar inventory-toolbar stock-catalog-toolbar">
              <div class="search-box"><input type="search" id="stockCatalogSearch" placeholder="Cari nama barang, SKU, kategori, atau merek…" autocomplete="off" aria-label="Cari data stok dan bahan"></div>
              <select id="stockCatalogTypeFilter" aria-label="Filter jenis barang"><option value="all">Semua jenis</option><option value="asset">Peralatan / aset</option><option value="consumable">Bahan habis pakai</option></select>
              <select id="stockCatalogStatusFilter" aria-label="Filter status barang"><option value="all">Semua status</option><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select>
              <span class="content-total" id="stockCatalogCount">0 barang</span>
            </div>
            <div class="table-scroll">
              <table class="master-data-table inventory-table stock-catalog-table">
                <thead><tr><th>Barang</th><th>Kategori</th><th>Jenis</th><th>Saldo gudang</th><th>Di teknisi</th><th>Stok minimum</th><th>Kondisi stok</th><th>Status</th></tr></thead>
                <tbody id="stockCatalogRows"></tbody>
              </table>
              <div id="stockCatalogEmpty" class="empty-state" hidden><span>▦</span><strong>Data stok belum tersedia</strong><small>Belum ada barang yang cocok dengan pencarian atau filter ini. Data akan tampil setelah master barang tersedia di Inventaris &amp; Gudang.</small></div>
            </div>
            <div class="table-footer"><span id="stockCatalogTableCount">Memuat data stok…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="stockCatalogPrev" aria-label="Halaman sebelumnya" disabled>‹</button><span id="stockCatalogPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="stockCatalogNext" aria-label="Halaman berikutnya" disabled>›</button></div></div>
          </article>
        </section>

        <section class="page-view" id="page-inventory">
          <div class="page-heading inventory-page-heading">
            <div><h1>Inventaris & Gudang</h1><p>Kelola barang, persediaan, distribusi teknisi, pemakaian pekerjaan, dan perawatan aset JASPEST.</p></div>
            <div class="heading-actions"><button type="button" class="btn btn-secondary" id="inventoryAddMovement"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Catat mutasi</span></button><button type="button" class="btn btn-primary" id="inventoryAddItem"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Tambah barang</span></button></div>
          </div>
          <div class="inventory-stat-grid" role="group" aria-label="Ringkasan inventaris">
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-blue"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5zM3.5 7.8 12 12.2l8.5-4.4M12 12.2V21"/></svg></span><div><small>Jenis barang aktif</small><strong id="inventoryStatItems">0</strong><span>SKU terdaftar</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-green"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16v13H4zM8 7V4h8v3M8 12h8M8 16h5"/></svg></span><div><small>Barang memiliki stok</small><strong id="inventoryStatStockedItems">0</strong><span>SKU dengan saldo gudang tersedia</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-green"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 7h18M5 7v13h14V7M8 11h8M8 15h5"/><path d="M9 7V4h6v3"/></svg></span><div><small>Nilai stok gudang</small><strong id="inventoryStatValue">Rp0</strong><span>Estimasi berdasarkan harga batch</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-amber"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3 2.8 20h18.4L12 3Z"/><path d="M12 9v5M12 17h.01"/></svg></span><div><small>Stok perlu perhatian</small><strong id="inventoryStatLow">0</strong><span>Menyentuh atau di bawah batas minimum</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-red"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2M4 4l3 3"/></svg></span><div><small>Batch kedaluwarsa dekat</small><strong id="inventoryStatExpiry">0</strong><span>Dalam 30 hari atau sudah lewat</span></div></article>
            <article class="inventory-stat"><span class="inventory-stat-icon inventory-icon-amber"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v3M12 18v3M4.2 5.2l2.1 2.1m11.4 9.4 2.1 2.1M3 12h3m12 0h3M4.2 18.8l2.1-2.1m11.4-9.4 2.1-2.1"/><circle cx="12" cy="12" r="5"/><path d="M12 9v3l2 1"/></svg></span><div><small>Pemeliharaan jatuh tempo</small><strong id="inventoryStatMaintenance">0</strong><span>Terjadwal dalam 30 hari</span></div></article>
          </div>
          <div class="inventory-alert-grid">
            <article class="panel inventory-alert-panel"><div class="inventory-panel-heading"><div><h2>Perlu restock</h2><p>Barang yang sudah menyentuh batas stok minimum.</p></div><span class="inventory-alert-mark amber-mark"></span></div><div id="inventoryLowStockList" class="inventory-alert-list"><p class="muted-empty">Memuat data stok…</p></div></article>
            <article class="panel inventory-alert-panel"><div class="inventory-panel-heading"><div><h2>Monitoring kedaluwarsa</h2><p>Batch bahan yang perlu diperiksa sebelum digunakan.</p></div><span class="inventory-alert-mark red-mark"></span></div><div id="inventoryExpiryList" class="inventory-alert-list"><p class="muted-empty">Memuat data batch…</p></div></article>
          </div>
          <div class="inventory-tabs" role="tablist" aria-label="Modul inventaris">
            <button type="button" class="inventory-tab is-active" id="inventoryTabButtonItems" data-inventory-tab="items" role="tab" aria-selected="true" aria-controls="inventoryPanelItems">Master barang</button>
            <button type="button" class="inventory-tab" id="inventoryTabButtonMovements" data-inventory-tab="movements" role="tab" aria-selected="false" aria-controls="inventoryPanelMovements">Mutasi stok</button>
            <button type="button" class="inventory-tab" id="inventoryTabButtonTechnician" data-inventory-tab="technicians" role="tab" aria-selected="false" aria-controls="inventoryPanelTechnicians">Stok teknisi</button>
            <button type="button" class="inventory-tab" id="inventoryTabButtonMaintenance" data-inventory-tab="maintenance" role="tab" aria-selected="false" aria-controls="inventoryPanelMaintenance">Pemeliharaan aset</button>
          </div>
          <section class="inventory-tab-panel is-active" id="inventoryPanelItems" role="tabpanel" aria-labelledby="inventoryTabButtonItems">
            <article class="panel booking-panel">
              <div class="booking-toolbar inventory-toolbar"><div class="search-box"><input type="search" id="inventoryItemSearch" placeholder="Cari SKU, nama barang, kategori, atau merek…" autocomplete="off"></div><select id="inventoryItemTypeFilter" aria-label="Filter jenis barang"><option value="all">Semua jenis</option><option value="asset">Aset / peralatan</option><option value="consumable">Bahan habis pakai</option></select><select id="inventoryItemStatusFilter" aria-label="Filter status barang"><option value="active">Barang aktif</option><option value="all">Semua status</option><option value="inactive">Nonaktif</option></select><span class="content-total" id="inventoryItemCount">0 barang</span></div>
              <div class="table-scroll"><table class="master-data-table inventory-table"><thead><tr><th>Barang</th><th>Kategori</th><th>Jenis</th><th>Stok gudang</th><th>Di teknisi</th><th>Status</th><th class="align-right">Aksi</th></tr></thead><tbody id="inventoryItemRows"></tbody></table><div id="inventoryItemsEmpty" class="empty-state" hidden><span>▦</span><strong>Belum ada master barang</strong><small>Tambahkan aset kerja atau bahan habis pakai untuk memulai pencatatan stok.</small><button type="button" class="btn btn-secondary btn-sm" id="inventoryEmptyAddItem">Tambah barang</button></div></div>
              <div class="table-footer"><span id="inventoryItemTableCount">Memuat barang…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="inventoryItemsPrev" aria-label="Halaman sebelumnya" disabled>‹</button><span id="inventoryItemsPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="inventoryItemsNext" aria-label="Halaman berikutnya" disabled>›</button></div></div>
            </article>
          </section>
          <section class="inventory-tab-panel" id="inventoryPanelMovements" role="tabpanel" aria-labelledby="inventoryTabButtonMovements" hidden>
            <article class="panel booking-panel">
              <div class="booking-toolbar inventory-toolbar"><div class="search-box"><input type="search" id="inventoryMovementSearch" placeholder="Cari barang, kode mutasi, teknisi, atau nomor order…" autocomplete="off"></div><select id="inventoryMovementTypeFilter" aria-label="Filter jenis mutasi"><option value="all">Semua mutasi</option><option value="stock_in">Stok masuk</option><option value="stock_out">Stok keluar</option><option value="distribution">Distribusi teknisi</option><option value="usage">Pemakaian pekerjaan</option><option value="return">Pengembalian</option><option value="adjustment_in">Penyesuaian masuk</option><option value="adjustment_out">Penyesuaian keluar</option><option value="write_off">Pemusnahan / barang kedaluwarsa</option></select><span class="content-total" id="inventoryMovementCount">0 mutasi</span></div>
              <div class="table-scroll"><table class="master-data-table inventory-table"><thead><tr><th>Tanggal / kode</th><th>Barang</th><th>Jenis mutasi</th><th>Jumlah</th><th>Nilai mutasi</th><th>Teknisi / order</th><th>Catatan</th></tr></thead><tbody id="inventoryMovementRows"></tbody></table><div id="inventoryMovementsEmpty" class="empty-state" hidden><span>⇄</span><strong>Belum ada riwayat mutasi</strong><small>Catat stok masuk, pengeluaran, distribusi, atau pemakaian untuk melihat pergerakan barang.</small></div></div>
              <div class="table-footer"><span id="inventoryMovementTableCount">Memuat mutasi…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="inventoryMovementsPrev" aria-label="Halaman sebelumnya" disabled>‹</button><span id="inventoryMovementsPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="inventoryMovementsNext" aria-label="Halaman berikutnya" disabled>›</button></div></div>
            </article>
          </section>
          <section class="inventory-tab-panel" id="inventoryPanelTechnicians" role="tabpanel" aria-labelledby="inventoryTabButtonTechnician" hidden>
            <article class="panel booking-panel">
              <div class="booking-toolbar inventory-toolbar"><div class="search-box"><input type="search" id="inventoryTechStockSearch" placeholder="Cari nama teknisi, barang, atau SKU…" autocomplete="off"></div><span class="content-total" id="inventoryTechStockCount">0 saldo</span></div>
              <div class="table-scroll"><table class="master-data-table inventory-table"><thead><tr><th>Teknisi</th><th>Barang</th><th>SKU</th><th>Saldo dibawa</th><th>Batch / Kedaluwarsa</th><th>Status teknisi</th><th>Terakhir diperbarui</th></tr></thead><tbody id="inventoryTechStockRows"></tbody></table><div id="inventoryTechStocksEmpty" class="empty-state" hidden><span>♧</span><strong>Belum ada stok teknisi</strong><small>Gunakan mutasi Distribusi Teknisi untuk mencatat barang yang diserahkan ke teknisi.</small></div></div>
              <div class="table-footer"><span id="inventoryTechStockTableCount">Memuat saldo…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="inventoryTechStocksPrev" aria-label="Halaman sebelumnya" disabled>‹</button><span id="inventoryTechStocksPageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="inventoryTechStocksNext" aria-label="Halaman berikutnya" disabled>›</button></div></div>
            </article>
          </section>
          <section class="inventory-tab-panel" id="inventoryPanelMaintenance" role="tabpanel" aria-labelledby="inventoryTabButtonMaintenance" hidden>
            <article class="panel booking-panel">
              <div class="booking-toolbar inventory-toolbar"><select id="inventoryMaintenanceStatusFilter" aria-label="Filter status pemeliharaan"><option value="all">Semua status</option><option value="planned">Terjadwal</option><option value="completed">Selesai</option><option value="cancelled">Dibatalkan</option></select><span class="content-total" id="inventoryMaintenanceCount">0 jadwal</span><button type="button" class="btn btn-primary btn-sm" id="inventoryAddMaintenance"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Jadwalkan perawatan</span></button></div>
              <div class="table-scroll"><table class="master-data-table inventory-table"><thead><tr><th>Tanggal</th><th>Aset</th><th>Jenis perawatan</th><th>Petugas</th><th>Biaya</th><th>Status</th><th class="align-right">Aksi</th></tr></thead><tbody id="inventoryMaintenanceRows"></tbody></table><div id="inventoryMaintenanceEmpty" class="empty-state" hidden><span>⚙</span><strong>Belum ada jadwal pemeliharaan</strong><small>Buat jadwal inspeksi, pembersihan, perbaikan, kalibrasi, atau servis aset.</small></div></div>
              <div class="table-footer"><span id="inventoryMaintenanceTableCount">Memuat jadwal…</span><div class="table-pagination"><button type="button" class="btn btn-icon-only btn-sm" id="inventoryMaintenancePrev" aria-label="Halaman sebelumnya" disabled>‹</button><span id="inventoryMaintenancePageLabel">1 / 1</span><button type="button" class="btn btn-icon-only btn-sm" id="inventoryMaintenanceNext" aria-label="Halaman berikutnya" disabled>›</button></div></div>
            </article>
          </section>
          <p class="privacy-footnote">Catatan stok diproses secara transaksional. Barang kedaluwarsa tidak dapat dikeluarkan dari gudang, distribusi menambah saldo teknisi, dan pemakaian wajib terhubung dengan Deal Order.</p>
        </section>

        <section class="page-view" id="page-content">
          <div class="page-heading"><div><h1 id="contentPageTitle">Kelola konten</h1><p id="contentPageDesc">Atur informasi yang ditampilkan pada website publik JASPEST.</p></div><button class="btn btn-primary" id="addContent"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg><span>Tambah data</span></button></div>
          <div class="content-intro"><div><strong id="contentIntroTitle">Konten Website Terpusat</strong><p id="contentIntroDesc">Ubah teks, gambar, dan informasi website dari satu tempat.</p></div></div>
          <article class="panel content-panel"><div class="booking-toolbar"><div class="search-box"><input type="search" id="contentSearch" placeholder="Cari konten..."></div><span class="content-total" id="contentTotal">0 data</span></div><div class="content-list" id="contentList"></div></article>
          <p class="privacy-footnote" id="contentSourceNote">Perubahan tersimpan di server dan tampil di website publik jika berstatus Aktif.</p>
        </section>
      </div>
    </main>
  </div>

  <div class="modal-backdrop" id="detailModal" hidden><section class="modal detail-modal" role="dialog" aria-modal="true" aria-labelledby="detailName"><header class="modal-head"><div><h2 id="detailName">Klien</h2><p id="detailRef">REF-0001</p></div><button class="modal-close" data-close="detailModal" aria-label="Tutup">×</button></header><div class="modal-body"><div class="detail-status" id="detailStatus"></div><div class="detail-grid" id="detailFields"></div><section class="detail-section"><h3>Foto dari klien</h3><div class="photo-preview" id="detailPhotos"></div></section><section class="detail-section"><h3>Persetujuan dihubungi</h3><p id="detailConsent"></p></section><section class="detail-section whatsapp-notification-section" id="detailWhatsappNotificationSection" hidden><h3>Status Konfirmasi WhatsApp</h3><div class="whatsapp-notification-card" id="detailWhatsappNotification" aria-live="polite"></div><button type="button" class="btn btn-secondary btn-sm whatsapp-notification-retry" id="retryDealWhatsapp" hidden><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M20 7v5h-5M4 17v-5h5M5.6 9A7 7 0 0 1 18 6l2 2M4 16l2 2a7 7 0 0 0 12.4-3"/></svg><span>Kirim Ulang Pesan</span></button></section><div class="detail-note">Foto dan data klien bersifat privat. Foto tidak ikut terkirim ke chat WhatsApp.</div><section class="detail-section"><h3>Catatan internal</h3><div class="note-form"><textarea id="detailNoteInput" rows="2" maxlength="1000" placeholder="Catat hasil percakapan, jadwal survei, atau hal penting lain (hanya terlihat oleh admin)…"></textarea><button type="button" class="btn btn-secondary btn-sm" id="saveBookingNote"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Tambah catatan</span></button></div></section><section class="detail-section"><h3>Riwayat aktivitas</h3><div id="detailEvents" class="detail-events"></div></section></div><footer class="modal-footer booking-detail-footer">
    <section class="booking-status-footer-group" aria-labelledby="detailStatusActionsTitle">
      <div class="booking-status-footer-heading">
        <h3 id="detailStatusActionsTitle">Perbarui Status</h3>
        <span>Pesan tindak lanjut disiapkan dari data pengajuan dan dapat ditinjau sebelum dikirim.</span>
      </div>
      <div class="booking-status-actions" id="detailStatusActions" role="group" aria-label="Tindak lanjut dan status booking"><button type="button" class="booking-status-button booking-followup-action" id="detailFollowupWhatsapp" aria-pressed="false"><svg class="ui-icon ui-icon-brand" viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 11.7a8.2 8.2 0 0 1-12.1 7.2L4 20l1.1-4A8.2 8.2 0 1 1 20.2 11.7Z"/><path d="M9 8.2c.2-.5.5-.5.8-.5h.5c.2 0 .4.1.5.4l.7 1.6c.1.2.1.4-.1.6l-.5.6c-.2.2-.2.4 0 .6.5.9 1.2 1.5 2.1 1.9.2.1.4.2.6-.1l.7-.8c.2-.2.4-.2.6-.1l1.5.7c.3.1.4.3.4.5 0 .4-.2 1.1-.7 1.5-.5.4-1.1.6-1.7.5-1-.1-2.3-.7-3.5-1.8-1.3-1.2-2.1-2.7-2.3-3.7-.2-.7 0-1.4.4-1.9Z"/></svg><span>Follow-up</span></button><button type="button" class="booking-status-button" data-booking-status="converted" aria-pressed="false"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 12h18M10 12v2h4v-2"/></svg><span>Deal Order</span></button><button type="button" class="booking-status-button" data-booking-status="lost" aria-pressed="false"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m7 7 10 10M17 7 7 17"/></svg><span>Tidak Deal</span></button></div>
    </section>
    
  </footer></section></div>

  <div class="modal-backdrop order-modal-backdrop" id="manualBookingModal" hidden>
    <section class="modal order-modal manual-booking-modal" role="dialog" aria-modal="true" aria-labelledby="manualBookingTitle">
      <header class="modal-head">
        <div><span class="modal-kicker">TRANSAKSI BARU</span><h2 id="manualBookingTitle">Tambah Order</h2><p>Masukkan data pelanggan, layanan, dan detail kesepakatan.</p></div>
        <button type="button" class="modal-close" data-close="manualBookingModal" aria-label="Tutup">×</button>
      </header>
      <form id="manualBookingForm">
        <div class="modal-body">
          <section class="order-form-section" aria-labelledby="manualCustomerTitle">
            <div class="order-section-heading"><span class="order-step">01</span><div><h3 id="manualCustomerTitle">Informasi pelanggan</h3><p>Identitas dan lokasi pekerjaan.</p></div></div>
            <div class="form-two-cols order-field-grid">
              <label class="form-label">Nama pelanggan / PIC <span class="required-mark">*</span><input name="full_name" required minlength="2" maxlength="150" autocomplete="name" placeholder="Nama lengkap"></label>
              <label class="form-label">Jenis lokasi <span class="required-mark">*</span><select name="property_type" id="manualPropertyType" required><option value="rumah">Rumah</option><option value="bisnis">Bisnis</option></select></label>
              <label class="form-label manual-company-field" id="manualCompanyField" hidden>Nama perusahaan <span class="required-mark">*</span><input name="company_name" id="manualCompanyName" maxlength="200" autocomplete="organization" placeholder="Nama perusahaan"></label>
              <label class="form-label">Nomor WhatsApp <span class="required-mark">*</span><input name="whatsapp" required inputmode="tel" autocomplete="tel" maxlength="24" placeholder="08xxxxxxxxxx"></label>
              <label class="form-label">Layanan <span class="required-mark">*</span><select name="need" id="manualServiceSelect" required><option value="">Memuat layanan…</option></select></label>
              <label class="form-label form-field-full">Alamat lokasi <span class="required-mark">*</span><textarea name="address" required minlength="5" maxlength="1000" rows="2" placeholder="Alamat lengkap lokasi pekerjaan"></textarea></label>
              <label class="form-label form-field-full">Catatan kebutuhan<textarea name="description" maxlength="3000" rows="2" placeholder="Informasi tambahan (opsional)"></textarea></label>
            </div>
          </section>
          <section class="order-form-section" aria-labelledby="manualAgreementTitle">
            <div class="order-section-heading"><span class="order-step">02</span><div><h3 id="manualAgreementTitle">Kesepakatan & penugasan</h3><p>Detail yang akan tercatat sebagai Deal Order.</p></div></div>
            <div class="form-two-cols order-field-grid">
              <label class="form-label">Luasan pekerjaan <span class="required-mark">*</span><span class="order-input-shell order-input-suffix"><input name="order_area" type="text" required inputmode="decimal" autocomplete="off" data-format="thousands" data-max-decimals="2" placeholder="120"><span>m²</span></span></label>
              <label class="form-label">Harga kesepakatan <span class="required-mark">*</span><span class="order-input-shell order-input-prefix"><span>Rp</span><input name="order_price" type="text" required inputmode="numeric" autocomplete="off" data-format="thousands" data-max-decimals="0" placeholder="2.500.000"></span></label>
              <label class="form-label">Tanggal pelaksanaan <span class="required-mark">*</span><input name="scheduled_date" type="date" required></label>
              <label class="form-label">Teknisi yang ditugaskan <span class="required-mark">*</span><select name="assigned_technician_id" id="manualTechnicianSelect" required><option value="">Memuat teknisi…</option></select></label>
            </div>
            <p class="order-technician-hint" id="manualTechnicianHint" aria-live="polite"></p>
            <label class="form-checkbox manual-consent"><input type="checkbox" name="consent" value="1" required><span>Pelanggan menyetujui untuk menerima konfirmasi melalui WhatsApp.</span></label>
          </section>
        </div>
        <footer class="modal-footer">
          <button type="button" class="btn btn-outline" data-close="manualBookingModal">Batal</button>
          <button type="submit" class="btn btn-order-confirm" id="manualBookingSubmit"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12.5 9.5 18 20 6"/></svg><span>Simpan Deal Order</span></button>
        </footer>
      </form>
    </section>
  </div>

  <div class="modal-backdrop order-modal-backdrop" id="orderModal" hidden>
    <section class="modal order-modal" role="dialog" aria-modal="true" aria-labelledby="orderModalTitle">
      <header class="modal-head">
        <div><span class="modal-kicker">KONFIRMASI DEAL ORDER</span><h2 id="orderModalTitle">Konfirmasi Deal Order</h2><p id="orderBookingSummary">Lengkapi detail kesepakatan dan penugasan teknisi.</p></div>
        <button type="button" class="modal-close" data-close="orderModal" aria-label="Tutup">×</button>
      </header>
      <form id="orderForm">
        <div class="modal-body">
          <div class="order-form-intro"><span class="order-intro-icon"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M7 3v4m10-4v4M5 5h14a1 1 0 0 1 1 1v13H4V6a1 1 0 0 1 1-1Z"/><path d="m8 13 2.5 2.5L16 10"/></svg></span><div><strong>Konfirmasi detail kesepakatan</strong><p>Lengkapi nilai order dan jadwal. Data akan tersimpan bersama booking setelah dikonfirmasi.</p></div></div>
          <section class="order-form-section" aria-labelledby="orderCommercialTitle"><div class="order-section-heading"><span class="order-step">01</span><div><h3 id="orderCommercialTitle">Kesepakatan layanan</h3><p>Masukkan nilai dan luasan final pekerjaan.</p></div></div><div class="form-two-cols order-field-grid">
            <label class="form-label">Harga kesepakatan <span class="required-mark">*</span><span class="order-input-shell order-input-prefix"><span>Rp</span><input name="order_price" type="text" required inputmode="numeric" autocomplete="off" data-format="thousands" data-max-decimals="0" placeholder="2.500.000" aria-describedby="orderPriceHint"></span><small class="field-hint" id="orderPriceHint">Nilai akhir sesuai kesepakatan dengan klien.</small></label>
            <label class="form-label">Luasan final <span class="required-mark">*</span><span class="order-input-shell order-input-suffix"><input name="order_area" type="text" required inputmode="decimal" autocomplete="off" data-format="thousands" data-max-decimals="2" placeholder="120" aria-describedby="orderAreaHint"><span>m²</span></span><small class="field-hint" id="orderAreaHint">Terisi dari estimasi booking dan dapat disesuaikan.</small></label>
          </div></section>
          <section class="order-form-section" aria-labelledby="orderAssignmentTitle"><div class="order-section-heading"><span class="order-step">02</span><div><h3 id="orderAssignmentTitle">Jadwal & penugasan</h3><p>Tentukan tanggal pelaksanaan dan teknisi yang tersedia.</p></div></div><div class="form-two-cols order-field-grid">
            <label class="form-label">Tanggal pelaksanaan <span class="required-mark">*</span><input name="scheduled_date" type="date" required aria-describedby="orderDateHint"><small class="field-hint" id="orderDateHint">Tanggal rencana pekerjaan di lokasi klien.</small></label>
            <label class="form-label">Teknisi yang ditugaskan <span class="required-mark">*</span><select name="assigned_technician_id" id="orderTechnicianSelect" required><option value="">Memuat daftar teknisi…</option></select><small class="field-hint">Hanya teknisi dengan status Tersedia yang dapat dipilih.</small></label>
          </div><p class="order-technician-hint" id="orderTechnicianHint" aria-live="polite"></p></section>
        </div>
        <footer class="modal-footer">
          <button type="button" class="btn btn-outline" data-close="orderModal">Batal</button>
          <button type="submit" class="btn btn-order-confirm" id="orderSubmitButton"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12.5 9.5 18 20 6"/></svg><span>Konfirmasi & jadikan order</span></button>
        </footer>
      </form>
    </section>
  </div>

  <div class="modal-backdrop" id="whatsappPreviewModal" hidden><section class="modal whatsapp-preview-modal" role="dialog" aria-modal="true" aria-labelledby="whatsappPreviewTitle"><header class="modal-head"><div><h2 id="whatsappPreviewTitle">Pratinjau pesan klien</h2><p>Salin format pesan untuk klien.</p></div><button class="modal-close" data-close="whatsappPreviewModal" aria-label="Tutup">×</button></header><div class="modal-body"><pre id="whatsappPreviewMessage" class="whatsapp-preview-message"></pre></div><footer class="modal-footer"><button class="btn btn-outline" data-close="whatsappPreviewModal">Tutup</button><button class="btn btn-whatsapp" id="copyWhatsAppPreview"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><rect x="8" y="8" width="12" height="13" rx="2"/><path d="M16 8V5a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h2"/></svg><span>Salin format chat</span></button></footer></section></div>
  <div class="modal-backdrop" id="contentModal" hidden><section class="modal content-modal" role="dialog" aria-modal="true" aria-labelledby="contentModalTitle"><header class="modal-head"><div><h2 id="contentModalTitle">Tambah Data</h2><p>Isi informasi yang akan dikelola admin.</p></div><button class="modal-close" data-close="contentModal" aria-label="Tutup">×</button></header><form id="contentForm"><div class="modal-body"><label class="form-label">Nama / Judul<input name="title" required maxlength="180" placeholder="Masukkan nama atau judul"></label><label class="form-label">Slug / URL (opsional)<input name="slug" maxlength="200" placeholder="contoh: pengendalian-rayap"></label><label class="form-label">Kategori / Ringkasan<input name="category" maxlength="120" placeholder="Contoh: Pengendalian Rayap"></label><label class="form-label">Deskripsi singkat<textarea name="description" rows="2" maxlength="500" placeholder="Ringkasan yang tampil pada kartu konten"></textarea></label><label class="form-label">Isi / Detail Konten<textarea name="body" rows="5" maxlength="100000" placeholder="Tulis isi lengkap, jawaban FAQ, atau detail layanan..."></textarea></label><label class="form-label">Kata kunci FAQ (pisahkan dengan koma)<input name="keywords" maxlength="1000" placeholder="rayap, tikus, konsultasi, layanan"></label><div class="form-two-cols"><label class="form-label">Urutan tampil<input name="sort_order" type="number" min="0" max="100000" value="0"></label><label class="form-label">Ikon (opsional)<input name="icon" maxlength="40" placeholder="bug, mouse, shield..."></label></div><label class="form-label">Gambar (JPG, PNG, WebP)<input name="image" type="file" accept="image/jpeg,image/png,image/webp"></label><label class="form-label">Alt text gambar<input name="image_alt" maxlength="180" placeholder="Deskripsi singkat gambar"></label><label class="form-checkbox"><input name="remove_image" type="checkbox" value="1"> Hapus gambar saat ini</label><label class="form-label">Status<select name="status"><option value="Aktif">Aktif / Ditampilkan</option><option value="Draft">Draft</option></select></label></div><footer class="modal-footer"><button type="button" class="btn btn-outline" data-close="contentModal">Batal</button><button type="submit" class="btn btn-primary"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Simpan data</span></button></footer></form></section></div>
  <div class="modal-backdrop" id="confirmModal" hidden><section class="modal confirm-modal" role="alertdialog" aria-modal="true" aria-labelledby="confirmTitle" aria-describedby="confirmMessage"><header class="modal-head"><div><h2 id="confirmTitle">Konfirmasi</h2></div><button type="button" class="modal-close" data-close="confirmModal" aria-label="Tutup">×</button></header><div class="modal-body"><p id="confirmMessage" class="confirm-message"></p></div><footer class="modal-footer"><button type="button" class="btn btn-outline" id="confirmCancel" data-close="confirmModal">Batal</button><button type="button" class="btn btn-primary" id="confirmOk"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg><span>Ya, lanjutkan</span></button></footer></section></div>
  <div class="modal-backdrop" id="accountModal" hidden><section class="modal master-modal" role="dialog" aria-modal="true" aria-labelledby="accountModalTitle"><header class="modal-head"><div><h2 id="accountModalTitle">Buat akun</h2><p>Atur identitas login dan divisi pengguna.</p></div><button class="modal-close" data-close="accountModal" aria-label="Tutup">×</button></header><form id="accountForm"><div class="modal-body">
    <label class="form-label">Nama lengkap <span class="required-mark">*</span><input name="name" required minlength="2" maxlength="255" autocomplete="name" placeholder="Nama pemilik akun"></label>
    <div class="form-two-cols"><label class="form-label">Username <span class="required-mark">*</span><input name="username" required maxlength="50" autocomplete="username" pattern="[A-Za-z0-9._-]+" placeholder="contoh: budi.finance"><small class="field-hint">Huruf, angka, titik, garis bawah, dan tanda hubung.</small></label><label class="form-label">Divisi / role <span class="required-mark">*</span><select name="role" required><option value="" disabled selected>Pilih divisi</option><option value="direksi">Direksi</option><option value="finance">Finance</option><option value="logistik">Logistik</option><option value="marketing">Marketing</option></select></label></div>
    <label class="form-label">Nomor telepon <span class="required-mark">*</span><input name="phone" type="tel" required minlength="8" maxlength="24" inputmode="tel" autocomplete="tel" placeholder="Contoh: 081234567890"><small class="field-hint">Gunakan 8–15 digit. Boleh diawali +62.</small></label>
    <div class="form-two-cols"><label class="form-label">Password <span class="required-mark" id="accountPasswordRequired">*</span><input name="password" type="password" minlength="8" autocomplete="new-password" placeholder="Minimal 8 karakter"><small class="field-hint" id="accountPasswordHint">Wajib diisi saat membuat akun.</small></label><label class="form-label">Konfirmasi password<input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" placeholder="Ulangi password"></label></div>
    <label class="form-label">Status akun<select name="is_active" required><option value="1" selected>Aktif</option><option value="0">Nonaktif</option></select><small class="field-hint">Akun nonaktif tidak dapat masuk ke sistem.</small></label>
  </div><footer class="modal-footer"><button type="button" class="btn btn-outline" data-close="accountModal">Batal</button><button type="submit" class="btn btn-primary"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Simpan akun</span></button></footer></form></section></div>

  <div class="modal-backdrop" id="technicianModal" hidden><section class="modal master-modal" role="dialog" aria-modal="true" aria-labelledby="technicianModalTitle"><header class="modal-head"><div><h2 id="technicianModalTitle">Tambah teknisi</h2><p>Isi data teknisi secara mandiri, tanpa membuat akun login.</p></div><button class="modal-close" data-close="technicianModal" aria-label="Tutup">×</button></header><form id="technicianForm"><div class="modal-body">
    <div class="form-two-cols"><label class="form-label">Nama lengkap <span class="required-mark">*</span><input name="full_name" required maxlength="150" autocomplete="name" placeholder="Nama lengkap teknisi"></label><label class="form-label">Email <span class="required-mark">*</span><input name="email" type="email" required maxlength="150" autocomplete="email" placeholder="nama@contoh.com"></label></div>
    <label class="form-label">Kode teknisi <small class="field-hint">Opsional · dibuat otomatis bila kosong</small><input name="technician_code" maxlength="30" placeholder="Contoh: TKN-0001"></label>
    <label class="form-label">Spesialisasi <span class="required-mark">*</span><input name="specialization" required maxlength="120" placeholder="Contoh: Pest Control, Termite Control"></label>
    <div class="form-two-cols"><label class="form-label">Wilayah layanan<input name="service_area" maxlength="180" placeholder="Contoh: Bandung Raya"></label><label class="form-label">Sertifikasi / kompetensi<input name="certification" maxlength="180" placeholder="Nama sertifikasi (opsional)"></label></div>
    <label class="form-label">Alamat<textarea name="address" rows="2" maxlength="2000" placeholder="Alamat teknisi (opsional)"></textarea></label>
    <label class="form-label">Status operasional<select name="operational_status" required><option value="available">Tersedia</option><option value="assigned">Sedang Bertugas</option><option value="off_duty">Di Luar Tugas</option></select><small class="field-hint">Saat dipilih pada konfirmasi order, status otomatis menjadi Sedang bertugas. Setelah pekerjaan selesai, ubah kembali menjadi Tersedia.</small></label>
    <label class="form-label">Catatan teknisi<textarea name="notes" rows="3" maxlength="3000" placeholder="Keahlian tambahan, catatan perlengkapan, atau informasi operasional"></textarea></label>
  </div><footer class="modal-footer"><button type="button" class="btn btn-outline" data-close="technicianModal">Batal</button><button type="submit" class="btn btn-primary"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Simpan teknisi</span></button></footer></form></section></div>
  <div class="modal-backdrop" id="clientModal" hidden>
    <section class="modal master-modal" role="dialog" aria-modal="true" aria-labelledby="clientModalTitle">
      <header class="modal-head"><div><h2 id="clientModalTitle">Edit data klien</h2><p>Perbarui identitas dan informasi kontak klien. Riwayat booking tidak ikut diubah.</p></div><button type="button" class="modal-close" data-close="clientModal" aria-label="Tutup">×</button></header>
      <form id="clientForm"><div class="modal-body">
        <div class="client-code-readonly" id="clientCodeReadonly">Kode klien</div>
        <label class="form-label">Nama lengkap klien <span class="required-mark">*</span><input name="full_name" required minlength="2" maxlength="150" placeholder="Nama klien"></label>
        <label class="form-label">Nama perusahaan / instansi<input name="company_name" maxlength="200" placeholder="Opsional untuk klien perorangan"></label>
        <div class="form-two-cols"><label class="form-label">Nomor WhatsApp <span class="required-mark">*</span><input name="whatsapp" required maxlength="24" inputmode="tel" placeholder="08xxxxxxxxxx"><small class="field-hint">Nomor ini digunakan untuk mencegah klien terdaftar dua kali.</small></label><label class="form-label">Email<input name="email" type="email" maxlength="150" placeholder="email@contoh.com"></label></div>
        <label class="form-label">Alamat<textarea name="address" rows="3" maxlength="3000" placeholder="Alamat klien"></textarea></label>
        <label class="form-label">Status klien<select name="status" required><option value="active">Aktif</option><option value="inactive">Nonaktif</option></select></label>
        <label class="form-label">Catatan<textarea name="notes" rows="3" maxlength="3000" placeholder="Catatan internal (opsional)"></textarea></label>
      </div><footer class="modal-footer"><button type="button" class="btn btn-outline" data-close="clientModal">Batal</button><button type="submit" class="btn btn-primary"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Simpan perubahan</span></button></footer></form>
    </section>
  </div>

  <div class="modal-backdrop" id="inventoryItemModal" hidden>
    <section class="modal master-modal inventory-form-modal" role="dialog" aria-modal="true" aria-labelledby="inventoryItemModalTitle">
      <header class="modal-head"><div><h2 id="inventoryItemModalTitle">Tambah barang</h2><p>Daftarkan aset kerja atau bahan persediaan ke master inventaris.</p></div><button type="button" class="modal-close" data-close="inventoryItemModal" aria-label="Tutup">×</button></header>
      <form id="inventoryItemForm"><div class="modal-body">
        <div class="form-two-cols"><label class="form-label">Nama barang <span class="required-mark">*</span><input name="name" required minlength="2" maxlength="180" placeholder="Contoh: Knapsack Sprayer 16 L"></label><label class="form-label">SKU / kode barang <small class="field-hint">Kosongkan untuk dibuat otomatis.</small><input name="sku" maxlength="40" placeholder="Contoh: EQP-SPR-001"></label></div>
        <div class="form-two-cols"><label class="form-label">Kategori <span class="required-mark">*</span><input name="category" list="inventoryCategorySuggestions" required maxlength="80" placeholder="Contoh: Sprayer & fogging, Insektisida, APD"><datalist id="inventoryCategorySuggestions"><option value="Peralatan kerja"><option value="Sprayer & fogging"><option value="Rodent control"><option value="Insektisida"><option value="Umpan serangga"><option value="Rodentisida"><option value="Termitisida"><option value="Larvasida"><option value="APD"><option value="Monitoring"><option value="Keselamatan kerja"><option value="Consumable"></datalist></label><label class="form-label">Jenis barang <span class="required-mark">*</span><select name="item_type" required><option value="consumable">Bahan habis pakai</option><option value="asset">Aset / peralatan</option></select></label></div>
        <div class="form-two-cols"><label class="form-label">Merek<input name="brand" maxlength="120" placeholder="Merek barang (opsional)"></label><label class="form-label">Satuan <span class="required-mark">*</span><input name="unit" required maxlength="24" placeholder="Pcs, Liter, Kg, Set, Unit"></label></div>
        <label class="form-label">Batas stok minimum <span class="required-mark">*</span><input name="minimum_stock" type="number" min="0" max="999999999" step="0.001" value="0" required><small class="field-hint">Sistem memberi peringatan saat stok gudang menyentuh batas ini.</small></label>
        <label class="form-label">Spesifikasi / keterangan<textarea name="specification" rows="3" maxlength="3000" placeholder="Ukuran, kapasitas, formulasi, konsentrasi, atau spesifikasi penting lainnya."></textarea></label>
        <label class="form-checkbox"><input name="track_expiry" type="checkbox" value="1"> Lacak tanggal kedaluwarsa untuk setiap batch masuk</label>
        <label class="form-label">Status barang<select name="is_active" required><option value="1" selected>Aktif</option><option value="0">Nonaktif</option></select></label>
      </div><footer class="modal-footer"><button type="button" class="btn btn-outline" data-close="inventoryItemModal">Batal</button><button type="submit" class="btn btn-primary"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Simpan barang</span></button></footer></form>
    </section>
  </div>

  <div class="modal-backdrop" id="inventoryMovementModal" hidden>
    <section class="modal master-modal inventory-form-modal" role="dialog" aria-modal="true" aria-labelledby="inventoryMovementModalTitle">
      <header class="modal-head"><div><h2 id="inventoryMovementModalTitle">Catat mutasi stok</h2><p>Setiap mutasi tersimpan sebagai riwayat dan memperbarui saldo secara otomatis.</p></div><button type="button" class="modal-close" data-close="inventoryMovementModal" aria-label="Tutup">×</button></header>
      <form id="inventoryMovementForm"><div class="modal-body">
        <label class="form-label">Jenis mutasi <span class="required-mark">*</span><select name="movement_type" id="inventoryMovementType" required><option value="stock_in">Stok masuk</option><option value="stock_out">Stok keluar</option><option value="distribution">Distribusi ke teknisi</option><option value="usage">Pemakaian pada pekerjaan</option><option value="return">Pengembalian dari teknisi</option><option value="adjustment_in">Penyesuaian stok masuk</option><option value="adjustment_out">Penyesuaian stok keluar</option><option value="write_off">Pemusnahan / barang kedaluwarsa</option></select></label>
        <label class="form-label">Barang <span class="required-mark">*</span><select name="item_id" id="inventoryMovementItem" required><option value="">Pilih barang</option></select><small class="field-hint" id="inventoryMovementStockHint">Pilih barang untuk melihat saldo gudang.</small></label>
        <div class="form-two-cols"><label class="form-label">Jumlah <span class="required-mark">*</span><input name="quantity" type="number" min="0.001" max="999999999" step="0.001" required placeholder="Contoh: 5"></label><label class="form-label" id="inventoryMovementCostField">Harga satuan (Rp)<input name="unit_cost" type="number" min="0" max="99999999999" step="0.01" value="0" placeholder="0"><small class="field-hint">Diisi untuk stok masuk dan penyesuaian masuk.</small></label></div>
        <div class="inventory-conditional-fields" id="inventoryInboundFields">
          <div class="form-two-cols"><label class="form-label" id="inventoryBatchNumberField">Nomor batch<input name="batch_number" maxlength="80" placeholder="Nomor batch dari kemasan"></label><label class="form-label">Tanggal kedaluwarsa<input name="expires_at" type="date"><small class="field-hint" id="inventoryExpiryHint">Isi jika barang memiliki tanggal kedaluwarsa.</small></label></div>
          <div class="form-two-cols" id="inventorySupplierFields"><label class="form-label">Supplier<input name="supplier_name" maxlength="180" placeholder="Nama pemasok"></label><label class="form-label">Nomor invoice<input name="invoice_number" maxlength="100" placeholder="Nomor invoice / nota"></label></div>
        </div>
        <div class="inventory-conditional-fields" id="inventoryTechnicianFields"><label class="form-label">Teknisi <span class="required-mark">*</span><select name="technician_id" id="inventoryMovementTechnician"><option value="">Pilih teknisi</option></select><small class="field-hint">Teknisi yang sedang di luar tugas tidak tersedia untuk transaksi.</small></label></div>
        <div class="inventory-conditional-fields" id="inventoryOrderFields"><label class="form-label">Pekerjaan / Deal Order <span class="required-mark">*</span><select name="enquiry_id" id="inventoryMovementOrder"><option value="">Pilih Deal Order</option></select><small class="field-hint">Pemakaian wajib dikaitkan dengan Deal Order agar biaya bahan dapat ditelusuri per pekerjaan.</small></label></div>
        <label class="form-label" id="inventoryMovementNotesField">Catatan / alasan<textarea name="notes" rows="3" maxlength="3000" placeholder="Contoh: bahan dipakai untuk pekerjaan REF-... atau alasan stok keluar."></textarea><small class="field-hint">Wajib diisi untuk stok keluar, penyesuaian, dan pemusnahan.</small></label>
      </div><footer class="modal-footer"><button type="button" class="btn btn-outline" data-close="inventoryMovementModal">Batal</button><button type="submit" class="btn btn-primary"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h16M12 4l8 8-8 8"/></svg><span>Simpan mutasi</span></button></footer></form>
    </section>
  </div>

  <div class="modal-backdrop" id="inventoryMaintenanceModal" hidden>
    <section class="modal master-modal inventory-form-modal" role="dialog" aria-modal="true" aria-labelledby="inventoryMaintenanceModalTitle">
      <header class="modal-head"><div><h2 id="inventoryMaintenanceModalTitle">Jadwalkan pemeliharaan</h2><p>Buat jadwal inspeksi atau perawatan untuk aset operasional.</p></div><button type="button" class="modal-close" data-close="inventoryMaintenanceModal" aria-label="Tutup">×</button></header>
      <form id="inventoryMaintenanceForm"><div class="modal-body">
        <label class="form-label">Aset <span class="required-mark">*</span><select name="item_id" id="inventoryMaintenanceItem" required><option value="">Pilih aset</option></select></label>
        <div class="form-two-cols"><label class="form-label">Jenis pemeliharaan <span class="required-mark">*</span><select name="maintenance_type" required><option value="inspection">Inspeksi</option><option value="cleaning">Pembersihan</option><option value="repair">Perbaikan</option><option value="calibration">Kalibrasi</option><option value="servicing">Servis berkala</option></select></label><label class="form-label">Tanggal jadwal <span class="required-mark">*</span><input name="scheduled_date" type="date" required></label></div>
        <div class="form-two-cols"><label class="form-label">Petugas / teknisi<select name="technician_id" id="inventoryMaintenanceTechnician"><option value="">Belum ditentukan</option></select></label><label class="form-label">Estimasi biaya (Rp)<input name="cost" type="number" min="0" max="99999999999" step="0.01" value="0"></label></div>
        <label class="form-label">Catatan<textarea name="notes" rows="3" maxlength="3000" placeholder="Keluhan, pekerjaan yang perlu dilakukan, atau hasil inspeksi."></textarea></label>
      </div><footer class="modal-footer"><button type="button" class="btn btn-outline" data-close="inventoryMaintenanceModal">Batal</button><button type="submit" class="btn btn-primary"><svg class="ui-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h12l3 3v13H4V4zM8 4v6h8V4M8 20v-6h8v6"/></svg><span>Simpan jadwal</span></button></footer></form>
    </section>
  </div>

  <div class="toast-stack" id="toastStack" role="region" aria-label="Notifikasi"></div>
  <script>window.JASPEST_ADMIN_CONFIG = { mode: 'api', apiBase: @json($adminApiBase), loginUrl: @json($adminLoginUrl), canManageAccounts: @json($canManageAccounts) };</script>
  @vite('resources/js/app.js')
  <script src="{{ asset('jaspest/admin/admin.js') }}?v=20261103-25" defer></script>
</body>
</html>
