<?php

namespace App\Filament\Resources\Withdrawals\Pages;

use App\Domain\Finance\WithdrawalNotAllowed;
use App\Domain\Finance\Withdrawals;
use App\Enums\WithdrawalStatus;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Filament\Resources\Withdrawals\WithdrawalResource;
use App\Models\Withdrawal;
use App\Support\Audit\AuditLogger;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Withdrawal $record
 */
class ViewWithdrawal extends ViewRecord
{
    protected static string $resource = WithdrawalResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        // Banka bilgisinin görüntülenmesi kayda geçer.
        app(AuditLogger::class)->record('withdrawal.viewed', $this->record);
    }

    protected function resolveRecord(int|string $key): Model
    {
        return parent::resolveRecord($key)->load(['user', 'taxForm']);
    }

    public function hydrate(): void
    {
        $this->record->loadMissing(['user', 'taxForm']);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Onayla')
                ->icon('lucide-check')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === WithdrawalStatus::Pending)
                ->authorize('update')
                ->requiresConfirmation()
                ->modalDescription('Onaydan sonra ödemeyi Wise ile gönderip "Ödendi" olarak işaretle.')
                ->action(fn (Action $action) => $this->run($action, 'withdrawal.approved', fn (Withdrawals $w) => $w->approve($this->record, ReleaseActions::admin()), 'Talep onaylandı.')),
            Action::make('markPaid')
                ->label('Ödendi olarak işaretle')
                ->icon('lucide-banknote')
                ->color('success')
                ->visible(fn (): bool => $this->record->status === WithdrawalStatus::Approved)
                ->authorize('update')
                ->modalDescription(fn (): string => sprintf('Talep: %s. Defterde net ödeme ve ücret ayrı kayıt olarak düşer.', Format::money((string) $this->record->amount_usd)))
                ->schema([
                    TextInput::make('fee_usd')->label('Gerçek Wise ücreti (USD)')->default(fn (): string => (string) $this->record->estimated_fee_usd)
                        ->regex('/^\d{1,9}(\.\d{1,2})?$/')->required(),
                    TextInput::make('paid_amount')->label(fn (): string => 'Gönderilen tutar ('.$this->record->payout_currency.')')
                        ->regex('/^\d{1,12}(\.\d{1,2})?$/')->required(),
                    TextInput::make('wise_reference')->label('Wise transfer numarası')->maxLength(120),
                ])
                ->action(fn (array $data, Action $action) => $this->run($action, 'withdrawal.paid',
                    fn (Withdrawals $w) => $w->markPaid($this->record, ReleaseActions::admin(), $data['fee_usd'], $data['paid_amount'], $data['wise_reference'] ?? null),
                    'Ödeme kaydedildi.', ['fee_usd' => $data['fee_usd'], 'paid_amount' => $data['paid_amount']])),
            Action::make('reject')
                ->label('Reddet')
                ->icon('lucide-x')
                ->color('danger')
                ->visible(fn (): bool => in_array($this->record->status, [WithdrawalStatus::Pending, WithdrawalStatus::Approved], true))
                ->authorize('update')
                ->modalDescription('Tutar kullanıcının çekilebilir bakiyesine geri döner; sebep kullanıcıya gönderilir.')
                ->schema([
                    Textarea::make('reason')->label('Sebep (zorunlu)')->rows(3)->maxLength(1000)->required(),
                ])
                ->action(fn (array $data, Action $action) => $this->run($action, 'withdrawal.rejected',
                    fn (Withdrawals $w) => $w->reject($this->record, ReleaseActions::admin(), $data['reason']),
                    'Talep reddedildi.', ['reason' => $data['reason']])),
        ];
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    private function run(Action $action, string $event, callable $callback, string $message, array $changes = []): void
    {
        try {
            $callback(app(Withdrawals::class));
        } catch (WithdrawalNotAllowed $e) {
            Notification::make()->danger()->title('İşlem yapılamadı')->body($e->getMessage())->send();
            $action->halt();

            return;
        }

        $this->record->refresh();
        app(AuditLogger::class)->record($event, $this->record, $changes);
        Notification::make()->success()->title($message)->send();
    }
}
