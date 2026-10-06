<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * İletişim formundan gelen mesaj.
 */
#[Fillable(['name', 'email', 'topic', 'message', 'user_id', 'ip_address', 'user_agent'])]
class ContactMessage extends Model
{
    public const TOPICS = ['general', 'distribution', 'payments', 'partnership', 'press', 'privacy'];

    protected function casts(): array
    {
        return ['handled_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'handled_by');
    }
}
