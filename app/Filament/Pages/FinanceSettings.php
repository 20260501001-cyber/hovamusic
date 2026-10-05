<?php

namespace App\Filament\Pages;

use App\Models\Admin;
use App\Support\Audit\AuditLogger;
use App\Support\Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Para çekme talebinde gösterilen tahmini Wise ücreti. Gerçek ücret ödeme sırasında
 * talebe girilir.
 *
 * @property-read Schema $form
 */
class FinanceSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'lucide-calculator';

    protected static string|UnitEnum|null $navigationGroup = 'Finans';

    protected static ?string $navigationLabel = 'Finans ayarları';

    protected static ?string $title = 'Finans ayarları';

    protected static ?string $slug = 'finans-ayarlari';

    protected static ?int $navigationSort = 90;

    private const KEYS = ['wise_fee_fixed_usd', 'wise_fee_pct'];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->canManageFinance();
    }

    public function mount(Settings $settings): void
    {
        $this->form->fill(collect(self::KEYS)->mapWithKeys(fn (string $key): array => [$key => (string) $settings->get($key)])->all());
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('wise_fee_fixed_usd')
                    ->label('Tahmini Wise ücreti: sabit kısım')
                    ->prefix('USD')
                    ->regex('/^\d{1,4}(\.\d{1,2})?$/')
                    ->required(),
                TextInput::make('wise_fee_pct')
                    ->label('Tahmini Wise ücreti: yüzde')
                    ->suffix('%')
                    ->helperText('Talep tutarına uygulanır. Tahmini ücret = sabit + tutar × yüzde.')
                    ->regex('/^\d{1,2}(\.\d{1,3})?$/')
                    ->required(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Kaydet')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(Settings $settings, AuditLogger $audit): void
    {
        $state = $this->form->getState();
        $data = collect(self::KEYS)->mapWithKeys(fn (string $key): array => [$key => trim((string) $state[$key])])->all();
        $before = collect(self::KEYS)->mapWithKeys(fn (string $key): array => [$key => (string) $settings->get($key)])->all();

        $settings->put($data);

        $changes = collect($data)
            ->filter(fn (string $value, string $key): bool => $before[$key] !== $value)
            ->map(fn (string $value, string $key): array => ['old' => $before[$key], 'new' => $value])
            ->all();

        if ($changes !== []) {
            $audit->record('settings.updated', changes: $changes);
        }

        Notification::make()->success()->title('Ayarlar kaydedildi.')->send();
    }
}
