<?php

namespace App\Http\Requests\Panel;

use App\Enums\DataRequestType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataRequestRequest extends FormRequest
{
    protected $errorBag = 'privacy';

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(DataRequestType::class)],
            'message' => ['nullable', 'string', 'max:2000', Rule::requiredIf($this->input('type') === DataRequestType::Correction->value)],
            'current_password' => [Rule::requiredIf($this->input('type') === DataRequestType::Deletion->value), 'nullable', 'current_password:web'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'message' => __('privacy.fields.message'),
            'current_password' => __('auth.fields.current_password'),
        ];
    }
}
