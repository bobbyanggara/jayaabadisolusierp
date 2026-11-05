<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_contracts', function (Blueprint $table): void {
            $table->id();
            $table->string('contract_number', 32)->unique();
            // Enquiry berstatus "converted" yang menjadi induk data pelanggan kontrak.
            $table->foreignId('enquiry_id')->nullable()->constrained('jaspest_enquiries')->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained('jaspest_clients')->nullOnDelete();
            $table->string('service', 100);
            $table->string('period_type', 10);              // weekly | monthly | yearly
            $table->unsignedSmallInteger('visits_per_period'); // kuota kunjungan per periode
            $table->unsignedSmallInteger('duration_periods');  // jumlah periode kontrak
            $table->date('start_date');
            $table->date('end_date');
            $table->string('price_basis', 12)->default('total'); // total | per_visit
            $table->decimal('price_amount', 15, 2);
            $table->decimal('total_price', 15, 2);
            $table->decimal('area', 12, 2)->nullable();
            $table->foreignId('default_technician_id')->nullable()->constrained('jaspest_technicians')->nullOnDelete();
            $table->string('default_technician_name', 150)->nullable();
            $table->string('status', 12)->default('active'); // active | completed | cancelled
            $table->text('notes')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'end_date'], 'jcontract_status_end_idx');
        });

        Schema::create('jaspest_contract_visits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contract_id')->constrained('jaspest_contracts')->cascadeOnDelete();
            $table->unsignedSmallInteger('period_index');   // 1..N, 0 = kunjungan tambahan
            $table->unsignedSmallInteger('slot_no');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('scheduled_date')->nullable();
            $table->foreignId('technician_id')->nullable()->constrained('jaspest_technicians')->nullOnDelete();
            $table->string('technician_name', 150)->nullable();
            $table->string('status', 12)->default('unscheduled'); // unscheduled | scheduled | done | skipped | cancelled
            $table->boolean('is_extra')->default(false);
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['contract_id', 'period_index'], 'jcvisit_contract_period_idx');
            $table->index(['technician_id', 'scheduled_date'], 'jcvisit_tech_date_idx');
            $table->index(['status', 'scheduled_date'], 'jcvisit_status_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_contract_visits');
        Schema::dropIfExists('jaspest_contracts');
    }
};
