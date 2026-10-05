<?php

namespace App\Models;

use App\Enums\EntityType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Profil ve fatura bilgisi. Adres, telefon, vergi numarası ve doğum tarihi uygulama
 * anahtarıyla şifreli saklanır ve loglanmaz.
 */
#[Fillable([
    'entity_type', 'legal_name', 'company_name', 'country', 'citizenship', 'address_line', 'city', 'postal_code',
    'phone', 'tax_id', 'tax_office', 'date_of_birth',
])]
#[Hidden(['address_line', 'city', 'postal_code', 'phone', 'tax_id', 'date_of_birth'])]
class UserProfile extends Model
{
    protected $attributes = [
        'entity_type' => 'individual',
    ];

    protected function casts(): array
    {
        return [
            'entity_type' => EntityType::class,
            'address_line' => 'encrypted',
            'city' => 'encrypted',
            'postal_code' => 'encrypted',
            'phone' => 'encrypted',
            'tax_id' => 'encrypted',
            'date_of_birth' => 'encrypted',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isComplete(): bool
    {
        return filled($this->legal_name) && filled($this->country) && filled($this->address_line) && filled($this->city)
            && ($this->entity_type !== EntityType::Company || filled($this->company_name));
    }
}
