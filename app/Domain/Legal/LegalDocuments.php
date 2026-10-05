<?php

namespace App\Domain\Legal;

use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Illuminate\Support\Facades\Cache;

/**
 * Yasal metinlerin yayımlanmış güncel sürümleri. Onay kaydına onaylanan sürüm
 * numarası yazılır; metin henüz girilmediyse sürüm "0" olur.
 */
class LegalDocuments
{
    private const CACHE_KEY = 'legal.current_versions';

    public function document(string $slug): ?LegalDocument
    {
        return LegalDocument::query()->where('slug', $slug)->with('currentVersion')->first();
    }

    public function byConsentType(string $consentType): ?LegalDocument
    {
        return LegalDocument::query()->where('consent_type', $consentType)->with('currentVersion')->first();
    }

    public function current(string $slug): ?LegalDocumentVersion
    {
        return $this->document($slug)?->currentVersion;
    }

    /**
     * Onay türü için geçerli sürüm numarası ("kvkk-aydinlatma" => "3").
     */
    public function versionFor(string $consentType): string
    {
        $versions = Cache::remember(self::CACHE_KEY, 300, fn (): array => LegalDocument::query()
            ->whereNotNull('consent_type')
            ->with('currentVersion')
            ->get()
            ->mapWithKeys(fn (LegalDocument $document): array => [
                $document->consent_type => (string) ($document->currentVersion?->version ?? 0),
            ])
            ->all());

        return $versions[$consentType] ?? '0';
    }

    public function urlFor(string $consentType): ?string
    {
        $slug = LegalDocument::query()->where('consent_type', $consentType)->value('slug');

        return $slug ? route('legal.show', $slug) : null;
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
