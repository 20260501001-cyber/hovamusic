<?php

namespace Database\Factories;

use App\Enums\AccountType;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'account_type' => AccountType::Artist,
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function label(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => AccountType::Label,
        ]);
    }

    public function suspended(string $reason = 'Test'): static
    {
        return $this->afterMaking(function (User $user) use ($reason): void {
            $user->status = UserStatus::Suspended;
            $user->status_reason = $reason;
        });
    }

    public function banned(string $reason = 'Test'): static
    {
        return $this->afterMaking(function (User $user) use ($reason): void {
            $user->status = UserStatus::Banned;
            $user->status_reason = $reason;
        });
    }
}
