<?php

namespace App\Domain\Media;

use App\Models\MediaFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * Özel diskteki dosyalara yalnızca imzalı ve süreli adresle erişilir. Bitiş zamanı
 * 10 dakikalık dilimlere yuvarlanır; böylece aynı sayfa yenilendiğinde adres değişmez
 * ve tarayıcı önbelleği işe yarar.
 */
class MediaUrl
{
    public const TTL_MINUTES = 15;

    public static function temporary(MediaFile $media, bool $download = false): string
    {
        $bucket = 600;
        $expires = Carbon::createFromTimestamp((intdiv(now()->getTimestamp(), $bucket) + 1) * $bucket)
            ->addMinutes(self::TTL_MINUTES - 10);

        return URL::temporarySignedRoute('media.show', $expires, array_filter([
            'media' => $media->ulid,
            'indir' => $download ? 1 : null,
        ]));
    }
}
