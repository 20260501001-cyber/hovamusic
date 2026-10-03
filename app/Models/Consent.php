<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'visitor_id', 'type', 'document_version', 'context_type', 'context_id', 'choices', 'ip_address', 'user_agent', 'accepted_at'])]
class Consent extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'choices' => 'array',
            'accepted_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function context(): MorphTo
    {
        return $this->morphTo();
    }
}
