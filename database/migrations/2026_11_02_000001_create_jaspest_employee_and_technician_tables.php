<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_technicians', function (Blueprint $table): void {
            $table->id();
            $table->string('full_name', 150)->nullable();
            $table->string('whatsapp', 24)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('address')->nullable();
            $table->string('technician_code', 30)->unique();
            $table->string('specialization', 120);
            $table->string('service_area', 180)->nullable();
            $table->string('certification', 180)->nullable();
            $table->string('operational_status', 20)->default('available');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['operational_status', 'specialization']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_technicians');
    }
};
