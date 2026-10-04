<?php

namespace App\Http\Requests\Panel;

use App\Enums\RequestType;
use App\Models\Release;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReleaseRequestRequest extends FormRequest
{
    /**
     * Hatalar ayrı bir torbada döner; sayfadaki diğer formlarla karışmaz.
     */
    protected $errorBag = 'request';

    public function authorize(): bool
    {
        $release = $this->route('release');

        return $release instanceof Release && $this->user()?->can('request', $release) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(RequestType::class)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('release.requests.fields.type'),
            'message' => __('release.requests.fields.message'),
        ];
    }
}
