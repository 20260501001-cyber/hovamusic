<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Admin'in elle yaptığı eşleştirme; sonraki raporlarda aynı anahtar (ISRC, UPC ya da
 * sanatçı + parça adı) aynı parçaya/yayına bağlanır.
 */
#[Fillable(['key_type', 'key', 'track_id', 'release_id', 'created_by'])]
class MatchRule extends Model
{
    use Auditable;

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
