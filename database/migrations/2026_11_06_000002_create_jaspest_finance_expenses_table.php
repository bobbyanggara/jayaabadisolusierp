<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_finance_expenses', function (Blueprint $table): void {
            $table->id();
            $table->date('expense_date');
            $table->string('category', 30);
            $table->string('description', 250);
            $table->string('payee', 200)->nullable();
            $table->decimal('amount', 15, 2);
            // transfer = keluar dari rekening bank (Master Data), cash = keluar dari Kas Besar / Kas Kecil.
            $table->string('method', 30);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();

            // Rekening sumber dana. INT agar kompatibel dengan jaspest_finance_bank_accounts.id.
            // Snapshot nama/nomor/pemilik disimpan supaya riwayat tetap utuh walau rekening dihapus dari master.
            $table->unsignedInteger('bank_account_id')->nullable();
            $table->string('bank_name', 80)->nullable();
            $table->string('bank_account_number', 100)->nullable();
            $table->string('bank_account_holder', 200)->nullable();

            // Akun kas untuk pengeluaran tunai (111.01 KAS BESAR / 111.02 KAS KECIL).
            $table->string('cash_account_code', 20)->nullable();
            $table->string('cash_account_name', 200)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('expense_date', 'jfin_expense_date_idx');
            $table->index(['bank_account_id', 'expense_date'], 'jfin_expense_bank_date_idx');
            $table->index('category', 'jfin_expense_category_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_finance_expenses');
    }
};
