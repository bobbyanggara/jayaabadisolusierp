<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Data Karyawan tidak lagi mengenal akun nonaktif: akun dihapus langsung.
 * Akun divisi yang masih berstatus nonaktif dihapus permanen di sini
 * (akun administrator utama di editor_user_ids tidak disentuh).
 */
return new class extends Migration
{
    public function up(): void
    {
        $protectedIds = array_map('intval', config('jaspest_admin.editor_user_ids', []));

        DB::table('users')
            ->whereIn('role', ['direksi', 'finance', 'logistik', 'marketing'])
            ->where('is_active', false)
            ->when($protectedIds !== [], fn ($query) => $query->whereNotIn('id', $protectedIds))
            ->delete();
    }

    public function down(): void
    {
        // Penghapusan permanen tidak dapat dikembalikan.
    }
};
