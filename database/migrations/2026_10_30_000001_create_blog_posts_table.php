<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 160);
            $table->string('slug', 180)->unique();
            $table->string('category', 60);
            $table->string('excerpt', 300);
            $table->longText('body');
            $table->string('cover_path', 500)->nullable();
            $table->string('cover_alt', 180)->nullable();
            $table->string('author_name', 100)->default('Tim JASPEST');
            $table->string('meta_title', 100)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
