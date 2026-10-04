<?php

namespace App\Rules;

use App\Domain\Isrc\IsrcAllocator;
use App\Models\IsrcCode;
use App\Models\Track;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Admin'in parçaya elle girdiği ISRC: biçim, aynı yayında tekrar ve Hova Music
 * önekli kodun başka bir parçaya atanmış olup olmadığı.
 */
class AdminTrackIsrc implements ValidationRule
{
    public function __construct(private readonly ?Track $track) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        $isrc = Isrc::normalize((string) $value);

        if ($isrc === null) {
            $fail(__('release.validation.isrc_format'));

            return;
        }

        if ($this->track === null) {
            return;
        }

        $duplicate = Track::query()
            ->where('release_id', $this->track->release_id)
            ->whereKeyNot($this->track->getKey())
            ->where('isrc', $isrc)
            ->exists();

        if ($duplicate) {
            $fail(__('release.validation.isrc_duplicate', ['isrc' => $value]));

            return;
        }

        if (app(IsrcAllocator::class)->isReserved($isrc)) {
            $owner = IsrcCode::query()->where('isrc', $isrc)->first();

            if ($owner !== null && $owner->track_id !== $this->track->getKey()) {
                $fail(__('isrc.already_used', ['isrc' => $owner->formatted()]));
            }
        }
    }
}
