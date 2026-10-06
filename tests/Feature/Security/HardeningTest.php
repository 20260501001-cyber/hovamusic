<?php

use App\Http\Middleware\ThrottleFormSubmissions;
use App\Logging\MaskSensitiveData;
use App\Models\Release;
use App\Models\User;
use Illuminate\Log\Logger;
use Monolog\Handler\TestHandler;
use Monolog\Logger as Monolog;

it('limits form submissions per user across the whole site', function () {
    $user = User::factory()->create();

    $data = ['theme' => 'dark', 'display_currency' => 'USD'];

    foreach (range(1, ThrottleFormSubmissions::PER_MINUTE) as $i) {
        $this->actingAs($user)->put(route('panel.account.preferences'), $data)->assertRedirect();
    }

    $this->actingAs($user)->put(route('panel.account.preferences'), $data)->assertStatus(429);
    $this->actingAs(User::factory()->create())->put(route('panel.account.preferences'), $data)->assertRedirect();
});

it('masks secrets and account numbers in log records', function () {
    $handler = new TestHandler;
    $logger = new Logger(new Monolog('test', [$handler]));
    (new MaskSensitiveData)($logger);

    $logger->warning('Ödeme TR330006100519786457841326 hesabına gönderilemedi', [
        'iban' => 'TR330006100519786457841326',
        'password' => 'gizli-sifre',
        'nested' => ['access_token' => 'tok_123', 'note' => 'IBAN DE89370400440532013000'],
        'amount' => '25.00',
    ]);

    $record = $handler->getRecords()[0];

    expect($record->message)->toBe('Ödeme TR33**** hesabına gönderilemedi')
        ->and($record->context['iban'])->toBe('***')
        ->and($record->context['password'])->toBe('***')
        ->and($record->context['nested']['access_token'])->toBe('***')
        ->and($record->context['nested']['note'])->toBe('IBAN DE89****')
        ->and($record->context['amount'])->toBe('25.00');
});

it('ignores ownership fields sent with a form (mass assignment)', function () {
    $owner = User::factory()->create();
    $attacker = User::factory()->create();
    activePlan($attacker);

    $this->actingAs($attacker)->post(route('panel.releases.store'), [
        'user_id' => $owner->id,
        'status' => 'live',
        'type' => 'single',
    ]);

    $release = Release::query()->latest('id')->first();

    expect($release?->user_id)->toBe($attacker->id)
        ->and($release?->status->value)->toBe('draft');

    $this->actingAs($attacker)->put(route('panel.account.preferences'), [
        'theme' => 'dark',
        'display_currency' => 'EUR',
        'email' => 'ele-gecirildi@example.com',
        'status' => 'banned',
        'account_type' => 'label',
    ])->assertRedirect();

    $attacker->refresh();

    expect($attacker->email)->not->toBe('ele-gecirildi@example.com')
        ->and($attacker->display_currency?->value)->toBe('EUR')
        ->and($attacker->account_type->value)->toBe('artist')
        ->and($attacker->canSignIn())->toBeTrue();
});

it('does not reveal another user\'s release by sequential id or ulid', function () {
    $release = Release::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('panel.releases.show', $release))
        ->assertForbidden();

    $this->actingAs($release->user)
        ->get('/panel/yayinlar/'.$release->id)
        ->assertNotFound();
});
