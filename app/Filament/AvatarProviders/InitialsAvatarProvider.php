<?php

namespace App\Filament\AvatarProviders;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Filament'in varsayılan avatarı ui-avatars.com'dan yüklenir: CSP'ye takılır ve
 * admin adını üçüncü bir tarafa gönderir. Baş harfler burada SVG olarak üretilir.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = str(Filament::getNameForDefaultAvatar($record))
            ->trim()
            ->explode(' ')
            ->map(fn (string $part): string => mb_substr((string) preg_replace('/^[^\p{L}\p{N}]+/u', '', $part), 0, 1))
            ->filter()
            ->take(2)
            ->map(fn (string $letter): string => mb_strtoupper($letter))
            ->join('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#131316"/>'
            .'<text x="32" y="32" dy="0.35em" text-anchor="middle" font-family="Archivo, Arial, sans-serif" font-size="24" font-weight="600" fill="#F2F1EF">'
            .e($initials)
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
