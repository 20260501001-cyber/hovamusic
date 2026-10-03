<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (AdminRole::cases() as $role) {
            Role::findOrCreate($role->value, 'admin');
        }
    }
}
