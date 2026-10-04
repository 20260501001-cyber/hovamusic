<?php

namespace App\Models;

use App\Enums\DuplicateFlagStatus;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Aynı ses dosyasının (SHA-256) başka bir hesapta da bulunduğunu admin'e bildirir.
 * Kullanıcıya hiçbir şey gösterilmez.
 */
#[Fillable(['media_file_id', 'matched_media_file_id', 'status', 'reviewed_by', 'reviewed_at'])]
class DuplicateFlag extends Model
{
    use Auditable;

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'status' => DuplicateFlagStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function matchedMediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'matched_media_file_id');
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }
}
