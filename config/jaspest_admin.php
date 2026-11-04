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
];
