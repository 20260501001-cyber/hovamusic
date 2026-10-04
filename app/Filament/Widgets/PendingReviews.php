<?php

namespace App\Filament\Widgets;

use App\Enums\ReleaseStatus;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Models\Admin;
use App\Models\Release;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingReviews extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->isReviewer();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('İnceleme bekleyen yayınlar')
            ->query(fn (): Builder => Release::query()->where('status', ReleaseStatus::InReview)->with(['user', 'artists'])->withCount('tracks'))
            ->defaultSort('submitted_at')
            ->defaultPaginationPageOption(10)
            ->emptyStateHeading('İnceleme bekleyen yayın yok')
            ->columns([
                TextColumn::make('title')->label('Yayın')
                    ->formatStateUsing(fn (Release $record): string => $record->displayTitle())
                    ->description(fn (Release $record): string => $record->artistLine()),
                TextColumn::make('user.email')->label('Kullanıcı'),
                TextColumn::make('tracks_count')->label('Parça'),
                TextColumn::make('release_date')->label('Yayın tarihi')->date('d.m.Y'),
                TextColumn::make('submitted_at')->label('Gönderim')->since(),
            ])
            ->recordUrl(fn (Release $record): string => ReleaseResource::getUrl('view', ['record' => $record]));
    }
}
