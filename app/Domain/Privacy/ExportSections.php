<?php

namespace App\Domain\Privacy;

use App\Models\User;

/**
 * Veri kopyasına sonradan eklenen bölümler (profil, ödeme bilgisi, bakiye).
 */
class ExportSections
{
    /**
     * @var array<string, callable(User): array<string, mixed>>
     */
    private static array $sections = [];

    /**
     * @param  callable(User): array<string, mixed>  $section
     */
    public static function register(string $key, callable $section): void
    {
        self::$sections[$key] = $section;
    }

    /**
     * @return array<string, mixed>
     */
    public static function extra(User $user): array
    {
        $data = [];

        foreach (self::$sections as $section) {
            $data = [...$data, ...$section($user)];
        }

        return $data;
    }
}
