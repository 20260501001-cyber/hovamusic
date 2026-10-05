<?php

namespace App\Filament\Resources\LedgerEntries\Pages;

use App\Domain\Finance\Ledger;
use App\Domain\Finance\ManualAdjustments;
use App\Enums\LedgerBucket;
use App\Filament\Resources\LedgerEntries\LedgerEntryResource;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Models\User;
use App\Support\Audit\AuditLogger;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class ListLedgerEntries extends ListRecords
{
    protected static string $resource = LedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('adjust')
                ->label('Manuel düzeltme')
                ->icon('lucide-scale')
                ->visible(fn (): bool => ReleaseActions::admin()->canManageFinance())
                ->modalDescription('Deftere yeni bir kayıt eklenir; mevcut kayıtlar değişmez. Eksi tutar bakiyeden düşer.')
                ->schema([
                    Select::make('user_id')->label('Kullanıcı')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search): array => User::query()
                            ->where(fn (Builder $query) => $query->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"))
                            ->limit(20)->pluck('email', 'id')->all())
                        ->getOptionLabelUsing(fn ($value): ?string => User::query()->find($value)?->email)
                        ->live()
                        ->helperText(function (Get $get): ?string {
                            $user = $get('user_id') ? User::query()->find($get('user_id')) : null;

                            if ($user === null) {
                                return null;
                            }

                            $b = app(Ledger::class)->balances($user);

                            return sprintf('Çekilebilir %s · bloke %s · rezerve %s', Format::money($b->available), Format::money($b->blocked), Format::money($b->reserved));
                        })
                        ->required(),
                    Select::make('bucket')->label('Kova')->required()->default(LedgerBucket::Available->value)
                        ->options([LedgerBucket::Available->value => LedgerBucket::Available->label(), LedgerBucket::Blocked->value => LedgerBucket::Blocked->label()]),
                    TextInput::make('amount')->label('Tutar (USD)')->helperText('Artı ya da eksi, ör. 12.50 veya -3.20')
                        ->regex('/^-?\d{1,12}(\.\d{1,6})?$/')->required(),
                    Textarea::make('reason')->label('Sebep (zorunlu)')->rows(3)->maxLength(2000)->required(),
                ])
                ->action(function (array $data, Action $action): void {
                    try {
                        $adjustment = app(ManualAdjustments::class)->apply(
                            User::query()->findOrFail($data['user_id']),
                            LedgerBucket::from($data['bucket']),
                            $data['amount'],
                            $data['reason'],
                            ReleaseActions::admin(),
                        );
                    } catch (InvalidArgumentException $e) {
                        Notification::make()->danger()->title('Kaydedilemedi')->body($e->getMessage())->send();
                        $action->halt();

                        return;
                    }

                    app(AuditLogger::class)->record('ledger.adjusted', $adjustment, [
                        'amount_usd' => (string) $adjustment->amount_usd,
                        'bucket' => $adjustment->bucket->value,
                        'reason' => $adjustment->reason,
                    ]);
                    Notification::make()->success()->title('Düzeltme kaydedildi.')->send();
                }),
        ];
    }
}
