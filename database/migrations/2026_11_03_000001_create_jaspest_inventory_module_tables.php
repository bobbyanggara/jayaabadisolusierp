<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_inventory_items', function (Blueprint $table): void {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name', 180);
            $table->string('category', 80);
            $table->string('item_type', 20)->default('consumable');
            $table->string('brand', 120)->nullable();
            $table->text('specification')->nullable();
            $table->string('unit', 24);
            $table->decimal('minimum_stock', 14, 3)->default(0);
            $table->decimal('stock_on_hand', 14, 3)->default(0);
            $table->boolean('track_expiry')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['item_type', 'is_active']);
            $table->index(['category', 'name']);
        });

        Schema::create('jaspest_inventory_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('jaspest_inventory_items')->cascadeOnDelete();
            $table->string('batch_number', 80)->nullable();
            $table->date('expires_at')->nullable();
            $table->decimal('quantity_received', 14, 3);
            $table->decimal('quantity_remaining', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->string('supplier_name', 180)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->date('received_at');
            $table->timestamps();
            $table->index(['item_id', 'expires_at']);
            $table->index(['item_id', 'quantity_remaining']);
        });

        Schema::create('jaspest_inventory_technician_stocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('jaspest_inventory_items')->cascadeOnDelete();
            $table->foreignId('technician_id')->constrained('jaspest_technicians')->cascadeOnDelete();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
            $table->unique(['item_id', 'technician_id'], 'jinv_tech_stock_item_tech_unique');
            $table->index(['technician_id', 'quantity'], 'jinv_tech_stock_tech_qty_idx');
        });

        Schema::create('jaspest_inventory_movements', function (Blueprint $table): void {
            $table->id();
            $table->string('movement_code', 48)->unique();
            $table->foreignId('item_id')->constrained('jaspest_inventory_items')->restrictOnDelete();
            $table->string('movement_type', 24);
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0);
            $table->decimal('warehouse_before', 14, 3)->default(0);
            $table->decimal('warehouse_after', 14, 3)->default(0);
            $table->foreignId('technician_id')->nullable()->constrained('jaspest_technicians')->nullOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained('jaspest_enquiries')->nullOnDelete();
            $table->string('supplier_name', 180)->nullable();
            $table->string('invoice_number', 100)->nullable();
            $table->string('batch_number', 80)->nullable();
            $table->date('expires_at')->nullable();
            $table->json('batch_allocations')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['item_id', 'created_at']);
            $table->index(['movement_type', 'created_at']);
            $table->index(['technician_id', 'created_at']);
            $table->index('enquiry_id');
        });

        Schema::create('jaspest_inventory_maintenance', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('item_id')->constrained('jaspest_inventory_items')->restrictOnDelete();
            $table->foreignId('technician_id')->nullable()->constrained('jaspest_technicians')->nullOnDelete();
            $table->string('maintenance_type', 32);
            $table->date('scheduled_date');
            $table->timestamp('completed_at')->nullable();
            $table->string('status', 20)->default('planned');
            $table->decimal('cost', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'scheduled_date']);
            $table->index('item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_inventory_maintenance');
        Schema::dropIfExists('jaspest_inventory_movements');
        Schema::dropIfExists('jaspest_inventory_technician_stocks');
        Schema::dropIfExists('jaspest_inventory_batches');
        Schema::dropIfExists('jaspest_inventory_items');
    }
};
