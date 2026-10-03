<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, mixed>  $input
     */
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', Rule::unique(User::class, 'email')->ignore($user->id)],
        ], [], [
            'name' => __('auth.register.name'),
        ])->validateWithBag('updateProfileInformation');

        $email = Str::lower($input['email']);

        if ($email !== $user->email) {
            $user->forceFill([
                'name' => trim($input['name']),
                'email' => $email,
                'email_verified_at' => null,
            ])->save();

            $user->sendEmailVerificationNotification();

            return;
        }

        $user->forceFill(['name' => trim($input['name'])])->save();
    }
}
