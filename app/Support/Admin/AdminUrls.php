<?php

namespace App\Support\Admin;

use App\Models\Release;
use App\Models\TaxForm;
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

    public static function taxForm(TaxForm $form): string
    {
        return URL::temporarySignedRoute('admin.tax-forms.download', now()->addMinutes(self::DOWNLOAD_TTL_MINUTES), ['taxForm' => $form->ulid]);
    }
}
