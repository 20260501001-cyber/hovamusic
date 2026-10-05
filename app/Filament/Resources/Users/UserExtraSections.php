<?php

namespace App\Filament\Resources\Users;

use Filament\Schemas\Components\Component;

/**
 * Kullanıcı detayına başka alanların (ör. finans) eklediği bölümler.
 */
class UserExtraSections
{
    /**
     * @var array<string, callable(): Component>
     */
    private static array $sections = [];

    /**
     * @param  callable(): Component  $section
     */
    public static function add(string $key, callable $section): void
    {
        self::$sections[$key] = $section;
    }

    /**
     * @return list<Component>
     */
    public static function sections(): array
    {
        return array_values(array_map(fn (callable $section): Component => $section(), self::$sections));
    }
}
