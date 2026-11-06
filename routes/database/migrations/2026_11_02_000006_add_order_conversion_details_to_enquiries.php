<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            $table->decimal('order_price', 15, 2)->nullable()->after('converted_at');
            $table->decimal('order_area', 12, 2)->nullable()->after('order_price');
            $table->date('scheduled_date')->nullable()->after('order_area');
            $table->foreignId('assigned_technician_id')->nullable()->after('scheduled_date')
                ->constrained('jaspest_technicians')->nullOnDelete();
            $table->string('assigned_technician_name', 150)->nullable()->after('assigned_technician_id');
        });
    }

    public function down(): void
    {
        Schema::table('jaspest_enquiries', function (Blueprint $table): void {
            $table->dropForeign(['assigned_technician_id']);
            $table->dropColumn([
                'order_price',
                'order_area',
                'scheduled_date',
                'assigned_technician_id',
                'assigned_technician_name',
            ]);
        });
    }
};
