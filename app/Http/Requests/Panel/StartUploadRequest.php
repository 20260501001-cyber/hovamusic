<?php

namespace App\Http\Requests\Panel;

use App\Models\Track;
use Illuminate\Foundation\Http\FormRequest;

class StartUploadRequest extends FormRequest
{
    private ?Track $resolvedTrack = null;

    /**
     * Parça kullanıcının düzenleyebildiği bir yayına ait olmalı.
     */
    public function authorize(): bool
    {
        $track = $this->track();

        return $track !== null && $this->user()->can('update', $track->release);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'track' => ['required', 'string', 'size:26'],
            'name' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'fingerprint' => ['required', 'string', 'max:64'],
        ];
    }

    public function track(): ?Track
    {
        if ($this->resolvedTrack === null && is_string($this->input('track'))) {
            $this->resolvedTrack = Track::query()->with('release')->where('ulid', $this->input('track'))->first();
        }

        return $this->resolvedTrack;
    }
}
