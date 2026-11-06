<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_clients', function (Blueprint $table): void {
            $table->id();
            $table->string('client_code', 32)->unique();
            $table->string('full_name', 150);
            $table->string('company_name', 200)->nullable();
            $table->string('whatsapp', 24);
            $table->string('whatsapp_normalized', 24)->unique();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['status', 'full_name'], 'jclient_status_name_idx');
            $table->index('company_name', 'jclient_company_idx');
        });

        // Gunakan tabel relasi tersendiri agar tidak perlu ALTER TABLE pada
        // jaspest_enquiries; ini lebih kompatibel dengan koneksi SQLite default.
        Schema::create('jaspest_client_enquiry_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('client_id')->constrained('jaspest_clients')->restrictOnDelete();
            $table->foreignId('enquiry_id')->constrained('jaspest_enquiries')->restrictOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique('enquiry_id', 'jclient_link_enquiry_unique');
            $table->index(['client_id', 'created_at'], 'jclient_link_client_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_client_enquiry_links');
        Schema::dropIfExists('jaspest_clients');
    }
};
