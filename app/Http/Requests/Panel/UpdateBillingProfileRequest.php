<?php

namespace App\Http\Requests\Panel;

use App\Enums\EntityType;
use App\Support\Locale\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBillingProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper((string) $this->input('country')),
            'citizenship' => $this->filled('citizenship') ? strtoupper((string) $this->input('citizenship')) : null,
            'tax_id' => $this->filled('tax_id') ? preg_replace('/\s+/', '', (string) $this->input('tax_id')) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->input('entity_type') === EntityType::Company->value;
        $turkish = $this->input('country') === 'TR';

        return [
            'entity_type' => ['required', Rule::enum(EntityType::class)],
            'legal_name' => ['required', 'string', 'max:200'],
            'company_name' => [Rule::requiredIf($company), 'nullable', 'string', 'max:200'],
            'country' => ['required', Rule::in(Countries::codes())],
            'citizenship' => [Rule::requiredIf(! $company), 'nullable', Rule::in(Countries::codes())],
            'address_line' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:32', 'regex:/^[0-9+()\s\-]{6,32}$/'],
            'tax_id' => array_filter([
                Rule::requiredIf($turkish),
                'nullable',
                'string',
                'max:32',
                $turkish ? ($company ? 'digits:10' : 'digits:11') : null,
            ]),
            'tax_office' => ['nullable', 'string', 'max:120'],
            'date_of_birth' => [Rule::requiredIf(! $company), 'nullable', 'date', 'before:-13 years', 'after:1900-01-01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['entity_type', 'legal_name', 'company_name', 'country', 'citizenship', 'address_line', 'city', 'postal_code', 'phone', 'tax_id', 'tax_office', 'date_of_birth'])
            ->mapWithKeys(fn (string $field): array => [$field => __('finance.profile.'.$field)])
            ->all();
    }
}
