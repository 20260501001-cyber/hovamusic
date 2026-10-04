<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Admin panelinden değiştirilebilen site ayarları. Değer veritabanında yoksa
 * config/hova.php içindeki varsayılan kullanılır.
 */
class Settings
{
    private const CACHE_KEY = 'hova.settings';

    public function get(string $key): mixed
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());

        return $stored[$key] ?? config("hova.settings.{$key}");
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function releaseLeadDays(): int
    {
        return max(0, $this->int('release_min_lead_days'));
    }

    public function coverMaxBytes(): int
    {
        return $this->int('cover_max_mb') * 1024 * 1024;
    }

    public function audioMaxBytes(): int
    {
        return $this->int('audio_max_mb') * 1024 * 1024;
    }
}
