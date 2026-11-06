<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_whatsapp_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('jaspest_enquiries')->cascadeOnDelete();
            $table->string('notification_type', 60)->default('deal_order_confirmation');
            $table->string('recipient', 20);
            $table->longText('message');
            $table->string('status', 30)->default('queued')->index();
            $table->string('provider_message_id', 100)->nullable()->unique();
            $table->string('provider_request_id', 100)->nullable()->index();
            $table->string('provider_status', 50)->nullable();
            $table->string('provider_state', 100)->nullable();
            $table->string('provider_state_id', 100)->nullable()->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->json('provider_response')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->unique(['enquiry_id', 'notification_type'], 'jas_wa_notice_enquiry_type_uq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_whatsapp_notifications');
    }
};
