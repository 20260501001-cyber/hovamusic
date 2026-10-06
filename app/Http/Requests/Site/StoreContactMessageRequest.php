<?php

namespace App\Http\Requests\Site;

use App\Models\ContactMessage;
use App\Support\Security\Turnstile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreContactMessageRequest extends FormRequest
{
    protected $errorBag = 'contact';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'topic' => ['required', Rule::in(ContactMessage::TOPICS)],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! app(Turnstile::class)->verify($this->input('cf-turnstile-response'), $this->ip())) {
                    $validator->errors()->add('turnstile', __('auth.turnstile_failed'));
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('site.contact.name'),
            'email' => __('site.contact.email'),
            'topic' => __('site.contact.topic'),
            'message' => __('site.contact.message'),
        ];
    }
}
