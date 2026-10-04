<?php

namespace App\Filament\Widgets;

use App\Enums\DuplicateFlagStatus;
use App\Enums\ReleaseStatus;
use App\Enums\RequestStatus;
use App\Enums\SpotifyMatchStatus;
use App\Filament\Resources\DuplicateFlags\DuplicateFlagResource;
use App\Filament\Resources\ReleaseRequests\ReleaseRequestResource;
use App\Filament\Resources\Releases\ReleaseResource;
use App\Filament\Resources\SpotifyMatches\SpotifyMatchResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Admin;
use App\Models\DuplicateFlag;
use App\Models\Release;
use App\Models\ReleaseRequest;
use App\Models\SpotifyMatch;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * Dashboard: bekleyen incelemeler, bekleyen talepler, yeni kullanıcılar ve inceleme
 * ekibinin diğer bekleyen işleri. Finans rolü görmez.
 */
class ReviewOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $heading = 'Bekleyen işler';

    public static function canView(): bool
    {
        $admin = auth('admin')->user();

        return $admin instanceof Admin && $admin->isReviewer();
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $oldest = Release::query()->where('status', ReleaseStatus::InReview)->min('submitted_at');
        $newUsers = User::query()->where('created_at', '>=', now()->subDays(7))->count();
        $admin = auth('admin')->user();

        return [
            Stat::make('Bekleyen inceleme', Release::query()->where('status', ReleaseStatus::InReview)->count())
                ->description($oldest ? 'En eskisi '.Carbon::parse($oldest, 'UTC')->diffForHumans() : 'Bekleyen yok')
                ->icon('lucide-disc-3')
                ->url(ReleaseResource::getUrl('index')),
            Stat::make('Bekleyen talep', ReleaseRequest::query()->where('status', RequestStatus::Open)->count())
                ->description('Düzeltme ve kaldırma talepleri')
                ->icon('lucide-inbox')
                ->url(ReleaseRequestResource::getUrl('index')),
            Stat::make('Yeni kullanıcı (7 gün)', $newUsers)
                ->description('Toplam '.User::query()->count().' kullanıcı')
                ->icon('lucide-user-plus')
                ->url($admin instanceof Admin && $admin->isSuperAdmin() ? UserResource::getUrl('index') : null),
            Stat::make('Spotify önerisi', SpotifyMatch::query()->where('status', SpotifyMatchStatus::Pending)->distinct()->count('release_id'))
                ->description('Onay bekleyen yayın')
                ->icon('lucide-radar')
                ->url(SpotifyMatchResource::getUrl('index')),
            Stat::make('Aynı ses dosyası', DuplicateFlag::query()->where('status', DuplicateFlagStatus::Open)->count())
                ->description('Farklı hesaplardan yüklenmiş')
                ->icon('lucide-copy')
                ->url(DuplicateFlagResource::getUrl('index')),
            Stat::make('Mağazalara gönderilen', Release::query()->where('status', ReleaseStatus::Delivered)->count())
                ->description('Yayına girmesi bekleniyor')
                ->icon('lucide-send')
                ->url(ReleaseResource::getUrl('index')),
        ];
    }
}
