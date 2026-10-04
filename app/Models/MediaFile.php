<?php

namespace App\Models;

use App\Enums\MediaKind;
use App\Enums\MediaStatus;
use App\Models\Concerns\HasPublicUlid;
use App\Support\Format;
use Database\Factories\MediaFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'kind', 'disk', 'path', 'original_name', 'mime', 'size', 'sha256', 'format', 'width', 'height', 'color_space',
    'codec', 'sample_rate', 'bit_depth', 'channels', 'duration_ms', 'validation_status', 'validation_errors', 'analyzed_at',
])]
class MediaFile extends Model
{
    /** @use HasFactory<MediaFileFactory> */
    use HasFactory, HasPublicUlid;

    protected $attributes = [
        'validation_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'kind' => MediaKind::class,
            'validation_status' => MediaStatus::class,
            'validation_errors' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'sample_rate' => 'integer',
            'bit_depth' => 'integer',
            'channels' => 'integer',
            'duration_ms' => 'integer',
            'analyzed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return $this->validation_status === MediaStatus::Valid;
    }

    public function isPending(): bool
    {
        return $this->validation_status === MediaStatus::Pending;
    }

    /**
     * Ses dosyasının ölçülen değerleri: "WAV · 24 bit · 44,1 kHz · stereo · 3:42".
     */
    public function audioSummary(): string
    {
        return collect([
            strtoupper((string) $this->format),
            $this->bit_depth ? $this->bit_depth.' bit' : null,
            $this->sample_rate ? Format::kiloHertz($this->sample_rate) : null,
            $this->channels ? trans_choice('media.audio.channels', $this->channels) : null,
            $this->duration_ms ? Format::duration($this->duration_ms) : null,
        ])->filter()->implode(' · ');
    }

    public function absolutePath(): string
    {
        return Storage::disk($this->disk)->path($this->path);
    }

    public function deleteWithFile(): void
    {
        Storage::disk($this->disk)->delete($this->path);
        $this->delete();
    }
}
