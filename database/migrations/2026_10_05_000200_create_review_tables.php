<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_templates', function (Blueprint $table) {
            $table->id();
            $table->string('type', 24)->index();
            $table->string('title', 150);
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::table('release_status_logs', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('note')->constrained('review_templates')->nullOnDelete();
        });

        Schema::create('release_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->text('message');
            $table->string('status', 16)->default('open')->index();
            $table->string('previous_status', 24)->nullable();
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->index(['release_id', 'type', 'status']);
        });

        Schema::create('release_store_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('source', 16)->default('admin');
            $table->foreignId('confirmed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['release_id', 'platform_id']);
        });

        Schema::create('spotify_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('release_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('matched_by', 8);
            $table->string('spotify_album_id', 32);
            $table->string('album_name', 300);
            $table->string('album_url', 300);
            $table->string('spotify_track_id', 32)->nullable();
            $table->string('status', 16)->default('pending')->index();
            $table->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->timestamps();
            $table->unique(
    ['release_id', 'spotify_album_id', 'matched_by', 'track_id'],
    'spotify_match_unique'
);
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->string('spotify_album_id', 32)->nullable()->after('upc');
            $table->timestamp('spotify_checked_at')->nullable()->after('locked_at');
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->boolean('has_own_isrc')->default(false)->after('isrc');
            $table->string('isrc_source', 8)->nullable()->after('has_own_isrc');
            $table->string('spotify_track_id', 32)->nullable()->after('duration_ms');
        });

        // Bu alanlardan önce girilen ISRC'ler kullanıcının kendi kodudur.
        DB::table('tracks')->whereNotNull('isrc')->update(['has_own_isrc' => true, 'isrc_source' => 'user']);
    }

    public function down(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->dropColumn(['has_own_isrc', 'isrc_source', 'spotify_track_id']);
        });
        Schema::table('releases', function (Blueprint $table) {
            $table->dropColumn(['spotify_album_id', 'spotify_checked_at']);
        });
        Schema::dropIfExists('spotify_matches');
        Schema::dropIfExists('release_store_links');
        Schema::dropIfExists('release_requests');
        Schema::table('release_status_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
        });
        Schema::dropIfExists('review_templates');
    }
};
