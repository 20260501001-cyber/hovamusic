<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_files', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('disk', 32);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime', 100)->nullable();
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64)->nullable()->index();
            $table->string('format', 16)->nullable();
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('color_space', 16)->nullable();
            $table->string('codec', 32)->nullable();
            $table->unsignedInteger('sample_rate')->nullable();
            $table->unsignedTinyInteger('bit_depth')->nullable();
            $table->unsignedTinyInteger('channels')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->string('validation_status', 16)->default('pending')->index();
            $table->json('validation_errors')->nullable();
            $table->timestamp('analyzed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_files');
    }
};
