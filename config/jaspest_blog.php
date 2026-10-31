<?php

return [
    // Gunakan ID akun admin internal. Daftar kosong menolak seluruh akses CMS.
    'editor_user_ids' => array_values(array_filter(array_map(
        'trim', explode(',', (string) env('JASPEST_BLOG_EDITOR_IDS', ''))
    ), fn (string $id): bool => $id !== '')),
    'login_path' => '/admin/login',
];
