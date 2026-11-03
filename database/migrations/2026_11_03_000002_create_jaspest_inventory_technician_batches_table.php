<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_inventory_technician_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('jaspest_inventory_items')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('jaspest_technicians')->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained('jaspest_inventory_batches')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'technician_id', 'batch_id'], 'jaspest_inventory_tech_batch_unique');
            $table->index(['technician_id', 'quantity'], 'jinv_tech_batch_tech_qty_idx');
            $table->index(['batch_id', 'quantity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_inventory_technician_batches');
    }
};
