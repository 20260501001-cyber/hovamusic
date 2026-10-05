<?php

namespace App\Domain\Privacy;

use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Models\Admin;
use App\Models\DataRequest;
use App\Models\User;
use App\Notifications\DataRequestCompleted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * KVKK veri talepleri: kopya (dışa aktarma), düzeltme ve hesap silme. Kullanıcı
 * talebi açar; admin tamamlar ya da gerekçesiyle reddeder.
 */
class PrivacyRequests
{
    public function __construct(
        private readonly DataExporter $exporter,
        private readonly AccountEraser $eraser,
    ) {}

    public function hasPending(User $user, DataRequestType $type): bool
    {
        return $user->dataRequests()->where('type', $type)->where('status', DataRequestStatus::Pending)->exists();
    }

    public function open(User $user, DataRequestType $type, ?string $message): DataRequest
    {
        if ($this->hasPending($user, $type)) {
            throw new RuntimeException(__('privacy.errors.pending_exists'));
        }

        $request = new DataRequest([
            'type' => $type,
            'message' => filled($message) ? trim((string) $message) : null,
            'user_email' => $user->email,
        ]);
        $request->user()->associate($user);
        $request->save();

        return $request;
    }

    /**
     * Talebi türüne göre tamamlar. Silme talebinde hesap anonimleştirilir; kullanıcıya
     * bildirim, hesap kapanmadan önceki e-posta adresine gider.
     */
    public function complete(DataRequest $request, Admin $admin, ?string $note = null): DataRequest
    {
        $this->ensurePending($request);
        $user = $request->user;
        $email = $request->user_email ?? $user?->email;

        DB::transaction(function () use ($request, $admin, $note, $user): void {
            $path = null;

            if ($request->type === DataRequestType::Export && $user !== null) {
                $path = $this->exporter->export($user, $request);
            }

            if ($request->type === DataRequestType::Deletion && $user !== null) {
                $this->eraser->erase($user);
            }

            $request->forceFill([
                'status' => DataRequestStatus::Completed,
                'admin_note' => filled($note) ? trim((string) $note) : null,
                'export_path' => $path,
                'handled_by' => $admin->id,
                'completed_at' => now(),
            ])->save();
        });

        $this->notify($request, $user, $email);

        return $request;
    }

    public function reject(DataRequest $request, Admin $admin, string $reason): DataRequest
    {
        $this->ensurePending($request);

        $request->forceFill([
            'status' => DataRequestStatus::Rejected,
            'admin_note' => trim($reason),
            'handled_by' => $admin->id,
            'completed_at' => now(),
        ])->save();

        $this->notify($request, $request->user, $request->user_email ?? $request->user?->email);

        return $request;
    }

    private function notify(DataRequest $request, ?User $user, ?string $email): void
    {
        $notification = new DataRequestCompleted($request);

        if ($user !== null && ! $user->trashed()) {
            $user->notify($notification);
        } elseif (filled($email)) {
            Notification::route('mail', $email)->notify($notification);
        }
    }

    private function ensurePending(DataRequest $request): void
    {
        if (! $request->isPending()) {
            throw new RuntimeException(__('privacy.errors.not_pending'));
        }

        $request->loadMissing('user');
    }
}
