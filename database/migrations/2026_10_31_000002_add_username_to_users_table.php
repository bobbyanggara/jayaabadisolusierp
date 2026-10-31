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
            $table->string('username', 50)->nullable()->after('name');
        });

        // Preserve existing accounts: derive a unique username from the email local-part.
        // Example: admin@company.com becomes "admin". Collisions receive _2, _3, etc.
        $used = [];

        DB::table('users')->select(['id', 'email'])->orderBy('id')->each(function (object $user) use (&$used): void {
            $emailParts = explode('@', (string) $user->email, 2);
            $base = strtolower($emailParts[0] ?? '');
            $base = preg_replace('/[^a-z0-9._-]+/', '_', $base) ?? '';
            $base = trim($base, '._-');

            if ($base === '') {
                $base = 'user_'.$user->id;
            }

            $base = substr($base, 0, 50);
            $candidate = $base;
            $suffixNumber = 2;

            while (isset($used[$candidate]) || DB::table('users')
                ->where('username', $candidate)
                ->where('id', '<>', $user->id)
                ->exists()) {
                $suffix = '_'.$suffixNumber++;
                $candidate = substr($base, 0, 50 - strlen($suffix)).$suffix;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
            $used[$candidate] = true;
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->unique('username', 'users_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_username_unique');
            $table->dropColumn('username');
        });
    }
};
