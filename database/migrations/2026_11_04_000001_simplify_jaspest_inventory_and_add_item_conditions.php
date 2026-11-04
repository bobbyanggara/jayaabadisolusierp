<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jaspest_inventory_items')) {
            return;
        }

        Schema::table('jaspest_inventory_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('jaspest_inventory_items', 'condition_status')) {
                $table->string('condition_status', 24)->default('normal')->after('stock_on_hand');
            }
            if (! Schema::hasColumn('jaspest_inventory_items', 'damaged_quantity')) {
                $table->decimal('damaged_quantity', 14, 3)->default(0)->after('condition_status');
            }
        });

        // Consolidate technician-held quantities into one physical stock figure per item.
        // Quantities remain in each item's own unit; values are never totalled across units.
        if (Schema::hasTable('jaspest_inventory_technician_stocks')) {
            $balances = DB::table('jaspest_inventory_technician_stocks')
                ->select('item_id')
                ->selectRaw('SUM(quantity) AS quantity_total')
                ->groupBy('item_id')->get();

            foreach ($balances as $balance) {
                $quantity = round((float) $balance->quantity_total, 3);
                if ($quantity <= 0) {
                    continue;
                }

                // A deterministic ledger code makes this section safe if a migration
                // is retried after a partial failure but before the old table is dropped.
                $movementCode = 'INV-MIG-TECH-'.$balance->item_id;
                if (Schema::hasTable('jaspest_inventory_movements')
                    && DB::table('jaspest_inventory_movements')->where('movement_code', $movementCode)->exists()) {
                    continue;
                }

                DB::transaction(function () use ($balance, $quantity, $movementCode): void {
                    $item = DB::table('jaspest_inventory_items')->where('id', $balance->item_id)->lockForUpdate()->first();
                    if (! $item) {
                        return;
                    }

                    $before = round((float) $item->stock_on_hand, 3);
                    $after = round($before + $quantity, 3);
                    DB::table('jaspest_inventory_items')->where('id', $item->id)->update([
                        'stock_on_hand' => $after,
                        'updated_at' => now(),
                    ]);

                    if (Schema::hasTable('jaspest_inventory_movements')) {
                        DB::table('jaspest_inventory_movements')->insert([
                            'movement_code' => $movementCode,
                            'item_id' => $item->id,
                            'movement_type' => 'adjustment_in',
                            'quantity' => $quantity,
                            'unit_cost' => 0,
                            'warehouse_before' => $before,
                            'warehouse_after' => $after,
                            'notes' => 'Konsolidasi stok teknisi ke satu catatan stok barang saat penyederhanaan inventaris.',
                            'created_by' => null,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }, 3);
            }
        }

        if (Schema::hasTable('jaspest_inventory_movements')) {
            // Keep historical movement rows but remove obsolete movement labels.
            DB::table('jaspest_inventory_movements')->where('movement_type', 'distribution')->update(['movement_type' => 'stock_out']);
            DB::table('jaspest_inventory_movements')->where('movement_type', 'return')->update(['movement_type' => 'stock_in']);

            if (Schema::hasColumn('jaspest_inventory_movements', 'technician_id')) {
                Schema::table('jaspest_inventory_movements', function (Blueprint $table): void {
                    $table->dropForeign('jaspest_inventory_movements_technician_id_foreign');
                    $table->dropIndex('jaspest_inventory_movements_technician_id_created_at_index');
                    $table->dropColumn('technician_id');
                });
            }
            $movementColumns = array_values(array_filter(
                ['batch_number', 'expires_at', 'batch_allocations'],
                fn (string $column): bool => Schema::hasColumn('jaspest_inventory_movements', $column)
            ));
            if ($movementColumns !== []) {
                Schema::table('jaspest_inventory_movements', fn (Blueprint $table) => $table->dropColumn($movementColumns));
            }
        }

        // Batch and technician stock ledgers are no longer part of the inventory workflow.
        Schema::dropIfExists('jaspest_inventory_technician_batches');
        Schema::dropIfExists('jaspest_inventory_technician_stocks');
        Schema::dropIfExists('jaspest_inventory_batches');

        $itemColumns = array_values(array_filter(
            ['sku', 'item_type', 'minimum_stock', 'track_expiry', 'is_active'],
            fn (string $column): bool => Schema::hasColumn('jaspest_inventory_items', $column)
        ));
        if ($itemColumns !== []) {
            Schema::table('jaspest_inventory_items', function (Blueprint $table) use ($itemColumns): void {
                if (in_array('sku', $itemColumns, true)) {
                    $table->dropUnique('jaspest_inventory_items_sku_unique');
                }
                if (in_array('item_type', $itemColumns, true) && in_array('is_active', $itemColumns, true)) {
                    $table->dropIndex('jaspest_inventory_items_item_type_is_active_index');
                }
                $table->dropColumn($itemColumns);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('jaspest_inventory_items')) {
            return;
        }

        // Restore the previous schema shape for code rollback. Removed batch details cannot be reconstructed.
        Schema::table('jaspest_inventory_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('jaspest_inventory_items', 'sku')) $table->string('sku', 40)->nullable()->unique();
            if (! Schema::hasColumn('jaspest_inventory_items', 'item_type')) $table->string('item_type', 20)->default('consumable');
            if (! Schema::hasColumn('jaspest_inventory_items', 'minimum_stock')) $table->decimal('minimum_stock', 14, 3)->default(0);
            if (! Schema::hasColumn('jaspest_inventory_items', 'track_expiry')) $table->boolean('track_expiry')->default(false);
            if (! Schema::hasColumn('jaspest_inventory_items', 'is_active')) $table->boolean('is_active')->default(true);
            $table->index(['item_type', 'is_active']);
        });

        if (Schema::hasTable('jaspest_inventory_movements')) {
            Schema::table('jaspest_inventory_movements', function (Blueprint $table): void {
                if (! Schema::hasColumn('jaspest_inventory_movements', 'technician_id')) {
                    $table->foreignId('technician_id')->nullable()->after('warehouse_after')->constrained('jaspest_technicians')->nullOnDelete();
                    $table->index(['technician_id', 'created_at']);
                }
                if (! Schema::hasColumn('jaspest_inventory_movements', 'batch_number')) $table->string('batch_number', 80)->nullable();
                if (! Schema::hasColumn('jaspest_inventory_movements', 'expires_at')) $table->date('expires_at')->nullable();
                if (! Schema::hasColumn('jaspest_inventory_movements', 'batch_allocations')) $table->json('batch_allocations')->nullable();
            });
        }

        if (! Schema::hasTable('jaspest_inventory_batches')) {
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
        }
        if (! Schema::hasTable('jaspest_inventory_technician_stocks')) {
            Schema::create('jaspest_inventory_technician_stocks', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('item_id')->constrained('jaspest_inventory_items')->cascadeOnDelete();
                $table->foreignId('technician_id')->constrained('jaspest_technicians')->cascadeOnDelete();
                $table->decimal('quantity', 14, 3)->default(0);
                $table->timestamps();
                $table->unique(['item_id', 'technician_id'], 'jinv_tech_stock_item_tech_unique');
                $table->index(['technician_id', 'quantity'], 'jinv_tech_stock_tech_qty_idx');
            });
        }
        if (! Schema::hasTable('jaspest_inventory_technician_batches')) {
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

        Schema::table('jaspest_inventory_items', function (Blueprint $table): void {
            if (Schema::hasColumn('jaspest_inventory_items', 'condition_status')) $table->dropColumn('condition_status');
            if (Schema::hasColumn('jaspest_inventory_items', 'damaged_quantity')) $table->dropColumn('damaged_quantity');
        });
    }
};
