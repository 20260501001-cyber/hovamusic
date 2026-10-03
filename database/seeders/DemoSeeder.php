<?php

namespace Database\Seeders;

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Yalnızca local ortamda çalışır. Şifreler: "demo-sifre-2026".
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        User::factory()->create([
            'name' => 'Demo Sanatçı',
            'email' => 'sanatci@demo.hovamusic.test',
            'password' => 'demo-sifre-2026',
        ]);

        User::factory()->label()->create([
            'name' => 'Demo Plak Şirketi',
            'email' => 'label@demo.hovamusic.test',
            'password' => 'demo-sifre-2026',
        ]);

        $admin = Admin::query()->firstOrCreate(
            ['email' => 'admin@demo.hovamusic.test'],
            ['name' => 'Demo Admin', 'password' => 'demo-sifre-2026', 'is_active' => true],
        );
        $admin->syncRoles([AdminRole::SuperAdmin->value]);
    }
}
