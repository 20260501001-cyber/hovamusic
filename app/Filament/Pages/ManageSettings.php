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
 * Yayın sihirbazının ve sitenin kullandığı, admin'in değiştirebildiği değerler.
 *
 * @property-read Schema $form
 */
class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'lucide-sliders-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Ayarlar';

    protected static ?string $title = 'Ayarlar';

    protected static ?string $slug = 'ayarlar';

    protected static ?int $navigationSort = 80;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->isSuperAdmin();
    }

    public function mount(Settings $settings): void
    {
        $this->form->fill([
            'release_min_lead_days' => $settings->releaseLeadDays(),
            'cover_max_mb' => $settings->int('cover_max_mb'),
            'audio_max_mb' => $settings->int('audio_max_mb'),
            'isrc_registrant' => (string) $settings->get('isrc_registrant'),
            'google_site_verification' => (string) $settings->get('google_site_verification'),
            'contact_email' => (string) $settings->get('contact_email'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                TextInput::make('release_min_lead_days')
                    ->label('En erken yayın tarihi (bugünden kaç gün sonra)')
                    ->helperText('Kullanıcı bugünden en az bu kadar gün sonrasını seçebilir.')
                    ->integer()
                    ->minValue(0)
                    ->maxValue(60)
                    ->suffix('gün')
                    ->required(),
                TextInput::make('cover_max_mb')
                    ->label('Kapak dosyası üst sınırı')
                    ->helperText('Geçici yükleme sınırı 50 MB olduğu için en fazla 50 MB.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(50)
                    ->suffix('MB')
                    ->required(),
                TextInput::make('audio_max_mb')
                    ->label('Ses dosyası üst sınırı')
                    ->integer()
                    ->minValue(50)
                    ->maxValue(4096)
                    ->suffix('MB')
                    ->required(),
                TextInput::make('isrc_registrant')
                    ->label('ISRC öneki (ülke + kayıt sahibi kodu)')
                    ->helperText('PPL\'in verdiği ilk beş karakter, ör. GXLM5. "ISRC kodum yok" diyen kullanıcıların parçalarına bu önekle sırayla kod atanır; kullanıcılar bu önekle başlayan kod giremez.')
                    ->length(5)
                    ->regex('/^[A-Za-z]{2}[A-Za-z0-9]{3}$/')
                    ->required(),
                TextInput::make('google_site_verification')
                    ->label('Google Search Console doğrulama kodu')
                    ->helperText('HTML etiketi yönteminde content="..." içindeki değer. Tüm sayfalara meta etiketi olarak eklenir.')
                    ->maxLength(120)
                    ->regex('/^[A-Za-z0-9_\-]*$/'),
                TextInput::make('contact_email')
                    ->label('İletişim formu e-postası')
                    ->helperText('İletişim formundan gelen mesajlar bu adrese gider ve İletişim sayfasında gösterilir. Boşsa gönderen adresi kullanılır.')
                    ->email()
                    ->maxLength(191),
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
        $data = [
            'release_min_lead_days' => (int) $state['release_min_lead_days'],
            'cover_max_mb' => (int) $state['cover_max_mb'],
            'audio_max_mb' => (int) $state['audio_max_mb'],
            'isrc_registrant' => strtoupper(trim((string) $state['isrc_registrant'])),
            'google_site_verification' => trim((string) ($state['google_site_verification'] ?? '')),
            'contact_email' => trim((string) ($state['contact_email'] ?? '')),
        ];
        $before = [
            'release_min_lead_days' => $settings->releaseLeadDays(),
            'cover_max_mb' => $settings->int('cover_max_mb'),
            'audio_max_mb' => $settings->int('audio_max_mb'),
            'isrc_registrant' => (string) $settings->get('isrc_registrant'),
            'google_site_verification' => (string) $settings->get('google_site_verification'),
            'contact_email' => (string) $settings->get('contact_email'),
        ];

        $settings->put($data);

        $changes = collect($data)
            ->filter(fn (int|string $value, string $key): bool => $before[$key] !== $value)
            ->map(fn (int|string $value, string $key): array => ['old' => $before[$key], 'new' => $value])
            ->all();

        if ($changes !== []) {
            $audit->record('settings.updated', changes: $changes);
        }

        Notification::make()->success()->title('Ayarlar kaydedildi.')->send();
    }
}
