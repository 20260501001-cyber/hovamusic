<?php

namespace App\Models;

use App\Enums\ReleaseStatus;
use App\Enums\RequestStatus;
use App\Enums\RequestType;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Onaylanmış yayın için kullanıcının açtığı düzeltme ya da kaldırma talebi.
 * Düzeltme talebi yayının durumunu değiştirmez; kaldırma talebi "Kaldırma talebi"
 * durumuna geçirir ve reddedilirse yayın önceki durumuna döner.
 */
#[Fillable(['type', 'message', 'status', 'previous_status', 'admin_note', 'handled_at'])]
class ReleaseRequest extends Model
{
    use HasPublicUlid;

    protected $attributes = [
        'status' => 'open',
    ];

    protected function casts(): array
    {
        return [
            'type' => RequestType::class,
            'status' => RequestStatus::class,
            'previous_status' => ReleaseStatus::class,
            'handled_at' => 'datetime',
        ];
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
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by');
    }

    public function isOpen(): bool
    {
        return $this->status === RequestStatus::Open;
    }
}
