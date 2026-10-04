<?php

namespace App\Models;

use App\Enums\ReleaseStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['from_status', 'to_status', 'actor_type', 'actor_id', 'note'])]
class ReleaseStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'from_status' => ReleaseStatus::class,
            'to_status' => ReleaseStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Release, $this>
     */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }
}
