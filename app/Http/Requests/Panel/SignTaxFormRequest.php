<?php

namespace App\Http\Requests\Panel;

use App\Domain\Finance\TaxForms;
use App\Enums\TaxFormType;
use App\Support\Locale\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SignTaxFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function formType(): TaxFormType
    {
        return app(TaxForms::class)->typeFor($this->user());
    }

    protected function prepareForValidation(): void
    {
        foreach (['citizenship', 'incorporation_country', 'country', 'treaty_country'] as $field) {
            if ($this->filled($field)) {
                $this->merge([$field => strtoupper((string) $this->input($field))]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $countries = Rule::in(Countries::codes());
        $common = [
            'address' => ['required', 'string', 'max:300'],
            'city' => ['required', 'string', 'max:160'],
            'country' => ['required', $countries],
            'mailing_address' => ['nullable', 'string', 'max:300'],
            'us_tin' => ['nullable', 'string', 'max:20', 'regex:/^[0-9\-]{9,11}$/'],
            'foreign_tin' => ['nullable', 'string', 'max:40'],
            'signed_name' => ['required', 'string', 'max:200'],
            'certify' => ['accepted'],
            'esign' => ['accepted'],
        ];

        if ($this->formType() === TaxFormType::W8BenE) {
            return [
                ...$common,
                'organization_name' => ['required', 'string', 'max:200'],
                'incorporation_country' => ['required', $countries],
                'chapter3_status' => ['required', Rule::in(TaxForms::CHAPTER3_STATUSES)],
                'chapter4_status' => ['required', Rule::in(TaxForms::CHAPTER4_STATUSES)],
                'chapter4_other' => ['nullable', 'required_if:chapter4_status,other', 'string', 'max:200'],
                'giin' => ['nullable', 'string', 'regex:/^[A-Z0-9]{6}\.[A-Z0-9]{5}\.[A-Z]{2}\.[0-9]{3}$/i'],
                'signer_capacity' => ['required', 'string', 'max:120'],
            ];
        }

        return [
            ...$common,
            'name' => ['required', 'string', 'max:200'],
            'citizenship' => ['required', $countries],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'treaty_country' => ['nullable', $countries],
            'treaty_article' => ['nullable', 'required_with:treaty_rate', 'string', 'max:60'],
            'treaty_rate' => ['nullable', 'numeric', 'between:0,30'],
            'treaty_income_type' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Bireysel formda imza, formdaki adla aynı olmalı.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->formType() !== TaxFormType::W8Ben || $validator->errors()->hasAny(['name', 'signed_name'])) {
                    return;
                }

                $normalize = fn (?string $value): string => Str::lower((string) preg_replace('/\s+/u', ' ', trim((string) $value)));

                if ($normalize($this->input('signed_name')) !== $normalize($this->input('name'))) {
                    $validator->errors()->add('signed_name', __('finance.tax_form_page.name_mismatch', ['name' => trim((string) $this->input('name'))]));
                }
            },
        ];
    }

    /**
     * Forma yazılacak alanlar (imza ve onay kutuları hariç).
     *
     * @return array<string, string|null>
     */
    public function formData(): array
    {
        $keys = $this->formType() === TaxFormType::W8BenE
            ? ['organization_name', 'incorporation_country', 'chapter3_status', 'chapter4_status', 'chapter4_other', 'giin', 'address', 'city', 'country', 'mailing_address', 'us_tin', 'foreign_tin']
            : ['name', 'citizenship', 'date_of_birth', 'address', 'city', 'country', 'mailing_address', 'us_tin', 'foreign_tin', 'treaty_country', 'treaty_article', 'treaty_rate', 'treaty_income_type'];

        return collect($this->validated())
            ->only($keys)
            ->map(fn ($value): ?string => $value === null || $value === '' ? null : trim((string) $value))
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect(['name', 'organization_name', 'citizenship', 'incorporation_country', 'address', 'city', 'country', 'mailing_address', 'us_tin', 'foreign_tin', 'giin', 'date_of_birth', 'treaty_country', 'treaty_article', 'treaty_rate', 'treaty_income_type', 'chapter3_status', 'chapter4_status', 'chapter4_other', 'signed_name', 'signer_capacity', 'certify', 'esign'])
            ->mapWithKeys(fn (string $field): array => [$field => __('finance.tax_form_page.'.$field)])
            ->all();
    }
}
