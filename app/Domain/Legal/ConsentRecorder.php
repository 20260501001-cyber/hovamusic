<?php

namespace App\Domain\Legal;

use App\Models\Consent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Onay kaydı: kim (kullanıcı ya da ziyaretçi), hangi metnin hangi sürümü, ne
 * zaman, hangi IP ve tarayıcıdan, hangi bağlamda (ör. plan).
 */
class ConsentRecorder
{
    public function __construct(private readonly LegalDocuments $documents) {}

    /**
     * @param  array<string, mixed>|null  $choices
     */
    public function record(?User $user, string $type, ?Model $context = null, ?array $choices = null, ?string $visitorId = null, ?string $version = null): Consent
    {
        $request = request();

        return Consent::query()->create([
            'user_id' => $user?->id,
            'visitor_id' => $visitorId,
            'type' => $type,
            'document_version' => $version ?? $this->documents->versionFor($type),
            'context_type' => $context?->getMorphClass(),
            'context_id' => $context?->getKey(),
            'choices' => $choices,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? Str::limit((string) $request->userAgent(), 500, '') : null,
            'accepted_at' => now(),
        ]);
    }
}
