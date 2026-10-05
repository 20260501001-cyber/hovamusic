<?php

namespace App\Filament\Resources\ReportImports;

use App\Domain\Finance\ReportApproval;
use App\Domain\Finance\ReportImporter;
use App\Domain\Finance\ReportUnreadable;
use App\Enums\ReportImportStatus;
use App\Filament\Resources\Releases\Actions\ReleaseActions;
use App\Jobs\ProcessReportImport;
use App\Jobs\RecalculateReportImport;
use App\Models\ReportImport;
use App\Models\ReportMapping;
use App\Support\Audit\AuditLogger;
use App\Support\Format;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ReportImportActions
{
    public static function upload(): Action
    {
        return Action::make('upload')
            ->label('Rapor yükle')
            ->icon('lucide-upload')
            ->authorize('create', ReportImport::class)
            ->modalDescription('CSV ya da XLSX. Dosya kuyrukta okunur; önizleme hazır olunca bu listede "Önizleme" durumuna geçer. Aynı dosya iki kez yüklenemez.')
            ->schema([
                FileUpload::make('file')
                    ->label('Rapor dosyası')
                    ->disk('local')
                    ->directory('report-uploads')
                    ->visibility('private')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->maxSize(51200)
                    ->storeFileNamesIn('original_name')
                    ->required(),
                Select::make('report_mapping_id')
                    ->label('Eşleştirme profili')
                    ->options(fn (): array => ReportMapping::query()->orderByDesc('is_default')->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (): ?int => ReportMapping::query()->where('is_default', true)->value('id'))
                    ->required(),
                TextInput::make('currency')
                    ->label('Para birimi')
                    ->helperText('Yalnızca dosyada para birimi sütunu yoksa: ör. EUR.')
                    ->length(3)
                    ->regex('/^[A-Za-z]{3}$/'),
            ])
            ->action(function (array $data, Action $action): void {
                $disk = Storage::disk('local');
                $path = $data['file'];
                $name = (string) ($data['original_name'] ?? basename($path));

                if (! in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), ['csv', 'txt', 'xlsx'], true)) {
                    $disk->delete($path);
                    Notification::make()->danger()->title('Yalnızca CSV ya da XLSX yüklenebilir.')->send();
                    $action->halt();
                }

                try {
                    $import = app(ReportImporter::class)->upload(
                        $disk->path($path),
                        $name,
                        ReportMapping::query()->findOrFail($data['report_mapping_id']),
                        $data['currency'] ?? null,
                        ReleaseActions::admin(),
                    );
                } catch (ReportUnreadable $e) {
                    Notification::make()->danger()->title('Rapor yüklenemedi')->body($e->getMessage())->send();
                    $action->halt();

                    return;
                } finally {
                    $disk->delete($path);
                }

                app(AuditLogger::class)->record('report.uploaded', $import, ['file' => $name]);
                Notification::make()->success()->title('Rapor sıraya alındı.')->body('Önizleme hazır olunca durum güncellenir.')->send();
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Onayla ve bakiyelere yaz')
            ->icon('lucide-check')
            ->color('success')
            ->visible(fn (ReportImport $record): bool => $record->status === ReportImportStatus::Preview)
            ->disabled(fn (ReportImport $record): bool => ! $record->isApprovable())
            ->authorize('update')
            ->requiresConfirmation()
            ->modalHeading('Rapor onaylansın mı?')
            ->modalDescription(fn (ReportImport $record): string => sprintf(
                '%s tutarındaki kullanıcı payları %d kullanıcının bakiyesine yazılacak. Eşleşmeyen %d satırın geliri kimseye yazılmaz. Hata olursa rapor geri alınabilir.',
                Format::money($record->totals['users_usd'] ?? '0'), (int) ($record->totals['users'] ?? 0), $record->unmatched_count,
            ))
            ->action(function (ReportImport $record, Action $action): void {
                try {
                    app(ReportApproval::class)->approve($record, ReleaseActions::admin());
                } catch (ReportUnreadable $e) {
                    Notification::make()->danger()->title('Onaylanamadı')->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                app(AuditLogger::class)->record('report.approved', $record, ['users_usd' => $record->totals['users_usd'] ?? null]);
                Notification::make()->success()->title('Rapor onaylandı; bakiyeler güncellendi.')->send();
            });
    }

    public static function recalculate(): Action
    {
        return Action::make('recalculate')
            ->label('Yeniden hesapla')
            ->icon('lucide-refresh-cw')
            ->color('gray')
            ->visible(fn (ReportImport $record): bool => $record->status === ReportImportStatus::Preview)
            ->authorize('update')
            ->action(function (ReportImport $record): void {
                RecalculateReportImport::dispatch($record->id)->onQueue('imports');
                Notification::make()->success()->title('Yeniden hesaplama sıraya alındı.')->body('Kur, plan ve elle eşleştirmeler yeniden uygulanır.')->send();
            });
    }

    public static function reprocess(): Action
    {
        return Action::make('reprocess')
            ->label('Dosyayı yeniden oku')
            ->icon('lucide-file-search')
            ->color('gray')
            ->visible(fn (ReportImport $record): bool => in_array($record->status, [ReportImportStatus::Preview, ReportImportStatus::Failed], true))
            ->authorize('update')
            ->schema([
                Select::make('report_mapping_id')
                    ->label('Eşleştirme profili')
                    ->options(fn (): array => ReportMapping::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->default(fn (ReportImport $record): ?int => $record->report_mapping_id)
                    ->required(),
            ])
            ->action(function (array $data, ReportImport $record): void {
                $record->forceFill(['report_mapping_id' => $data['report_mapping_id']])->save();
                ProcessReportImport::dispatch($record->id)->onQueue('imports');
                Notification::make()->success()->title('Dosya yeniden okunmak üzere sıraya alındı.')->send();
            });
    }

    public static function reverse(): Action
    {
        return Action::make('reverse')
            ->label('Geri al')
            ->icon('lucide-undo-2')
            ->color('danger')
            ->visible(fn (ReportImport $record): bool => $record->status === ReportImportStatus::Approved)
            ->authorize('update')
            ->modalHeading('Rapor geri alınsın mı?')
            ->modalDescription('Her kullanıcıya yazılan kazancın ters kaydı yazılır; orijinal kayıtlar silinmez. Kullanıcı tutarı çektiyse bakiyesi eksiye düşebilir.')
            ->schema([
                Textarea::make('reason')->label('Sebep (zorunlu)')->rows(3)->maxLength(500)->required(),
            ])
            ->action(function (array $data, ReportImport $record, Action $action): void {
                try {
                    app(ReportApproval::class)->reverse($record, ReleaseActions::admin(), $data['reason']);
                } catch (ReportUnreadable $e) {
                    Notification::make()->danger()->title('Geri alınamadı')->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                app(AuditLogger::class)->record('report.reversed', $record, ['reason' => $data['reason']]);
                Notification::make()->success()->title('Rapor geri alındı.')->send();
            });
    }

    public static function discard(): Action
    {
        return Action::make('discard')
            ->label('Sil')
            ->icon('lucide-trash-2')
            ->color('danger')
            ->visible(fn (ReportImport $record): bool => in_array($record->status, [ReportImportStatus::Uploaded, ReportImportStatus::Preview, ReportImportStatus::Failed], true))
            ->authorize('delete')
            ->requiresConfirmation()
            ->modalDescription('Önizleme ve dosya silinir; bakiyelere hiçbir şey yazılmamıştır.')
            ->action(function (ReportImport $record, Action $action): void {
                try {
                    app(ReportImporter::class)->discard($record);
                } catch (ReportUnreadable $e) {
                    Notification::make()->danger()->title('Silinemedi')->body($e->getMessage())->send();
                    $action->halt();

                    return;
                }

                app(AuditLogger::class)->record('report.discarded', changes: ['file' => $record->original_name]);
                Notification::make()->success()->title('Rapor silindi.')->send();
                $action->redirect(ReportImportResource::getUrl('index'));
            });
    }
}
