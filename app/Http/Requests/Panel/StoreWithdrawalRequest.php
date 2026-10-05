<?php

namespace App\Http\Requests\Panel;

use Illuminate\Foundation\Http\FormRequest;

class StoreWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $amount = trim((string) $this->input('amount'));

        // "1.234,56" ve "1234,56" Türkçe yazımı noktalı biçime çevrilir.
        if (str_contains($amount, ',')) {
            $amount = str_replace(['.', ','], ['', '.'], $amount);
        }

        $this->merge(['amount' => str_replace([' ', '$'], '', $amount)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['amount' => __('finance.withdrawals_page.amount')];
    }
}
