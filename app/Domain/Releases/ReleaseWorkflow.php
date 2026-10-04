<?php

namespace App\Domain\Releases;

use App\Enums\ReleaseStatus;
use App\Models\Admin;
use App\Models\Release;
use App\Models\ReleaseStatusLog;
use App\Models\ReviewTemplate;
use App\Models\User;
use App\Notifications\ReleaseStatusChanged;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Yayının yaşam döngüsü. İzin verilmeyen geçiş reddedilir; her geçiş kim, ne zaman,
 * eski ve yeni durum ve notla release_status_logs tablosuna yazılır, kullanıcıya
 * e-posta ve panel bildirimi gider.
 *
 * Kullanıcı gönderir, yeniden gönderir ve onaylanmış yayın için kaldırma talebi
 * açar; diğer geçişleri admin yapar. Kaldırma talebi reddedilirse yayın talepten
 * önceki durumuna döner.
 */
class ReleaseWorkflow
{
    /**
     * @var array<string, array<string, list<ReleaseStatus>>>
     */
    private const TRANSITIONS = [
        'user' => [
            'draft' => [ReleaseStatus::InReview],
            'needs_changes' => [ReleaseStatus::InReview],
            'approved' => [ReleaseStatus::TakedownRequested],
            'delivered' => [ReleaseStatus::TakedownRequested],
            'live' => [ReleaseStatus::TakedownRequested],
        ],
        'admin' => [
            'in_review' => [ReleaseStatus::NeedsChanges, ReleaseStatus::Approved, ReleaseStatus::Rejected],
            'approved' => [ReleaseStatus::Delivered],
            'delivered' => [ReleaseStatus::Live],
            'live' => [ReleaseStatus::TakenDown],
            'takedown_requested' => [ReleaseStatus::TakenDown],
        ],
    ];

    /**
     * Not (sebep) yazılmadan yapılamayan geçişler.
     *
     * @var list<ReleaseStatus>
     */
    private const NOTE_REQUIRED = [
        ReleaseStatus::NeedsChanges,
        ReleaseStatus::Rejected,
        ReleaseStatus::TakedownRequested,
    ];

    /**
     * @return list<ReleaseStatus>
     */
    public function allowedTargets(Release $release, Authenticatable $actor): array
    {
        $type = $this->actorType($actor);
        $targets = self::TRANSITIONS[$type][$release->status->value] ?? [];

        if ($type === 'admin' && $release->status === ReleaseStatus::TakedownRequested) {
            $previous = $this->statusBeforeTakedown($release);

            if ($previous !== null) {
                $targets[] = $previous;
            }
        }

        return $targets;
    }

    public function canTransition(Release $release, ReleaseStatus $to, Authenticatable $actor): bool
    {
        return in_array($to, $this->allowedTargets($release, $actor), true);
    }

    public function requiresNote(Release $release, ReleaseStatus $to): bool
    {
        return in_array($to, self::NOTE_REQUIRED, true)
            || ($release->status === ReleaseStatus::TakedownRequested && $to !== ReleaseStatus::TakenDown);
    }

    /**
     * @throws InvalidTransition
     * @throws NoteRequired
     */
    public function transition(
        Release $release,
        ReleaseStatus $to,
        Authenticatable $actor,
        ?string $note = null,
        ?ReviewTemplate $template = null,
    ): Release {
        if (! $this->canTransition($release, $to, $actor)) {
            throw InvalidTransition::between($release->status, $to, $this->actorType($actor));
        }

        $note = filled($note) ? trim((string) $note) : null;

        if ($note === null && $this->requiresNote($release, $to)) {
            throw new NoteRequired(__('release.workflow.note_required'));
        }

        return DB::transaction(function () use ($release, $to, $actor, $note, $template): Release {
            $from = $release->status;

            $release->forceFill([
                'status' => $to,
                'submitted_at' => $to === ReleaseStatus::InReview ? now() : $release->submitted_at,
                'locked_at' => $to === ReleaseStatus::Approved ? now() : $release->locked_at,
            ])->save();

            /** @var ReleaseStatusLog $log */
            $log = $release->statusLogs()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $this->actorType($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'note' => $note,
                'template_id' => $template?->id,
            ]);

            DB::afterCommit(function () use ($release, $log): void {
                $release->loadMissing('user');
                $release->user?->notify(new ReleaseStatusChanged($release, $log));
            });

            return $release;
        });
    }

    /**
     * Kaldırma talebinden önceki durum; talep reddedilirse yayın bu duruma döner.
     */
    public function statusBeforeTakedown(Release $release): ?ReleaseStatus
    {
        $from = $release->statusLogs()
            ->where('to_status', ReleaseStatus::TakedownRequested->value)
            ->value('from_status');

        return $from instanceof ReleaseStatus ? $from : ReleaseStatus::tryFrom((string) $from);
    }

    private function actorType(Authenticatable $actor): string
    {
        return match (true) {
            $actor instanceof Admin => 'admin',
            $actor instanceof User => 'user',
            default => 'system',
        };
    }
}
