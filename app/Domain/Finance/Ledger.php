<?php

namespace App\Domain\Finance;

use App\Enums\LedgerBucket;
use App\Enums\LedgerEntryType;
use App\Models\Admin;
use App\Models\LedgerEntry;
use App\Models\User;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Bakiye defteri. Bakiye hiçbir yerde ayrı tutulmaz; her zaman kayıtların
 * toplamıdır. Kayıtlar değiştirilmez ve silinmez; düzeltme ters kayıtla yapılır.
 * Kovalar: kullanılabilir (çekilebilir), bloke (plansız dönem kazancı), rezerve
 * (bekleyen para çekme).
 */
class Ledger
{
    public function balances(User $user): Balances
    {
        $sums = LedgerEntry::query()
            ->where('user_id', $user->id)
            ->groupBy('bucket')
            ->selectRaw('bucket, SUM(amount_usd) as total')
            ->pluck('total', 'bucket');

        return new Balances(
            Money::ledger((string) ($sums[LedgerBucket::Available->value] ?? '0')),
            Money::ledger((string) ($sums[LedgerBucket::Blocked->value] ?? '0')),
            Money::ledger((string) ($sums[LedgerBucket::Reserved->value] ?? '0')),
        );
    }

    public function balance(User $user, LedgerBucket $bucket): BigDecimal
    {
        return Money::ledger((string) LedgerEntry::query()->where('user_id', $user->id)->where('bucket', $bucket)->sum('amount_usd'));
    }

    /**
     * Aynı kullanıcı için eşzamanlı bakiye işlemlerini sıraya koyar (işlem içinde çağrılmalı).
     */
    public function lock(User $user): void
    {
        User::withTrashed()->whereKey($user->id)->lockForUpdate()->value('id');
    }

    public function post(
        User $user,
        LedgerBucket $bucket,
        LedgerEntryType $type,
        BigDecimal $amount,
        ?Model $source = null,
        ?string $description = null,
        ?LedgerEntry $reversalOf = null,
        ?string $groupId = null,
        ?Authenticatable $actor = null,
    ): LedgerEntry {
        $actor ??= auth('admin')->user();

        return LedgerEntry::query()->create([
            'user_id' => $user->id,
            'bucket' => $bucket,
            'type' => $type,
            'amount_usd' => (string) Money::ledger($amount),
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reversal_of_id' => $reversalOf?->id,
            'group_id' => $groupId,
            'description' => $description !== null ? mb_substr($description, 0, 500) : null,
            'created_by_type' => $actor instanceof Admin ? 'admin' : ($actor instanceof User ? 'user' : 'system'),
            'created_by_id' => $actor?->getAuthIdentifier(),
            'created_at' => now(),
        ]);
    }

    /**
     * Kovalar arası aktarım: aynı grup kimliğiyle iki kayıt (kaynaktan eksi, hedefe artı).
     *
     * @return array{0: LedgerEntry, 1: LedgerEntry}
     */
    public function transfer(
        User $user,
        LedgerBucket $from,
        LedgerBucket $to,
        LedgerEntryType $type,
        BigDecimal $amount,
        ?Model $source = null,
        ?string $description = null,
        ?Authenticatable $actor = null,
    ): array {
        return DB::transaction(function () use ($user, $from, $to, $type, $amount, $source, $description, $actor): array {
            $group = (string) Str::uuid();

            return [
                $this->post($user, $from, $type, $amount->negated(), $source, $description, groupId: $group, actor: $actor),
                $this->post($user, $to, $type, $amount, $source, $description, groupId: $group, actor: $actor),
            ];
        });
    }

    /**
     * Kaydın tersini yazar (aynı kova, eksi tutar).
     */
    public function reverse(LedgerEntry $entry, LedgerEntryType $type, ?string $description = null, ?Authenticatable $actor = null): LedgerEntry
    {
        return $this->post(
            $entry->user,
            $entry->bucket,
            $type,
            Money::of((string) $entry->amount_usd)->negated(),
            $entry->source,
            $description,
            $entry,
            actor: $actor,
        );
    }
}
