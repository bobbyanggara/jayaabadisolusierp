<?php

return [
    // Masukkan ID user internal yang boleh membuka dashboard. Daftar kosong = tidak ada akses.
    'editor_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('JASPEST_ADMIN_USER_IDS', ''))
    ), fn (string $id): bool => $id !== '')),
    'login_path' => env('JASPEST_ADMIN_LOGIN_PATH', '/admin'),
    'prefix' => trim((string) env('JASPEST_ADMIN_PREFIX', 'admin/jaspest'), '/'),
    'photo_disk' => 'local', // Foto booking disimpan privat, tidak di public/storage.
    'content_disk' => 'public',
    // Teknisi dengan job aktif lebih dari angka ini hanya diberi peringatan (tidak diblokir).
    'technician_job_warning_threshold' => (int) env('JASPEST_TECHNICIAN_JOB_WARNING', 5),
    // Realtime dashboard (Laravel Reverb). Aktif bila BROADCAST_CONNECTION=reverb dan REVERB_APP_KEY terisi.
    // Alamat untuk browser (REVERB_CLIENT_*) boleh berbeda dengan alamat server-ke-server (REVERB_*),
    // mis. Reverb di balik Nginx pada domain publik.
    'realtime' => [
        'enabled' => env('BROADCAST_CONNECTION', 'null') === 'reverb' && filled(env('REVERB_APP_KEY')),
        'key' => env('REVERB_APP_KEY'),
        'host' => env('REVERB_CLIENT_HOST', env('REVERB_HOST')),
        'port' => (int) env('REVERB_CLIENT_PORT', env('REVERB_PORT', 443)),
        'scheme' => env('REVERB_CLIENT_SCHEME', env('REVERB_SCHEME', 'https')),
        'path' => env('REVERB_CLIENT_PATH', ''),
    ],
];
