<?php

namespace App\Models;

use App\Enums\UploadStatus;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'track_id', 'file_name', 'extension', 'total_size', 'received_bytes', 'fingerprint', 'temp_path', 'status', 'media_file_id', 'expires_at'])]
class UploadSession extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'status' => UploadStatus::class,
            'total_size' => 'integer',
            'received_bytes' => 'integer',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Track, $this>
     */
    public function track(): BelongsTo
    {
        return $this->belongsTo(Track::class);
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function mediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class);
    }

    public function isComplete(): bool
    {
        return $this->received_bytes >= $this->total_size;
    }
}
