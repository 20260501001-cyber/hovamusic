<?php

use App\Domain\Users\Impersonation;
use App\Enums\AdminRole;
use App\Enums\ReleaseStatus;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\RelationManagers\ReleasesRelationManager;
use App\Filament\Resources\Users\UserResource;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\ImpersonationLog;
use App\Models\Release;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = Admin::factory()->withRole(AdminRole::SuperAdmin)->create();
});

it('lists users with their releases for super admins only', function () {
    $user = User::factory()->create(['name' => 'Deniz Yılmaz']);
    $release = Release::factory()->for($user)->status(ReleaseStatus::Live)->create();

    $this->actingAs($this->admin, 'admin');

    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$user]);
    Livewire::test(ViewUser::class, ['record' => $user->ulid])->assertOk()->assertSee('Deniz Yılmaz');
    Livewire::test(ReleasesRelationManager::class, ['ownerRecord' => $user, 'pageClass' => ViewUser::class])
        ->assertCanSeeTableRecords([$release]);

    $this->actingAs(Admin::factory()->withRole(AdminRole::ReviewEditor)->create(), 'admin');
    expect(UserResource::canViewAny())->toBeFalse();
    Livewire::test(ListUsers::class)->assertForbidden();
});

it('suspends and bans only with a reason and signs the user out', function () {
    $user = User::factory()->create();
    $this->actingAs($this->admin, 'admin');

    Livewire::test(ViewUser::class, ['record' => $user->ulid])
        ->callAction('suspend', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required'])
        ->fillForm(['reason' => 'Ödeme itirazı inceleniyor.'])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($user->fresh())
        ->status->toBe(UserStatus::Suspended)
        ->status_reason->toBe('Ödeme itirazı inceleniyor.');
    expect(AuditLog::query()->where('action', 'user.status_changed')->sole()->changes['reason'])->toBe('Ödeme itirazı inceleniyor.');

    $this->actingAs($user->fresh())->get(route('panel.dashboard'))->assertRedirect(route('login'));

    $this->actingAs($this->admin, 'admin');
    Livewire::test(ViewUser::class, ['record' => $user->ulid])
        ->callAction('ban', data: ['reason' => 'Telif ihlali tekrarlandı.']);

    expect($user->fresh()->status)->toBe(UserStatus::Banned);
});

it('starts a read-only impersonation with a reason and logs start and end', function () {
    $user = User::factory()->create(['last_login_at' => null]);
    $release = Release::factory()->for($user)->create();
    $this->actingAs($this->admin, 'admin');

    Livewire::test(ViewUser::class, ['record' => $user->ulid])
        ->callAction('impersonate', data: ['reason' => ''])
        ->assertHasActionErrors(['reason' => 'required']);

    $log = app(Impersonation::class)->start($this->admin, $user, 'Destek talebi #42: yayın görünmüyor.', request());

    expect($log->reason)->toBe('Destek talebi #42: yayın görünmüyor.')
        ->and($log->started_at)->not->toBeNull()
        ->and($log->ended_at)->toBeNull()
        ->and($user->fresh()->last_login_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'user.impersonation_started')->exists())->toBeTrue();

    $this->get(route('panel.releases.show', $release))
        ->assertOk()
        ->assertSee('Görüntüleme modu')
        ->assertSee('Görüntülemeyi bitir');

    $this->post(route('panel.releases.store'))->assertForbidden();
    $this->delete(route('panel.releases.destroy', $release))->assertForbidden();
    $this->get(route('panel.releases.edit', ['release' => $release->ulid, 'step' => 1]))->assertRedirect(route('panel.releases.show', $release));
    expect(Release::query()->whereKey($release->id)->exists())->toBeTrue()
        ->and($user->releases()->count())->toBe(1);

    $this->post(route('impersonation.end'))->assertRedirect(UserResource::getUrl('view', ['record' => $user], panel: 'admin'));

    expect($log->fresh()->ended_at)->not->toBeNull()
        ->and(auth('web')->check())->toBeFalse()
        ->and(auth('admin')->id())->toBe($this->admin->id)
        ->and(AuditLog::query()->where('action', 'user.impersonation_ended')->exists())->toBeTrue();
});

it('ends the impersonation instead of logging the admin out', function () {
    $user = User::factory()->create();
    $this->actingAs($this->admin, 'admin');
    $log = app(Impersonation::class)->start($this->admin, $user, 'Kontrol', request());

    $this->post(route('logout'))->assertRedirect();

    expect($log->fresh()->ended_at)->not->toBeNull()
        ->and(auth('admin')->check())->toBeTrue();
});

it('does not let review editors impersonate', function () {
    $user = User::factory()->create();
    $editor = Admin::factory()->withRole(AdminRole::ReviewEditor)->create();

    expect($editor->can('impersonate', $user))->toBeFalse()
        ->and($this->admin->can('impersonate', $user))->toBeTrue()
        ->and(fn () => app(Impersonation::class)->start($editor, $user, 'Deneme', request()))
        ->toThrow(AuthorizationException::class);

    expect(ImpersonationLog::query()->count())->toBe(0);
});
