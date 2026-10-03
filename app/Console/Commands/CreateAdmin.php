<?php

namespace App\Console\Commands;

use App\Enums\AdminRole;
use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    protected $signature = 'hova:admin-create';

    protected $description = 'Yeni bir admin hesabı oluşturur. İlk girişte iki adımlı doğrulama kurulumu zorunludur.';

    public function handle(): int
    {
        $name = text('Ad soyad', required: true);
        $email = text('E-posta', required: true, validate: fn (string $value) => Validator::make(
            ['email' => $value],
            ['email' => ['email:rfc', 'unique:admins,email']],
        )->errors()->first('email') ?: null);
        $secret = password('Şifre', required: true, validate: fn (string $value) => Validator::make(
            ['password' => $value],
            ['password' => [Password::default()]],
        )->errors()->first('password') ?: null);
        $role = select('Rol', collect(AdminRole::cases())->mapWithKeys(fn (AdminRole $r) => [$r->value => $r->label()])->all(), default: AdminRole::SuperAdmin->value);

        Role::findOrCreate($role, 'admin');

        $admin = Admin::create([
            'name' => $name,
            'email' => strtolower($email),
            'password' => $secret,
            'is_active' => true,
        ]);
        $admin->assignRole($role);

        $this->components->info("Admin oluşturuldu: {$admin->email}");

        return self::SUCCESS;
    }
}
