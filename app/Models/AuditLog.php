<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'changes', 'ip_address', 'user_agent'])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
