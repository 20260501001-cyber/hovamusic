<?php

namespace App\Filament\Resources\Users\Pages;

use App\Domain\Users\AccountStatusChanger;
use App\Domain\Users\Impersonation;
use App\Enums\UserStatus;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

/**
 * @property User $record
 */
class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('impersonate')
                ->label('Kullanıcı olarak görüntüle')
                ->icon('lucide-eye')
                ->color('gray')
                ->authorize('impersonate')
                ->modalDescription('Kullanıcı panelini bu kullanıcının gördüğü gibi açarsın. Yalnızca görüntüleme yapılır; değişiklik engellenir. Başlangıç, bitiş ve sebep kaydedilir.')
                ->modalSubmitActionLabel('Görüntülemeyi başlat')
                ->schema([
                    Textarea::make('reason')->label('Sebep (zorunlu)')->rows(3)->minLength(5)->maxLength(500)->required(),
                ])
                ->action(function (array $data): void {
                    app(Impersonation::class)->start(ReleaseActions::admin(), $this->record, $data['reason'], request());

                    $this->redirect(route('panel.dashboard'));
                }),
            $this->statusAction('suspend', 'Askıya al', 'lucide-pause', 'warning', UserStatus::Suspended),
            $this->statusAction('ban', 'Banla', 'lucide-ban', 'danger', UserStatus::Banned),
            Action::make('reactivate')
                ->label('Hesabı yeniden aç')
                ->icon('lucide-rotate-ccw')
                ->color('success')
                ->authorize('update')
                ->visible(fn (): bool => $this->record->status !== UserStatus::Active)
                ->requiresConfirmation()
                ->schema([
                    Textarea::make('reason')->label('Not')->rows(3)->maxLength(500),
                ])
                ->action(function (array $data): void {
                    app(AccountStatusChanger::class)->change($this->record, UserStatus::Active, ReleaseActions::admin(), $data['reason'] ?? null);
                    Notification::make()->success()->title('Hesap yeniden açıldı.')->send();
                }),
        ];
    }

    private function statusAction(string $name, string $label, string $icon, string $color, UserStatus $status): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->authorize('update')
            ->visible(fn (): bool => $this->record->status !== $status)
            ->modalDescription('Kullanıcı oturumdan çıkarılır ve giriş yapamaz. Sebep kaydedilir.')
            ->modalSubmitActionLabel($label)
            ->schema([
                Textarea::make('reason')->label('Sebep (zorunlu)')->rows(3)->maxLength(500)->required(),
            ])
            ->action(function (array $data) use ($status, $label): void {
                app(AccountStatusChanger::class)->change($this->record, $status, ReleaseActions::admin(), $data['reason']);
                Notification::make()->success()->title($label.' işlemi yapıldı.')->send();
            });
    }
}
