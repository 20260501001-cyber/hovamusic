<?php

namespace App\Domain\Isrc;

use App\Enums\IsrcSource;
use App\Models\Admin;
use App\Models\IsrcCode;
use App\Models\Release;
use App\Models\Track;
use App\Rules\Isrc;
use App\Support\Settings;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Hova Music'in ISRC önekiyle (ülke + kayıt sahibi kodu, ör. GXLM5) kod atar.
 * Kod: önek + atama yılının son iki hanesi + o yıl içinde sıradaki 5 haneli numara
 * (GXLM5 26 00001). Her atanan kod isrc_codes tablosuna yazılır; kayıt silinmez,
 * parça silinse bile aynı kod bir daha atanmaz.
 */
class IsrcAllocator
{
    public const MAX_SEQUENCE = 99999;

    public function __construct(private readonly Settings $settings) {}

    public function registrant(): ?string
    {
        return $this->settings->isrcRegistrant();
    }

    public function isEnabled(): bool
    {
        return $this->registrant() !== null;
    }

    /**
     * Kodun Hova Music önekiyle başlayıp başlamadığı; bu kodları yalnızca sistem ve admin atar.
     */
    public function isReserved(?string $isrc): bool
    {
        $registrant = $this->registrant();
        $normalized = Isrc::normalize($isrc) ?? strtoupper(str_replace(['-', ' '], '', (string) $isrc));

        return $registrant !== null && str_starts_with($normalized, $registrant);
    }

    /**
     * Kullanıcının "ISRC kodum yok" dediği ve henüz kodu olmayan parçalara kod atar.
     *
     * @return list<string> Atanan kodlar
     */
    public function assignMissing(Release $release, Authenticatable $actor): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        return $release->tracks()
            ->where('has_own_isrc', false)
            ->whereNull('isrc')
            ->get()
            ->map(fn (Track $track): string => $this->assign($track, $actor))
            ->all();
    }

    /**
     * @throws IsrcExhausted
     */
    public function assign(Track $track, Authenticatable $actor): string
    {
        $registrant = $this->registrant() ?? throw new IsrcExhausted(__('isrc.not_configured'));

        return Cache::lock('isrc-allocation', 10)->block(10, function () use ($track, $actor, $registrant): string {
            for ($attempt = 0; $attempt < 3; $attempt++) {
                $year = (int) now()->format('y');
                $next = (int) IsrcCode::query()->where('year', $year)->max('sequence') + 1;

                if ($next > self::MAX_SEQUENCE) {
                    throw new IsrcExhausted(__('isrc.exhausted', ['year' => now()->format('Y')]));
                }

                $isrc = $registrant.sprintf('%02d', $year).sprintf('%05d', $next);

                try {
                    return DB::transaction(function () use ($track, $actor, $isrc, $year, $next): string {
                        IsrcCode::query()->create([
                            'isrc' => $isrc,
                            'year' => $year,
                            'sequence' => $next,
                            'track_id' => $track->id,
                            'assigned_by_type' => $actor instanceof Admin ? 'admin' : 'system',
                            'assigned_by_id' => $actor instanceof Admin ? $actor->getKey() : null,
                        ]);

                        $track->forceFill(['isrc' => $isrc, 'isrc_source' => IsrcSource::Hova])->save();

                        return $isrc;
                    });
                } catch (UniqueConstraintViolationException) {
                    // Aynı numarayı başka bir süreç aldıysa sıradakini dene.
                }
            }

            throw new IsrcExhausted(__('isrc.busy'));
        });
    }

    /**
     * Admin'in elle girdiği kod Hova Music önekiyle başlıyorsa kayda geçirilir; başka bir
     * parçaya atanmış bir kod yeniden kullanılamaz.
     *
     * @throws IsrcAlreadyUsed
     */
    public function claimManual(Track $track, string $isrc, Admin $admin): void
    {
        if (! $this->isReserved($isrc)) {
            return;
        }

        $existing = IsrcCode::query()->where('isrc', $isrc)->first();

        if ($existing !== null) {
            if ($existing->track_id === $track->id) {
                return;
            }

            throw new IsrcAlreadyUsed(__('isrc.already_used', ['isrc' => $existing->formatted()]));
        }

        IsrcCode::query()->create([
            'isrc' => $isrc,
            'year' => (int) substr($isrc, 5, 2),
            'sequence' => (int) substr($isrc, 7),
            'track_id' => $track->id,
            'assigned_by_type' => 'admin',
            'assigned_by_id' => $admin->getKey(),
        ]);
    }

    /**
     * Bu yıl atanacak sıradaki kod (yalnızca bilgi; atama sırasında yeniden hesaplanır).
     */
    public function nextCode(): ?string
    {
        $registrant = $this->registrant();

        if ($registrant === null) {
            return null;
        }

        $year = (int) now()->format('y');
        $next = (int) IsrcCode::query()->where('year', $year)->max('sequence') + 1;

        return $next > self::MAX_SEQUENCE ? null : $registrant.sprintf('%02d', $year).sprintf('%05d', $next);
    }
}
