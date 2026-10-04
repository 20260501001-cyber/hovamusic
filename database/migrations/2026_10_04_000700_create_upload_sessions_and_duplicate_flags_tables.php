<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upload_sessions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('extension', 8);
            $table->unsignedBigInteger('total_size');
            $table->unsignedBigInteger('received_bytes')->default(0);
            $table->string('fingerprint', 191);
            $table->string('temp_path', 500);
            $table->string('status', 16)->default('uploading');
            $table->foreignId('media_file_id')->nullable()->constrained('media_files')->nullOnDelete();
            $table->timestamp('expires_at')->index();
            $table->timestamps();

            $table->index(['track_id', 'fingerprint', 'status']);
        });

        Schema::create('duplicate_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->foreignId('matched_media_file_id')->constrained('media_files')->cascadeOnDelete();
            $table->string('status', 16)->default('open')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['media_file_id', 'matched_media_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('duplicate_flags');
        Schema::dropIfExists('upload_sessions');
    }
};
