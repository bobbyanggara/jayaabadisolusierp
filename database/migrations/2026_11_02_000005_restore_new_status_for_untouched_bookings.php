<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Older releases incorrectly converted every untouched booking to Follow-up.
        // Restore Baru only when no contact, WhatsApp, follow-up, or conversion action
        // has ever been recorded. Explicitly progressed bookings keep their current state.
        DB::table('jaspest_enquiries')
            ->where('status', 'followup')
            ->whereNull('wa_opened_at')
            ->whereNull('contacted_at')
            ->whereNull('followed_up_at')
            ->whereNull('converted_at')
            ->update(['status' => 'new']);

        // A submission event always describes the initial state, regardless of
        // the booking's current status. Keep that part of the timeline truthful.
        DB::table('jaspest_enquiry_events')
            ->where('event', 'submitted')
            ->update(['to_status' => 'new']);

        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            $table->string('status', 20)->default('new')->change();
        });
    }

    public function down(): void
    {
        DB::table('jaspest_enquiries')
            ->where('status', 'new')
            ->update(['status' => 'followup']);

        DB::table('jaspest_enquiry_events')
            ->where('event', 'submitted')
            ->where('to_status', 'new')
            ->update(['to_status' => 'followup']);

        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            $table->string('status', 20)->default('followup')->change();
        });
    }
};
