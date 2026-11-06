<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jaspest_enquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('full_name', 150);
            $table->string('whatsapp', 20);
            $table->text('address');
            $table->decimal('estimated_area', 12, 2);
            $table->string('need', 100);
            $table->string('property_type', 20);
            $table->string('company_name', 200)->nullable();
            $table->text('description');
            $table->boolean('consent')->default(false);
            $table->string('status', 20)->default('new');
            $table->timestamp('wa_opened_at')->nullable();
            $table->timestamp('contacted_at')->nullable();
            $table->timestamp('followed_up_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['property_type', 'created_at']);
            $table->index('whatsapp');
        });

        Schema::create('jaspest_enquiry_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('jaspest_enquiries')->cascadeOnDelete();
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->timestamps();
        });

        Schema::create('jaspest_enquiry_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('enquiry_id')->constrained('jaspest_enquiries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['enquiry_id', 'created_at']);
        });

        Schema::create('jaspest_content_items', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 30);
            $table->string('title', 180);
            $table->string('slug', 200)->nullable();
            $table->string('category', 120)->nullable();
            $table->string('description', 500)->nullable();
            $table->longText('body')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->string('image_alt', 180)->nullable();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('data')->nullable();
            $table->timestamps();
            $table->unique(['type', 'slug']);
            $table->index(['type', 'status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jaspest_content_items');
        Schema::dropIfExists('jaspest_enquiry_events');
        Schema::dropIfExists('jaspest_enquiry_photos');
        Schema::dropIfExists('jaspest_enquiries');
    }
};
