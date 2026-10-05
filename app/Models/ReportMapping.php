<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Rapor sütun eşleştirme profili: alan adı => rapordaki sütun başlığı.
 */
#[Fillable(['name', 'is_default', 'header_row', 'delimiter', 'decimal_separator', 'default_currency', 'columns'])]
class ReportMapping extends Model
{
    use Auditable;

    /**
     * Raporda aranan alanlar. Zorunlu olanlar: tutar ve ISRC ya da UPC.
     *
     * @var list<string>
     */
    public const FIELDS = [
        'sales_month', 'platform', 'country', 'isrc', 'upc', 'artist_name', 'release_title', 'track_title',
        'sale_type', 'quantity', 'net_amount', 'currency',
    ];

    /**
     * Tek varsayılan profil olur.
     */
    protected static function booted(): void
    {
        static::saved(function (ReportMapping $mapping): void {
            if ($mapping->is_default) {
                self::query()->whereKeyNot($mapping->id)->where('is_default', true)->update(['is_default' => false]);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'header_row' => 'integer',
            'columns' => 'array',
        ];
    }
}
