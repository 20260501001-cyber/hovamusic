<?php

namespace App\Http\Requests\Panel;

use App\Models\PayoutMethod;
use App\Rules\Iban;
use App\Rules\SwiftBic;
use App\Support\Locale\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePayoutMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'bank_country' => strtoupper((string) $this->input('bank_country')),
            'currency' => strtoupper((string) $this->input('currency')),
            'iban' => $this->filled('iban') ? Iban::normalize((string) $this->input('iban')) : null,
            'account_number' => $this->filled('account_number') ? preg_replace('/\s+/', '', (string) $this->input('account_number')) : null,
            'routing_number' => $this->filled('routing_number') ? preg_replace('/\s+/', '', (string) $this->input('routing_number')) : null,
            'swift_bic' => $this->filled('swift_bic') ? strtoupper(trim((string) $this->input('swift_bic'))) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_holder' => ['required', 'string', 'max:200'],
            'bank_country' => ['required', Rule::in(Countries::codes())],
            'currency' => ['required', Rule::in(PayoutMethod::CURRENCIES)],
            'iban' => ['nullable', 'string', 'max:34', new Iban($this->input('bank_country'))],
            'account_number' => ['nullable', 'string', 'max:34', 'regex:/^[A-Za-z0-9\-]{4,34}$/'],
            'routing_number' => ['nullable', 'string', 'max:34', 'regex:/^[A-Za-z0-9\-]{3,34}$/'],
            'swift_bic' => ['nullable', 'string', new SwiftBic],
            'bank_name' => ['nullable', 'string', 'max:200'],
            'current_password' => ['required', 'current_password:web'],
        ];
    }

    /**
     * Yeni hesap numarası girilmezse kayıtlı olan korunur; hiç yoksa biri zorunlu.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $existing = $this->user()?->payoutMethod;
                $hasExisting = $existing !== null && (filled($existing->iban) || filled($existing->account_number));

                if (! $this->filled('iban') && ! $this->filled('account_number') && ! $hasExisting) {
                    $validator->errors()->add('iban', __('finance.payout_page.errors.account_required'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['account_holder', 'bank_country', 'currency', 'iban', 'account_number', 'routing_number', 'swift_bic', 'bank_name', 'current_password'])
            ->mapWithKeys(fn (string $field): array => [$field => __('finance.payout_page.'.$field)])
            ->all();
    }
}
