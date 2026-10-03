<?php

namespace App\Models;

use App\Http\Middleware\RestrictAdminIp;
use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

#[Fillable(['cidr', 'label', 'created_by'])]
class AdminAllowedIp extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(RestrictAdminIp::CACHE_KEY));
        static::deleted(fn () => Cache::forget(RestrictAdminIp::CACHE_KEY));
    }

    /**
     * @return BelongsTo<Admin, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
