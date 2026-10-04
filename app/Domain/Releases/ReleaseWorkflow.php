<?php

namespace App\Domain\Releases;

use App\Enums\ReleaseStatus;
use App\Models\Admin;
use App\Models\Release;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Yayının yaşam döngüsü. İzin verilmeyen geçiş reddedilir; her geçiş kim, ne zaman,
 * eski ve yeni durum ve notla release_status_logs tablosuna yazılır.
 *
 * Kullanıcı yalnızca gönderir, yeniden gönderir ve kaldırma talebi açar; diğer
 * geçişleri admin yapar (inceleme ekranları Faz 3'te).
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
            'live' => [ReleaseStatus::TakedownRequested],
        ],
        'admin' => [
            'in_review' => [ReleaseStatus::NeedsChanges, ReleaseStatus::Approved, ReleaseStatus::Rejected],
            'approved' => [ReleaseStatus::Delivered],
            'delivered' => [ReleaseStatus::Live],
            'takedown_requested' => [ReleaseStatus::TakenDown, ReleaseStatus::Live],
            'live' => [ReleaseStatus::TakenDown],
        ],
    ];

    public function canTransition(Release $release, ReleaseStatus $to, Authenticatable $actor): bool
    {
        $allowed = self::TRANSITIONS[$this->actorType($actor)][$release->status->value] ?? [];

        return in_array($to, $allowed, true);
    }

    public function transition(Release $release, ReleaseStatus $to, Authenticatable $actor, ?string $note = null): Release
    {
        if (! $this->canTransition($release, $to, $actor)) {
            throw InvalidTransition::between($release->status, $to, $this->actorType($actor));
        }

        return DB::transaction(function () use ($release, $to, $actor, $note): Release {
            $from = $release->status;

            $release->forceFill([
                'status' => $to,
                'submitted_at' => $to === ReleaseStatus::InReview ? now() : $release->submitted_at,
                'locked_at' => $to === ReleaseStatus::Approved ? now() : $release->locked_at,
            ])->save();

            $release->statusLogs()->create([
                'from_status' => $from,
                'to_status' => $to,
                'actor_type' => $this->actorType($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'note' => $note,
            ]);

            return $release;
        });
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
