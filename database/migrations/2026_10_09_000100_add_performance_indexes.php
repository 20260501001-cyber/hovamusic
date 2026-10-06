<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sık sorgulanan alanlar: rapor eşleştirmesi (ISRC, UPC), inceleme kuyruğu,
 * sipariş geçmişi, abonelik hatırlatmaları.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracks', function (Blueprint $table) {
            $table->index('isrc');
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->index('upc');
            $table->index(['status', 'submitted_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->index(['user_id', 'ordered_at']);
            $table->index('ordered_at');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->index(['status', 'current_period_end']);
        });

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'status']);
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropIndex(['status', 'current_period_end']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'ordered_at']);
            $table->dropIndex(['ordered_at']);
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex(['upc']);
            $table->dropIndex(['status', 'submitted_at']);
        });

        Schema::table('tracks', function (Blueprint $table) {
            $table->dropIndex(['isrc']);
        });
    }
};
