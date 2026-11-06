<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 24)->nullable()->after('username');
            // Email is retained for legacy integrations, but division accounts no longer require it.
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('phone');
        });

        // Restore NOT NULL only when no account depends on a missing email address.
        if (! DB::table('users')->whereNull('email')->exists()) {
            Schema::table('users', function (Blueprint $table): void {
                $table->string('email')->nullable(false)->change();
            });
        }
    }
};
