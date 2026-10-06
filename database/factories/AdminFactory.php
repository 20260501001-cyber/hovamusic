<?php

namespace Database\Factories;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<Admin>
 */
class AdminFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
        ];
    }

    public function withRole(AdminRole $role): static
    {
        return $this->afterCreating(function (Admin $admin) use ($role): void {
            Role::findOrCreate($role->value, 'admin');
            $admin->assignRole($role->value);
        });
    }

    /**
     * Uygulama doğrulaması (2FA) kurulmuş admin; panel 2FA olmadan açılmaz.
     */
    public function withMfa(): static
    {
        return $this->state(fn (array $attributes) => ['app_authentication_secret' => 'JBSWY3DPEHPK3PXP']);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
