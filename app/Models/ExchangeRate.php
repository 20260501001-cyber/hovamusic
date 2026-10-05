<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Dönem kuru: 1 from_currency = rate to_currency. Rapor tutarları (rapor para birimi
 * → USD) ve kullanıcı ekranındaki yaklaşık gösterim (USD → TRY/EUR) için.
 */
#[Fillable(['period', 'from_currency', 'to_currency', 'rate', 'created_by'])]
class ExchangeRate extends Model
{
    use Auditable;

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:8',
        ];
    }
}
