<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            // Legacy snapshot column retained for migration compatibility; no longer used by the Finance UI.
            $table->string('job_code', 40)->nullable();
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_account_holder', 200)->nullable();
        });

        Schema::table('jaspest_finance_payments', function (Blueprint $table): void {
            $table->string('receiving_account_code', 20)->nullable();
            $table->string('receiving_account_name', 200)->nullable();
            $table->unsignedInteger('received_bank_account_id')->nullable();
            $table->string('received_bank_name', 80)->nullable();
            $table->string('received_bank_account_number', 100)->nullable();
            $table->string('received_bank_account_holder', 200)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('jaspest_finance_payments', function (Blueprint $table): void {
            $table->dropColumn([
                'receiving_account_code',
                'receiving_account_name',
                'received_bank_account_id',
                'received_bank_name',
                'received_bank_account_number',
                'received_bank_account_holder',
            ]);
        });

        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'job_code',
                'bank_account_id',
                'bank_name',
                'bank_account_number',
                'bank_account_holder',
            ]);
        });
    }
};
