<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve existing bookings while removing the obsolete "new" status.
        DB::table('jaspest_enquiries')
            ->where('status', 'new')
            ->update(['status' => 'followup']);

        // Keep the activity timeline consistent with the current status vocabulary.
        DB::table('jaspest_enquiry_events')
            ->where('from_status', 'new')
            ->update(['from_status' => 'followup']);

        DB::table('jaspest_enquiry_events')
            ->where('to_status', 'new')
            ->update(['to_status' => 'followup']);

        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            $table->string('status', 20)->default('followup')->change();
        });
    }

    public function down(): void
    {
        // Existing records remain Follow-up; only restore the original column default.
        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            $table->string('status', 20)->default('new')->change();
        });
    }
};
