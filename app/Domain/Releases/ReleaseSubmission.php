<?php

namespace App\Domain\Releases;

use App\Domain\Isrc\IsrcAllocator;
use App\Domain\Isrc\IsrcExhausted;
use App\Domain\Plans\PlanGate;
use App\Enums\ReleaseStatus;
use App\Models\Release;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Gönderim: plan kapısı, yayının tam kontrolü ve üç hak beyanı onaylanınca
 * beyanlar onay kaydı olarak saklanır ve durum "İncelemede" olur.
 */
class ReleaseSubmission
{
    public function __construct(
        private readonly ReleaseValidator $validator,
        private readonly ReleaseWorkflow $workflow,
        private readonly PlanGate $plans,
        private readonly IsrcAllocator $isrc,
    ) {}

    /**
     * @param  array<string, mixed>  $declarations  hova.consents.release anahtarlarıyla
     *
     * @throws SubmissionFailed
     */
    public function submit(Release $release, User $user, array $declarations): Release
    {
        $gate = $this->plans->canSubmitRelease($user);

        if (! $gate->allowed) {
            throw new SubmissionFailed(['plan' => [(string) $gate->reason]]);
        }

        if (! $release->status->isSubmittable()) {
            throw new SubmissionFailed(['status' => [__('release.validation.not_submittable')]]);
        }

        $errors = $this->validator->validate($release);

        $missing = collect(config('hova.consents.release'))
            ->keys()
            ->reject(fn (string $type): bool => filter_var($declarations[$type] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->values();

        if ($missing->isNotEmpty()) {
            $errors['declarations'] = [__('release.validation.declarations_required')];
        }

        if ($errors !== []) {
            throw new SubmissionFailed($errors);
        }

        try {
            return $this->persist($release, $user);
        } catch (IsrcExhausted $exhausted) {
            throw new SubmissionFailed(['isrc' => [$exhausted->getMessage()]]);
        }
    }

    /**
     * Onay kayıtları, ISRC ataması ("ISRC kodum yok" diyen parçalar) ve durum geçişi
     * tek işlemde yapılır.
     */
    private function persist(Release $release, User $user): Release
    {
        return DB::transaction(function () use ($release, $user): Release {
            $request = request();

            foreach (config('hova.consents.release') as $type => $version) {
                $user->consents()->create([
                    'type' => $type,
                    'document_version' => $version,
                    'context_type' => $release->getMorphClass(),
                    'context_id' => $release->getKey(),
                    'ip_address' => $request->ip(),
                    'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
                    'accepted_at' => now(),
                ]);
            }

            $this->isrc->assignMissing($release, $user);

            return $this->workflow->transition($release, ReleaseStatus::InReview, $user);
        });
    }
}
