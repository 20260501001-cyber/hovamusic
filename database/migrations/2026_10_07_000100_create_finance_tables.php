<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('entity_type', 16)->default('individual');
            $table->string('legal_name', 200)->nullable();
            $table->string('company_name', 200)->nullable();
            $table->char('country', 2)->nullable();
            $table->char('citizenship', 2)->nullable();
            $table->text('address_line')->nullable();
            $table->text('city')->nullable();
            $table->text('postal_code')->nullable();
            $table->text('phone')->nullable();
            $table->text('tax_id')->nullable();
            $table->string('tax_office', 120)->nullable();
            $table->text('date_of_birth')->nullable();
            $table->timestamps();
        });

        Schema::create('payout_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('account_holder', 200);
            $table->text('iban')->nullable();
            $table->text('account_number')->nullable();
            $table->text('routing_number')->nullable();
            $table->string('swift_bic', 11)->nullable();
            $table->string('bank_name', 200)->nullable();
            $table->char('bank_country', 2);
            $table->char('currency', 3);
            $table->string('last4', 4)->nullable();
            $table->timestamps();
        });

        Schema::create('tax_forms', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('form_type', 16);
            $table->longText('data');
            $table->string('signed_name', 200);
            $table->string('signer_capacity', 120)->nullable();
            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->string('pdf_path')->nullable();
            $table->string('pdf_sha256', 64)->nullable();
            $table->string('status', 16)->default('valid')->index();
            $table->date('expires_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->char('period', 7);
            $table->char('from_currency', 3);
            $table->char('to_currency', 3);
            $table->decimal('rate', 18, 8);
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['period', 'from_currency', 'to_currency']);
        });

        Schema::create('report_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('is_default')->default(false);
            $table->unsignedSmallInteger('header_row')->default(1);
            $table->string('delimiter', 8)->default('auto');
            $table->string('decimal_separator', 8)->default('auto');
            $table->char('default_currency', 3)->nullable();
            $table->json('columns');
            $table->timestamps();
        });

        Schema::create('report_imports', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->string('original_name', 255);
            $table->string('file_path');
            $table->char('file_sha256', 64)->index();
            $table->foreignId('report_mapping_id')->nullable()->constrained()->nullOnDelete();
            $table->char('currency', 3)->nullable();
            $table->json('periods')->nullable();
            $table->string('status', 16)->index();
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('unmatched_count')->default(0);
            $table->json('totals')->nullable();
            $table->json('warnings')->nullable();
            $table->text('error')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('reversed_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->string('reverse_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('report_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_import_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->date('sales_month')->nullable();
            $table->string('platform', 120)->nullable();
            $table->string('country', 64)->nullable();
            $table->string('isrc', 12)->nullable()->index();
            $table->string('upc', 14)->nullable()->index();
            $table->string('artist_name', 255)->nullable();
            $table->string('release_title', 255)->nullable();
            $table->string('track_title', 255)->nullable();
            $table->string('sale_type', 64)->nullable();
            $table->bigInteger('quantity')->default(0);
            $table->decimal('net_amount', 20, 10)->default(0);
            $table->char('currency', 3)->nullable();
            $table->json('raw');
            $table->string('match_status', 16)->default('unmatched');
            $table->string('match_source', 16)->nullable();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('share_pct', 5, 2)->nullable();
            $table->decimal('fx_rate', 18, 8)->nullable();
            $table->decimal('amount_usd', 20, 10)->nullable();
            $table->decimal('user_amount_usd', 20, 10)->nullable();
            $table->index(['report_import_id', 'match_status']);
            $table->index(['report_import_id', 'user_id']);
        });

        Schema::create('match_rules', function (Blueprint $table) {
            $table->id();
            $table->string('key_type', 16);
            $table->string('key', 255);
            $table->foreignId('track_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key_type', 'key']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('bucket', 16);
            $table->string('type', 32);
            $table->decimal('amount_usd', 20, 6);
            $table->nullableMorphs('source');
            $table->foreignId('reversal_of_id')->nullable()->constrained('ledger_entries')->restrictOnDelete();
            $table->uuid('group_id')->nullable()->index();
            $table->string('description', 500)->nullable();
            $table->string('created_by_type', 16)->default('system');
            $table->unsignedBigInteger('created_by_id')->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'bucket']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('manual_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_usd', 20, 6);
            $table->string('bucket', 16);
            $table->text('reason');
            $table->foreignId('ledger_entry_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('withdrawals', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->decimal('amount_usd', 14, 2);
            $table->longText('payout_snapshot');
            $table->char('payout_currency', 3);
            $table->decimal('estimated_fee_usd', 14, 2);
            $table->foreignId('tax_form_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->index();
            $table->string('reject_reason', 1000)->nullable();
            $table->decimal('fee_usd', 14, 2)->nullable();
            $table->decimal('net_usd', 14, 2)->nullable();
            $table->decimal('paid_amount', 14, 2)->nullable();
            $table->string('wise_reference', 120)->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stream_stats_monthly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('report_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('release_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('track_id')->nullable()->constrained()->nullOnDelete();
            $table->string('platform', 120)->nullable();
            $table->string('country', 64)->nullable();
            $table->date('month');
            $table->bigInteger('quantity')->default(0);
            $table->decimal('revenue_usd', 20, 6)->default(0);
            $table->index(['user_id', 'month']);
        });

        // Believe raporu için varsayılan sütun eşleştirmesi; başlıklar admin panelden değiştirilebilir.
        DB::table('report_mappings')->insert([
            'name' => 'Believe (varsayılan)',
            'is_default' => true,
            'header_row' => 1,
            'delimiter' => 'auto',
            'decimal_separator' => 'auto',
            'default_currency' => null,
            'columns' => json_encode([
                'sales_month' => 'Sales Month',
                'platform' => 'Platform',
                'country' => 'Country / Region',
                'artist_name' => 'Artist Name',
                'release_title' => 'Release title',
                'track_title' => 'Track title',
                'upc' => 'UPC',
                'isrc' => 'ISRC',
                'sale_type' => 'Sales Type',
                'quantity' => 'Quantity',
                'net_amount' => 'Net Revenue',
                'currency' => 'Client Payment Currency',
            ], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Bakiye defterine yalnızca ekleme yapılır; MySQL'de değiştirme ve silme
        // veritabanı tetikleyicisiyle de engellenir.
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER ledger_entries_no_update BEFORE UPDATE ON ledger_entries
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries kayıtları değiştirilemez';
                SQL);
            DB::unprepared(<<<'SQL'
                CREATE TRIGGER ledger_entries_no_delete BEFORE DELETE ON ledger_entries
                FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ledger_entries kayıtları silinemez';
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_update');
            DB::unprepared('DROP TRIGGER IF EXISTS ledger_entries_no_delete');
        }

        Schema::dropIfExists('stream_stats_monthly');
        Schema::dropIfExists('withdrawals');
        Schema::dropIfExists('manual_adjustments');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('match_rules');
        Schema::dropIfExists('report_lines');
        Schema::dropIfExists('report_imports');
        Schema::dropIfExists('report_mappings');
        Schema::dropIfExists('exchange_rates');
        Schema::dropIfExists('tax_forms');
        Schema::dropIfExists('payout_methods');
        Schema::dropIfExists('user_profiles');
    }
};
