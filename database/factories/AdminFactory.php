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
        ];
    }

    public function withRole(AdminRole $role): static
    {
        return $this->afterCreating(function (Admin $admin) use ($role): void {
            Role::findOrCreate($role->value, 'admin');
            $admin->assignRole($role->value);
        });
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
