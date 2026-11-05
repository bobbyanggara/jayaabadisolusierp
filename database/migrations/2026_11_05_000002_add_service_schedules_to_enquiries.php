<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            // Daftar layanan beserta tanggal pelaksanaan masing-masing: [{"slug": "...", "scheduled_date": "Y-m-d"}]
            $table->json('service_schedules')->nullable()->after('scheduled_date');
        });
    }

    public function down(): void
    {
        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            $table->dropColumn('service_schedules');
        });
    }
};
