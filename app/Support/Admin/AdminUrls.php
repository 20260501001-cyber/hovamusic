<?php

namespace App\Support\Admin;

use App\Models\Release;
use Illuminate\Support\Facades\URL;

/**
 * Admin indirmeleri için imzalı ve süreli adresler. Adres tıklama anında üretilir.
 */
class AdminUrls
{
    public const DOWNLOAD_TTL_MINUTES = 10;

    public static function releaseArchive(Release $release): string
    {
        return URL::temporarySignedRoute('admin.releases.archive', now()->addMinutes(self::DOWNLOAD_TTL_MINUTES), ['release' => $release->ulid]);
    }
}
