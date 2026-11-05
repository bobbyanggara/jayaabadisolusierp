<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_finance_invoices', function (Blueprint $table): void {
            $table->id();
            $table->string('invoice_number', 100)->nullable()->unique();
            $table->foreignId('enquiry_id')->unique()->constrained('jaspest_enquiries')->restrictOnDelete();
            $table->date('invoice_date');
            $table->date('due_date');
            $table->string('billing_name', 200);
            $table->text('billing_address');
            $table->string('job_type', 250);
            $table->string('location_name', 200);
            $table->text('job_address');
            $table->string('work_period', 180);
            $table->decimal('work_amount', 15, 2);
            $table->decimal('ppn_rate', 5, 2)->default(0);
            $table->decimal('ppn_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->text('amount_words');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['due_date', 'invoice_date'], 'jfin_invoice_dates_idx');
        });

        Schema::create('jaspest_finance_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained('jaspest_finance_invoices')->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 15, 2);
            $table->string('method', 30);
            $table->string('reference', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['invoice_id', 'payment_date'], 'jfin_payment_invoice_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_finance_payments');
        Schema::dropIfExists('jaspest_finance_invoices');
    }
};
