<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Satu kontrak kini boleh punya banyak invoice (satu per penagihan kunjungan).
        //    Kolom enquiry_id tadinya UNIQUE; ganti dengan index biasa dulu supaya foreign key
        //    tetap punya index, baru unique-nya dilepas.
        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            $table->index('enquiry_id', 'jfin_invoice_enquiry_idx');
        });

        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            $table->dropUnique('jaspest_finance_invoices_enquiry_id_unique');
        });

        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            // Terisi hanya untuk invoice yang berasal dari kontrak berkala.
            $table->foreignId('contract_id')->nullable()->after('enquiry_id')
                ->constrained('jaspest_contracts')->nullOnDelete();
        });

        // 2) Tiap kunjungan kontrak mencatat invoice yang menagihnya dan nilai saat ditagih.
        //    invoice_id NULL = kunjungan belum ditagih.
        Schema::table('jaspest_contract_visits', function (Blueprint $table): void {
            $table->foreignId('invoice_id')->nullable()->after('status')
                ->constrained('jaspest_finance_invoices')->nullOnDelete();
            $table->decimal('billed_amount', 15, 2)->nullable()->after('invoice_id');
            $table->index(['contract_id', 'invoice_id'], 'jcvisit_contract_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::table('jaspest_contract_visits', function (Blueprint $table): void {
            $table->dropIndex('jcvisit_contract_invoice_idx');
            $table->dropConstrainedForeignId('invoice_id');
            $table->dropColumn('billed_amount');
        });

        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('contract_id');
        });

        // Catatan: mengembalikan UNIQUE gagal bila sudah ada order dengan lebih dari satu invoice.
        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            $table->unique('enquiry_id', 'jaspest_finance_invoices_enquiry_id_unique');
        });

        Schema::table('jaspest_finance_invoices', function (Blueprint $table): void {
            $table->dropIndex('jfin_invoice_enquiry_idx');
        });
    }
};
