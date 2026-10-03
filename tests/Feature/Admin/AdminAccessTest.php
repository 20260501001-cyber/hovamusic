<?php

use App\Enums\AdminRole;
use App\Models\Admin;
use App\Models\AdminAllowedIp;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Events\Login;

beforeEach(function () {
    $this->adminPath = '/'.trim(config('hova.admin.path'), '/');
});

it('serves the admin login only under the configured path', function () {
    $this->get($this->adminPath.'/login')->assertOk();
    $this->get('/admin/login')->assertNotFound();
});

it('does not let a regular user into the admin panel', function () {
    $this->actingAs(User::factory()->create())
        ->get($this->adminPath)
        ->assertRedirect($this->adminPath.'/login');
});

it('grants panel access only to active admins with a role', function () {
    $panel = Filament::getPanel('admin');

    expect(Admin::factory()->withRole(AdminRole::Finance)->create()->canAccessPanel($panel))->toBeTrue()
        ->and(Admin::factory()->create()->canAccessPanel($panel))->toBeFalse()
        ->and(Admin::factory()->inactive()->withRole(AdminRole::SuperAdmin)->create()->canAccessPanel($panel))->toBeFalse();
});

it('hides the admin panel from IPs outside the allow list', function () {
    config(['hova.admin.allowed_ips' => ['10.20.30.40']]);

    $this->get($this->adminPath.'/login')->assertNotFound();

    $this->withServerVariables(['REMOTE_ADDR' => '10.20.30.40'])
        ->get($this->adminPath.'/login')
        ->assertOk();
});

it('applies IP ranges stored in the database', function () {
    AdminAllowedIp::create(['cidr' => '192.168.10.0/24']);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.77'])->get($this->adminPath.'/login')->assertOk();
    $this->withServerVariables(['REMOTE_ADDR' => '192.168.11.1'])->get($this->adminPath.'/login')->assertNotFound();
});

it('writes an audit entry when an admin signs in', function () {
    $admin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();

    event(new Login('admin', $admin, false));

    expect(AuditLog::query()->where('action', 'admin.login')->where('actor_id', $admin->id)->exists())->toBeTrue();
});

it('audits admin changes made by another admin without leaking the password', function () {
    $actor = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
    $this->actingAs($actor, 'admin');

    $target = Admin::factory()->create();
    $target->update(['name' => 'Yeni İsim', 'password' => 'baska-guclu-sifre-9']);

    $log = AuditLog::query()->where('action', 'admins.updated')->latest('id')->firstOrFail();

    expect($log->changes['name']['new'])->toBe('Yeni İsim')
        ->and($log->changes['password'])->toBe('[gizli]');
});
