<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracks', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('title', 200)->nullable();
            $table->string('version', 120)->nullable();
            $table->char('isrc', 12)->nullable();
            $table->boolean('explicit')->default(false);
            $table->string('language', 8)->nullable();
            $table->unsignedSmallInteger('preview_start_sec')->default(0);
            $table->text('lyrics')->nullable();
            $table->foreignId('audio_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['release_id', 'position']);
        });

        Schema::create('track_artists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('artist_id')->nullable()->constrained('artists')->nullOnDelete();
            $table->string('name', 150);
            $table->string('role', 16);
            $table->string('spotify_artist_id', 32)->nullable();
            $table->string('apple_music_id', 32)->nullable();
            $table->unsignedSmallInteger('position')->default(0);

            $table->index(['track_id', 'role', 'position']);
        });

        Schema::create('track_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->string('name', 150);
            $table->unsignedSmallInteger('position')->default(0);

            $table->index(['track_id', 'role', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('track_credits');
        Schema::dropIfExists('track_artists');
        Schema::dropIfExists('tracks');
    }
};
