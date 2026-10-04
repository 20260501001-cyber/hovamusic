<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('releases', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16)->nullable();
            $table->string('title', 200)->nullable();
            $table->string('version', 120)->nullable();
            $table->string('label_name', 150);
            $table->foreignId('genre_id')->nullable()->constrained('genres')->nullOnDelete();
            $table->foreignId('subgenre_id')->nullable()->constrained('genres')->nullOnDelete();
            $table->string('language', 8)->nullable();
            $table->date('release_date')->nullable();
            $table->date('original_release_date')->nullable();
            $table->string('p_line', 200)->nullable();
            $table->string('c_line', 200)->nullable();
            $table->string('upc', 13)->nullable();
            $table->boolean('explicit')->default(false);
            $table->string('territory_mode', 16)->default('worldwide');
            $table->json('territories')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedTinyInteger('wizard_step')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'status']);
        });

        Schema::create('release_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->string('name', 150);
            $table->string('role', 16);
            $table->string('spotify_artist_id', 32)->nullable();
            $table->string('apple_music_id', 32)->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->index(['release_id', 'role', 'position']);
        });

        Schema::create('release_platforms', function (Blueprint $table) {
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();

            $table->primary(['release_id', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('release_platforms');
        Schema::dropIfExists('release_artists');
        Schema::dropIfExists('releases');
    }
};
