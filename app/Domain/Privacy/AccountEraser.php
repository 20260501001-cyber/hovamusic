<?php

namespace App\Domain\Privacy;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Hesap silme: kişisel veriler anonimleştirilir ve hesap kapatılır. Yasal saklama
 * yükümlülüğü olan kayıtlar (onaylar, siparişler, bakiye defteri, vergi formları)
 * silinmez; yayınlar ve mağazalardaki karşılıkları ayrıca kaldırma talebiyle yönetilir.
 */
class AccountEraser
{
    /**
     * @var array<string, callable(User): void>
     */
    private static array $hooks = [];

    /**
     * @param  callable(User): void  $hook
     */
    public static function extend(string $key, callable $hook): void
    {
        self::$hooks[$key] = $hook;
    }

    public function erase(User $user): void
    {
        DB::transaction(function () use ($user): void {
            foreach (self::$hooks as $hook) {
                $hook($user);
            }

            $user->artists()->get()->each->delete();
            $user->notifications()->delete();

            $user->forceFill([
                'name' => __('privacy.deleted_user'),
                'email' => 'silindi+'.$user->ulid.'@hovamusic.invalid',
                'password' => Hash::make(Str::random(64)),
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'last_login_ip' => null,
                'email_verified_at' => null,
            ])->save();

            if (config('session.driver') === 'database') {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }

            $user->delete();
        });
    }
}
