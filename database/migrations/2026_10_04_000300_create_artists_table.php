<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artists', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('spotify_artist_id', 32)->nullable();
            $table->string('spotify_url')->nullable();
            $table->string('spotify_image_url', 500)->nullable();
            $table->string('apple_music_id', 32)->nullable();
            $table->boolean('create_new_spotify')->default(false);
            $table->boolean('create_new_apple')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artists');
    }
};
