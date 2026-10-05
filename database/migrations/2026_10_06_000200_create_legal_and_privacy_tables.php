<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title', 200);
            $table->string('consent_type', 64)->nullable()->unique();
            $table->boolean('is_public')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->longText('body');
            $table->string('change_note', 300)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_document_id', 'version']);
        });

        Schema::create('data_requests', function (Blueprint $table) {
            $table->id();
            $table->ulid('ulid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_email')->nullable();
            $table->string('type', 16);
            $table->string('status', 16)->default('pending')->index();
            $table->text('message')->nullable();
            $table->text('admin_note')->nullable();
            $table->string('export_path')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        // Yasal metinlerin kendisi (başlık ve adres) her ortamda bulunur; içerikleri
        // admin panelinden sürüm olarak girilir.
        $now = now();
        $documents = [
            ['kvkk-aydinlatma-metni', 'KVKK Aydınlatma Metni', 'kvkk-aydinlatma'],
            ['acik-riza-metni', 'Açık Rıza Metni', 'acik-riza'],
            ['uyelik-sozlesmesi', 'Üyelik Sözleşmesi', 'uyelik-sozlesmesi'],
            ['gizlilik-politikasi', 'Gizlilik Politikası', null],
            ['cerez-politikasi', 'Çerez Politikası', 'cerez'],
            ['mesafeli-satis-sozlesmesi', 'Mesafeli Satış Sözleşmesi', 'mesafeli-satis'],
            ['on-bilgilendirme-formu', 'Ön Bilgilendirme Formu', 'on-bilgilendirme'],
        ];

        foreach ($documents as $sort => [$slug, $title, $consentType]) {
            DB::table('legal_documents')->insert([
                'slug' => $slug,
                'title' => $title,
                'consent_type' => $consentType,
                'is_public' => true,
                'sort' => $sort,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('data_requests');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};
