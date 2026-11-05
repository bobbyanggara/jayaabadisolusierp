<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_finance_bank_accounts', function (Blueprint $table): void {
            // INT PK dipakai agar kompatibel dengan snapshot bank_account_id pada invoice/payment.
            $table->increments('id');
            $table->string('bank_name', 80);
            // Nomor rekening wajib berupa string agar angka nol di depan tidak hilang.
            $table->string('account_number', 100);
            $table->string('account_holder', 200);
            $table->string('account_label', 200)->nullable();
            $table->string('branch', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'sort_order'], 'jfin_bank_active_sort_idx');
            $table->index('bank_name', 'jfin_bank_name_idx');
        });

        // Migrasikan delapan rekening dari bahan keuangan ke Master Data dengan ID stabil.
        // ID stabil menjaga konsistensi seed rekening pada instalasi awal.
        $accounts = config('jaspest_finance.bank_accounts', []);
        $now = now();
        foreach (array_values($accounts) as $index => $account) {
            if (! is_array($account) || empty($account['id']) || empty($account['bank_name']) || empty($account['account_number']) || empty($account['account_holder'])) {
                continue;
            }
            DB::table('jaspest_finance_bank_accounts')->insert([
                'id' => (int) $account['id'],
                'bank_name' => trim((string) $account['bank_name']),
                'account_number' => trim((string) $account['account_number']),
                'account_holder' => trim((string) $account['account_holder']),
                'account_label' => null,
                'branch' => null,
                'is_active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_finance_bank_accounts');
    }
};
