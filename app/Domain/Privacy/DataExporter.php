<?php

namespace App\Domain\Privacy;

use App\Models\DataRequest;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

/**
 * Kullanıcının kişisel verilerinin JSON kopyası. Dosya özel diskte tutulur ve
 * kullanıcıya imzalı, süreli adresle verilir. Şifreli finans alanları (IBAN, vergi
 * numarası) maskelenir.
 */
class DataExporter
{
    public const DISK = 'private';

    public function export(User $user, DataRequest $request): string
    {
        $user->loadMissing(['consents', 'artists', 'releases.tracks', 'orders', 'subscriptions.plan', 'planHistory']);

        $data = [
            'generated_at' => now()->toIso8601String(),
            'account' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'account_type' => $user->account_type->value,
                'status' => $user->status->value,
                'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'last_login_ip' => $user->last_login_ip,
                'locale' => $user->locale,
                'display_currency' => $user->display_currency?->value,
            ],
            'consents' => $user->consents->map(fn ($consent): array => [
                'type' => $consent->type,
                'version' => $consent->document_version,
                'accepted_at' => $consent->accepted_at?->toIso8601String(),
                'ip_address' => $consent->ip_address,
                'choices' => $consent->choices,
            ])->values()->all(),
            'artists' => $user->artists->map(fn ($artist): array => [
                'name' => $artist->name,
                'spotify' => $artist->spotify_artist_id,
                'apple_music' => $artist->apple_music_id,
            ])->values()->all(),
            'releases' => $user->releases->map(fn ($release): array => [
                'id' => $release->ulid,
                'title' => $release->title,
                'status' => $release->status->value,
                'upc' => $release->upc,
                'release_date' => $release->release_date?->toDateString(),
                'tracks' => $release->tracks->map(fn ($track): array => [
                    'title' => $track->title,
                    'isrc' => $track->isrc,
                ])->values()->all(),
            ])->values()->all(),
            'subscriptions' => $user->subscriptions->map(fn ($subscription): array => [
                'plan' => $subscription->plan?->name,
                'status' => $subscription->status->value,
                'started_at' => $subscription->started_at?->toIso8601String(),
                'current_period_end' => $subscription->current_period_end?->toIso8601String(),
            ])->values()->all(),
            'orders' => $user->orders->map(fn ($order): array => [
                'date' => $order->ordered_at?->toIso8601String(),
                'total' => (string) $order->total,
                'currency' => $order->currency,
                'status' => $order->status->value,
                'invoice_number' => $order->invoice_number,
            ])->values()->all(),
            'plan_history' => $user->planHistory->map(fn ($history): array => [
                'plan' => $history->plan_name,
                'revenue_share_pct' => (string) $history->revenue_share_pct,
                'starts_at' => $history->starts_at?->toIso8601String(),
                'ends_at' => $history->ends_at?->toIso8601String(),
            ])->values()->all(),
            ...ExportSections::extra($user),
        ];

        $path = "exports/{$user->ulid}/{$request->ulid}.json";
        Storage::disk(self::DISK)->put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return $path;
    }
}
