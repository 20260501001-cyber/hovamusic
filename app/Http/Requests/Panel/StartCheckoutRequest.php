<?php

namespace App\Http\Requests\Panel;

use App\Domain\Billing\CheckoutService;
use Illuminate\Foundation\Http\FormRequest;

class StartCheckoutRequest extends FormRequest
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
        return collect(CheckoutService::REQUIRED_CONSENTS)
            ->mapWithKeys(fn (string $type): array => ["consents.{$type}" => ['accepted']])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consents.on-bilgilendirme.accepted' => __('plans.confirm.pre_info_required'),
            'consents.mesafeli-satis.accepted' => __('plans.confirm.contract_required'),
        ];
    }
}
