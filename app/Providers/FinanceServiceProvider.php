<?php

namespace App\Providers;

use App\Domain\Finance\BlockedEarnings;
use App\Domain\Finance\Ledger;
use App\Domain\Privacy\AccountEraser;
use App\Domain\Privacy\ExportSections;
use App\Events\PlanBecameActive;
use App\Filament\Resources\DataRequests\DataRequestResource;
use App\Filament\Resources\Users\Sections\FinanceSection;
use App\Filament\Resources\Users\UserExtraSections;
use App\Models\DataRequest;
use App\Models\User;
use App\Support\Format;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Finans alanının diğer alanlarla bağlantıları: bloke kazancın plan aktif olunca
 * serbest kalması, KVKK veri kopyası ve hesap silme, admin kullanıcı detayı.
 */
class FinanceServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(PlanBecameActive::class, [BlockedEarnings::class, 'handle']);

        ExportSections::register('finance', function (User $user): array {
            $profile = $user->profile;
            $payout = $user->payoutMethod;
            $balances = app(Ledger::class)->balances($user);

            return [
                'billing_profile' => $profile === null ? null : [
                    'entity_type' => $profile->entity_type->value,
                    'legal_name' => $profile->legal_name,
                    'company_name' => $profile->company_name,
                    'country' => $profile->country,
                    'citizenship' => $profile->citizenship,
                    'address_line' => $profile->address_line,
                    'city' => $profile->city,
                    'postal_code' => $profile->postal_code,
                    'phone' => $profile->phone,
                    'tax_id' => self::mask($profile->tax_id),
                    'tax_office' => $profile->tax_office,
                    'date_of_birth' => $profile->date_of_birth,
                ],
                'payout_method' => $payout === null ? null : [
                    'account_holder' => $payout->account_holder,
                    'account' => $payout->maskedAccount(),
                    'swift_bic' => $payout->swift_bic,
                    'bank_name' => $payout->bank_name,
                    'bank_country' => $payout->bank_country,
                    'currency' => $payout->currency,
                ],
                'balances_usd' => [
                    'available' => (string) $balances->available,
                    'blocked' => (string) $balances->blocked,
                    'reserved' => (string) $balances->reserved,
                ],
                'ledger' => $user->ledgerEntries()->orderBy('id')->get()->map(fn ($entry): array => [
                    'date' => $entry->created_at?->toIso8601String(),
                    'bucket' => $entry->bucket->value,
                    'type' => $entry->type->value,
                    'amount_usd' => (string) $entry->amount_usd,
                    'description' => $entry->description,
                ])->all(),
                'withdrawals' => $user->withdrawals()->orderBy('id')->get()->map(fn ($withdrawal): array => [
                    'date' => $withdrawal->created_at?->toIso8601String(),
                    'amount_usd' => (string) $withdrawal->amount_usd,
                    'status' => $withdrawal->status->value,
                    'fee_usd' => $withdrawal->fee_usd !== null ? (string) $withdrawal->fee_usd : null,
                    'paid_at' => $withdrawal->paid_at?->toIso8601String(),
                ])->all(),
                'tax_forms' => $user->taxForms()->orderBy('id')->get()->map(fn ($form): array => [
                    'type' => $form->form_type->value,
                    'signed_name' => $form->signed_name,
                    'signed_at' => $form->signed_at?->toIso8601String(),
                    'status' => $form->status,
                    'expires_at' => $form->expires_at?->toDateString(),
                ])->all(),
            ];
        });

        // Vergi formları, para çekme kayıtları ve bakiye defteri yasal saklama
        // kapsamındadır; profil ve ödeme bilgisi silinir.
        AccountEraser::extend('finance', function (User $user): void {
            $user->payoutMethod()->delete();
            $user->profile()->delete();
        });

        DataRequestResource::warnWith('finance', function (DataRequest $request): array {
            $user = $request->user;
            $warnings = [];

            if ($user === null) {
                return [];
            }

            if ($user->activeSubscription() !== null) {
                $warnings[] = 'Kullanıcının aktif aboneliği var; Polar\'dan iptal edilmesi gerekir.';
            }

            $balances = app(Ledger::class)->balances($user);

            if (! $balances->total()->isZero()) {
                $warnings[] = sprintf('Bakiyesi var: çekilebilir %s, bloke %s, rezerve %s.',
                    Format::money($balances->available), Format::money($balances->blocked), Format::money($balances->reserved));
            }

            if ($user->withdrawals()->whereIn('status', ['pending', 'approved'])->exists()) {
                $warnings[] = 'Sonuçlanmamış para çekme talebi var.';
            }

            return $warnings;
        });

        UserExtraSections::add('finance', fn () => FinanceSection::make());
    }

    private static function mask(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return str_repeat('•', max(0, mb_strlen($value) - 4)).mb_substr($value, -4);
    }
}
