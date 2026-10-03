<?php

namespace App\Http\Requests\Panel;

use App\Enums\DisplayCurrency;
use App\Enums\ThemePreference;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
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
            'theme' => ['required', Rule::enum(ThemePreference::class)],
            'display_currency' => ['required', Rule::enum(DisplayCurrency::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'theme' => __('panel.account.theme'),
            'display_currency' => __('panel.account.currency'),
        ];
    }
}
