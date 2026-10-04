<?php

namespace App\Domain\Releases;

use App\Enums\ReleaseStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Admin;
use App\Models\Release;
use App\Models\ReleaseRequest;
use App\Models\User;
use App\Notifications\ReleaseRequestAnswered;
use Illuminate\Support\Facades\DB;

/**
 * Onaylanmış yayın için düzeltme ve kaldırma talepleri. Düzeltme talebi yayının
 * durumunu değiştirmez. Kaldırma talebi yayını "Kaldırma talebi" durumuna alır;
 * admin onaylarsa yayın kaldırılır, reddederse önceki durumuna döner.
 */
class ReleaseRequests
{
    /**
     * Talep açılabilen durumlar: Onaylandı, Mağazalara gönderildi ve Yayında.
     *
     * @var list<ReleaseStatus>
     */
    public const OPEN_STATUSES = [
        ReleaseStatus::Approved,
        ReleaseStatus::Delivered,
        ReleaseStatus::Live,
    ];

    public function __construct(private readonly ReleaseWorkflow $workflow) {}

    public function canOpen(Release $release, RequestType $type): bool
    {
        if ($type === RequestType::Takedown) {
            return in_array($release->status, self::OPEN_STATUSES, true)
                && ! $this->hasOpen($release, RequestType::Takedown);
        }

        return in_array($release->status, [...self::OPEN_STATUSES, ReleaseStatus::TakedownRequested], true)
            && ! $this->hasOpen($release, RequestType::Correction);
    }

    public function hasOpen(Release $release, RequestType $type): bool
    {
        return $release->requests()->where('type', $type)->where('status', RequestStatus::Open)->exists();
    }

    /**
     * @throws RequestNotAllowed
     */
    public function open(Release $release, User $user, RequestType $type, string $message): ReleaseRequest
    {
        if ($release->user_id !== $user->id || ! $this->canOpen($release, $type)) {
            throw new RequestNotAllowed(__('release.requests.not_allowed'));
        }

        return DB::transaction(function () use ($release, $user, $type, $message): ReleaseRequest {
            $request = new ReleaseRequest([
                'type' => $type,
                'message' => trim($message),
                'previous_status' => $type === RequestType::Takedown ? $release->status : null,
            ]);
            $request->release()->associate($release);
            $request->user()->associate($user);
            $request->save();

            if ($type === RequestType::Takedown) {
                $this->workflow->transition($release, ReleaseStatus::TakedownRequested, $user, $request->message);
            }

            return $request;
        });
    }

    /**
     * Düzeltme talebini yanıtlar; kullanıcıya e-posta ve panel bildirimi gider.
     */
    public function answer(ReleaseRequest $request, Admin $admin, string $note): ReleaseRequest
    {
        $this->ensureOpen($request, RequestType::Correction);

        $this->close($request, $admin, RequestStatus::Resolved, $note);
        DB::afterCommit(fn () => $request->user->notify(new ReleaseRequestAnswered($request)));

        return $request;
    }

    /**
     * Kaldırma talebini onaylar: yayın "Kaldırıldı" olur.
     */
    public function approveTakedown(ReleaseRequest $request, Admin $admin, ?string $note = null): ReleaseRequest
    {
        $this->ensureOpen($request, RequestType::Takedown);

        return DB::transaction(function () use ($request, $admin, $note): ReleaseRequest {
            $this->workflow->transition($request->release, ReleaseStatus::TakenDown, $admin, $note);

            return $this->close($request, $admin, RequestStatus::Resolved, $note);
        });
    }

    /**
     * Kaldırma talebini reddeder (sebep zorunlu): yayın talepten önceki durumuna döner.
     */
    public function rejectTakedown(ReleaseRequest $request, Admin $admin, string $note): ReleaseRequest
    {
        $this->ensureOpen($request, RequestType::Takedown);

        return DB::transaction(function () use ($request, $admin, $note): ReleaseRequest {
            $previous = $request->previous_status ?? $this->workflow->statusBeforeTakedown($request->release);

            if ($previous === null) {
                throw new RequestNotAllowed(__('release.requests.not_allowed'));
            }

            $this->workflow->transition($request->release, $previous, $admin, $note);

            return $this->close($request, $admin, RequestStatus::Rejected, $note);
        });
    }

    private function close(ReleaseRequest $request, Admin $admin, RequestStatus $status, ?string $note): ReleaseRequest
    {
        $request->forceFill([
            'status' => $status,
            'admin_note' => filled($note) ? trim((string) $note) : null,
            'handled_by' => $admin->getKey(),
            'handled_at' => now(),
        ])->save();

        return $request;
    }

    private function ensureOpen(ReleaseRequest $request, RequestType $type): void
    {
        if ($request->type !== $type || ! $request->isOpen()) {
            throw new RequestNotAllowed(__('release.requests.not_allowed'));
        }

        $request->loadMissing(['release', 'user']);
    }
}
