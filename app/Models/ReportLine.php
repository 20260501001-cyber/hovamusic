<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Raporun ham satırı ve hesap sonucu. Satırlar yalnızca içe aktarma ve eşleştirme
 * servisleriyle yazılır.
 */
#[Fillable(['report_import_id', 'row_number', 'sales_month', 'platform', 'country', 'isrc', 'upc', 'artist_name', 'release_title', 'track_title', 'sale_type', 'quantity', 'net_amount', 'currency', 'raw', 'match_status', 'match_source', 'track_id', 'release_id', 'user_id', 'share_pct', 'fx_rate', 'amount_usd', 'user_amount_usd'])]
class ReportLine extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'sales_month' => 'date',
            'raw' => 'array',
            'quantity' => 'integer',
            'net_amount' => 'decimal:10',
            'share_pct' => 'decimal:2',
            'fx_rate' => 'decimal:8',
            'amount_usd' => 'decimal:10',
            'user_amount_usd' => 'decimal:10',
        ];
    }

    /**
     * @return BelongsTo<ReportImport, $this>
     */
    public function import(): BelongsTo
    {
        return $this->belongsTo(ReportImport::class, 'report_import_id');
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
