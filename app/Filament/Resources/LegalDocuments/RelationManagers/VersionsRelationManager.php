<?php

namespace App\Filament\Resources\LegalDocuments\RelationManagers;

use App\Domain\Legal\LegalDocuments;
use App\Models\LegalDocument;
use App\Models\LegalDocumentVersion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Sürümler: taslak oluşturulur, önizlenir ve yayımlanır. Yayımlanan sürüm kilitlenir.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Sürümler';

    protected static ?string $modelLabel = 'Sürüm';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            MarkdownEditor::make('body')->label('Metin')->required()->columnSpanFull()
                ->toolbarButtons(['heading', 'bold', 'italic', 'link', 'bulletList', 'orderedList', 'table', 'undo', 'redo'])
                ->helperText('Markdown biçiminde. HTML etiketleri gösterilmez.'),
            TextInput::make('change_note')->label('Değişiklik notu')->maxLength(300)->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        $tz = config('hova.display_timezone');

        return $table
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->label('Sürüm'),
                TextColumn::make('published_at')->label('Yayımlandı')->dateTime('d.m.Y H:i')->timezone($tz)->placeholder('Taslak'),
                TextColumn::make('change_note')->label('Not')->placeholder('—')->limit(80),
                TextColumn::make('updated_at')->label('Son değişiklik')->since(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Yeni sürüm')
                    ->modalWidth('5xl')
                    ->fillForm(fn (): array => ['body' => $this->getOwnerRecord()->versions()->value('body') ?? ''])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'version' => (int) $this->getOwnerRecord()->versions()->max('version') + 1,
                        'created_by' => auth('admin')->id(),
                    ]),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Önizle')
                    ->icon('lucide-eye')
                    ->color('gray')
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->schema([
                        TextEntry::make('rendered')->hiddenLabel()
                            ->state(fn (LegalDocumentVersion $record): HtmlString => new HtmlString($record->html()))
                            ->html()
                            ->extraAttributes(['class' => 'fi-prose']),
                    ]),
                Action::make('publish')
                    ->label('Yayımla')
                    ->icon('lucide-send')
                    ->color('success')
                    ->visible(fn (LegalDocumentVersion $record): bool => $record->published_at === null)
                    ->authorize('update')
                    ->requiresConfirmation()
                    ->modalDescription('Bu sürüm hemen yürürlüğe girer ve kilitlenir. Yeni onaylar bu sürüm numarasıyla kaydedilir.')
                    ->action(function (LegalDocumentVersion $record): void {
                        $record->forceFill(['published_at' => now()])->save();
                        LegalDocuments::forget();
                        Notification::make()->success()->title('Sürüm '.$record->version.' yayımlandı.')->send();
                    }),
                EditAction::make()->modalWidth('5xl'),
                DeleteAction::make(),
            ]);
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof LegalDocument && (auth('admin')->user()?->isSuperAdmin() ?? false);
    }
}
