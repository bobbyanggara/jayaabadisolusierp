<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jaspest_contracts', function (Blueprint $table): void {
            // Daftar layanan kontrak; kolom service lama tetap dipertahankan untuk kompatibilitas.
            $table->json('services')->nullable()->after('service');
        });
    }

    public function down(): void
    {
        Schema::table('jaspest_contracts', function (Blueprint $table): void {
            $table->dropColumn('services');
        });
    }
};
