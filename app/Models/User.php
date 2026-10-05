<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\DisplayCurrency;
use App\Enums\ThemePreference;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'account_type', 'locale', 'display_currency', 'theme'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $attributes = [
        'status' => 'active',
        'locale' => 'tr',
        'display_currency' => 'USD',
        'theme' => 'system',
    ];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->ulid ??= Str::lower((string) Str::ulid());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'account_type' => AccountType::class,
            'status' => UserStatus::class,
            'status_changed_at' => 'datetime',
            'display_currency' => DisplayCurrency::class,
            'theme' => ThemePreference::class,
            'two_factor_confirmed_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Consent, $this>
     */
    public function consents(): HasMany
    {
        return $this->hasMany(Consent::class);
    }

    /**
     * @return HasMany<Artist, $this>
     */
    public function artists(): HasMany
    {
        return $this->hasMany(Artist::class)->orderBy('name');
    }

    /**
     * @return HasMany<Release, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest('id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('ordered_at')->latest('id');
    }

    /**
     * @return HasMany<PlanHistory, $this>
     */
    public function planHistory(): HasMany
    {
        return $this->hasMany(PlanHistory::class)->orderByDesc('starts_at')->orderByDesc('id');
    }

    /**
     * @return HasOne<PolarCustomer, $this>
     */
    public function polarCustomer(): HasOne
    {
        return $this->hasOne(PolarCustomer::class);
    }

    /**
     * @return HasMany<DataRequest, $this>
     */
    public function dataRequests(): HasMany
    {
        return $this->hasMany(DataRequest::class)->latest('id');
    }

    /**
     * Kullanımdaki abonelik; birden fazlaysa dönemi en geç biten.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->getQuery()->reorder()->active()->with('plan')
            ->orderByDesc('current_period_end')->orderByDesc('id')->first();
    }

    public function isLabel(): bool
    {
        return $this->account_type === AccountType::Label;
    }

    public function canSignIn(): bool
    {
        return $this->status->canSignIn();
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }
}
