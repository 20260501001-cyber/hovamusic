<?php

namespace App\Filament\Resources\Users;

use Filament\Schemas\Components\Component;

/**
 * Kullanıcı detayına başka alanların (ör. finans) eklediği bölümler.
 */
class UserExtraSections
{
    /**
     * @var list<callable(): Component>
     */
    private static array $sections = [];

    /**
     * @param  callable(): Component  $section
     */
    public static function add(callable $section): void
    {
        self::$sections[] = $section;
    }

    /**
     * @return list<Component>
     */
    public static function sections(): array
    {
        return array_map(fn (callable $section): Component => $section(), self::$sections);
    }
}
