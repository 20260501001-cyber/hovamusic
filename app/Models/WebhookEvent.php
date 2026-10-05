<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Gelen webhook olayları. (provider, event_id) benzersizdir; aynı olay ikinci kez
 * gelirse işlenmez.
 */
#[Fillable(['provider', 'event_id', 'type', 'payload', 'received_at', 'processed_at', 'attempts', 'error'])]
class WebhookEvent extends Model
{
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }
}
