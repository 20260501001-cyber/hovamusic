<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('name', 120);
            $table->string('audience', 16)->index();
            $table->string('interval', 8);
            $table->decimal('price_usd', 10, 2);
            $table->unsignedInteger('release_limit')->nullable();
            $table->unsignedInteger('artist_limit')->nullable();
            $table->decimal('revenue_share_pct', 5, 2);
            $table->string('polar_product_id', 64)->nullable()->unique();
            $table->text('description')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('polar_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('polar_id', 64)->unique();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 16)->default('polar');
            $table->string('provider_id', 64)->nullable()->unique();
            $table->string('provider_customer_id', 64)->nullable();
            $table->string('status', 24)->index();
            $table->decimal('amount', 12, 2)->nullable();
            $table->char('currency', 3)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('canceled_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('renewal_reminded_at')->nullable();
            $table->timestamp('expiry_notified_at')->nullable();
            $table->timestamp('provider_updated_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 16)->default('polar');
            $table->string('provider_id', 64)->unique();
            $table->string('status', 24)->index();
            $table->string('billing_reason', 32)->nullable();
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount', 12, 2)->default(0);
            $table->decimal('tax', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('refunded', 12, 2)->default(0);
            $table->char('currency', 3);
            $table->string('invoice_number', 64)->nullable();
            $table->string('product_name', 200)->nullable();
            $table->timestamp('ordered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('plan_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->string('plan_name', 120);
            $table->decimal('revenue_share_pct', 5, 2);
            $table->unsignedInteger('release_limit')->nullable();
            $table->unsignedInteger('artist_limit')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('reason', 32);
            $table->timestamps();
            $table->index(['user_id', 'starts_at']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 16);
            $table->string('event_id', 128);
            $table->string('type', 64)->index();
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'event_id']);
        });

        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('provider_id', 64)->nullable()->unique();
            $table->string('status', 16)->default('open');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('releases', function (Blueprint $table) {
            $table->timestamp('first_submitted_at')->nullable()->after('submitted_at');
            $table->index(['user_id', 'first_submitted_at']);
        });

        DB::table('releases')->whereNotNull('submitted_at')->update(['first_submitted_at' => DB::raw('submitted_at')]);
    }

    public function down(): void
    {
        Schema::table('releases', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'first_submitted_at']);
            $table->dropColumn('first_submitted_at');
        });
        Schema::dropIfExists('checkouts');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('plan_history');
        Schema::dropIfExists('orders');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('polar_customers');
        Schema::dropIfExists('plans');
    }
};
