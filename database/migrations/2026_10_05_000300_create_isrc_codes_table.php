<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hova Music'in kendi ISRC önekiyle (ör. GXLM5) atadığı kodların kaydı. Bir kez
 * kaydedilen kod silinmez; parça silinse bile kod yeniden kullanılmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('isrc_codes', function (Blueprint $table) {
            $table->id();
            $table->char('isrc', 12)->unique();
            $table->unsignedTinyInteger('year');
            $table->unsignedInteger('sequence');
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->string('assigned_by_type', 8);
            $table->unsignedBigInteger('assigned_by_id')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->unique(['year', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('isrc_codes');
    }
};
