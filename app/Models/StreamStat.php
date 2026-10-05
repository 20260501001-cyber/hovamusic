<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Onaylanan rapordan aylık özet: kullanıcı, parça, platform, ülke bazında adet ve
 * kullanıcıya düşen gelir (USD). Kazanç ve dinlenme ekranları bu tabloyu okur.
 */
#[Fillable(['user_id', 'report_import_id', 'release_id', 'track_id', 'platform', 'country', 'month', 'quantity', 'revenue_usd'])]
class StreamStat extends Model
{
    protected $table = 'stream_stats_monthly';

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'month' => 'date',
            'quantity' => 'integer',
            'revenue_usd' => 'decimal:6',
        ];
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
}
