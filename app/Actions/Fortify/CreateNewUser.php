<?php

namespace App\Actions\Fortify;

use App\Domain\Legal\ConsentRecorder;
use App\Enums\AccountType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $consentRules = collect(config('hova.consents.registration'))
            ->keys()
            ->mapWithKeys(fn (string $type): array => ["consents.{$type}" => ['accepted']])
            ->all();

        Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(User::class, 'email')],
            'password' => $this->passwordRules(),
            'account_type' => ['required', Rule::enum(AccountType::class)],
            ...$consentRules,
        ], [
            'consents.*.accepted' => __('auth.register.consent_required'),
        ], [
            'name' => __('auth.register.name'),
            'account_type' => __('auth.register.account_type'),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create([
                'name' => trim($input['name']),
                'email' => Str::lower($input['email']),
                'password' => $input['password'],
                'account_type' => $input['account_type'],
            ]);

            $consents = app(ConsentRecorder::class);

            foreach (array_keys(config('hova.consents.registration')) as $type) {
                $consents->record($user, $type);
            }

            // Açık rıza isteğe bağlıdır; verildiyse ayrıca kaydedilir.
            if (filter_var($input['consents']['acik-riza'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                $consents->record($user, 'acik-riza');
            }

            return $user;
        });
    }
}
