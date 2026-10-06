<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->string('description', 300)->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('post_category_id')->nullable()->constrained()->nullOnDelete();
            $table->char('locale', 2)->default('tr');
            $table->string('title', 200);
            $table->string('slug', 220);
            $table->string('excerpt', 400)->nullable();
            $table->longText('body');
            $table->string('cover_path')->nullable();
            $table->string('cover_alt', 200)->nullable();
            $table->unsignedSmallInteger('cover_width')->nullable();
            $table->unsignedSmallInteger('cover_height')->nullable();
            $table->string('author_name', 120)->nullable();
            $table->string('status', 16)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title', 200)->nullable();
            $table->string('seo_description', 300)->nullable();
            $table->boolean('noindex')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['locale', 'slug']);
            $table->index(['status', 'published_at']);
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->char('locale', 2)->default('tr');
            $table->string('group', 80)->nullable();
            $table->string('question', 300);
            $table->text('answer');
            $table->boolean('is_published')->default(true);
            $table->boolean('show_on_home')->default(false);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['locale', 'is_published', 'sort']);
        });

        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->string('title', 200)->nullable();
            $table->string('description', 300)->nullable();
            $table->string('canonical', 500)->nullable();
            $table->string('og_title', 200)->nullable();
            $table->string('og_description', 300)->nullable();
            $table->string('og_image_path')->nullable();
            $table->string('twitter_card', 32)->default('summary_large_image');
            $table->boolean('noindex')->default(false);
            $table->timestamps();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 255)->unique();
            $table->string('to_path', 500);
            $table->unsignedSmallInteger('code')->default(301);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 191);
            $table->string('topic', 32);
            $table->text('message');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->index('handled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('seo_meta');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('posts');
        Schema::dropIfExists('post_categories');
    }
};
