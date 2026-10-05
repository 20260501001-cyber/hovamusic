<?php

namespace App\Models;

use App\Enums\TaxFormType;
use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Elektronik imzalı W-8BEN / W-8BEN-E. Form verisi şifreli JSON; PDF özel diskte.
 * Form, imza yılından sonraki üçüncü takvim yılının sonuna kadar geçerlidir.
 */
#[Fillable(['form_type', 'data', 'signed_name', 'signer_capacity', 'signed_at', 'ip_address', 'user_agent', 'pdf_path', 'pdf_sha256', 'status', 'expires_at'])]
#[Hidden(['data'])]
class TaxForm extends Model
{
    use HasPublicUlid;

    protected function casts(): array
    {
        return [
            'form_type' => TaxFormType::class,
            'data' => 'encrypted:array',
            'signed_at' => 'datetime',
            'expires_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function isValid(): bool
    {
        return $this->status === 'valid' && ($this->expires_at === null || $this->expires_at->endOfDay()->isFuture());
    }
}
