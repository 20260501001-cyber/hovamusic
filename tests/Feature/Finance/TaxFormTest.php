<?php

use App\Domain\Finance\Withdrawals;
use App\Enums\AdminRole;
use App\Enums\TaxFormType;
use App\Models\Admin;
use App\Models\TaxForm;
use App\Models\User;
use App\Support\Admin\AdminUrls;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('private');
    $this->user = User::factory()->create();
    $this->user->profile()->create([
        'entity_type' => 'individual',
        'legal_name' => 'Deniz Yılmaz',
        'country' => 'TR',
        'citizenship' => 'TR',
        'address_line' => 'Test Mah. 1',
        'city' => 'İstanbul',
        'postal_code' => '34000',
        'tax_id' => '12345678901',
        'date_of_birth' => '1990-01-01',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function w8benData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Deniz Yılmaz',
        'citizenship' => 'tr',
        'date_of_birth' => '1990-01-01',
        'address' => 'Test Mah. 1',
        'city' => 'İstanbul 34000',
        'country' => 'TR',
        'foreign_tin' => '12345678901',
        'treaty_country' => 'TR',
        'signed_name' => '  deniz   yılmaz ',
        'certify' => '1',
        'esign' => '1',
    ], $overrides);
}

it('signs a W-8BEN, stores the PDF privately and makes it the valid form', function () {
    $this->actingAs($this->user)
        ->post(route('panel.tax-form.store'), w8benData())
        ->assertRedirect(route('panel.tax-form.create'))
        ->assertSessionHasNoErrors();

    $form = TaxForm::query()->sole();

    expect($form->form_type)->toBe(TaxFormType::W8Ben)
        ->and($form->status)->toBe('valid')
        ->and($form->data['citizenship'])->toBe('TR')
        ->and($form->ip_address)->not->toBeNull()
        ->and($form->expires_at->toDateString())->toBe(now()->addYears(3)->endOfYear()->toDateString())
        ->and(Storage::disk('private')->get($form->pdf_path))->toStartWith('%PDF')
        ->and(hash('sha256', Storage::disk('private')->get($form->pdf_path)))->toBe($form->pdf_sha256)
        ->and(app(Withdrawals::class)->validTaxForm($this->user)?->is($form))->toBeTrue();
});

it('requires the signature to match the name on the form', function () {
    $this->actingAs($this->user)
        ->post(route('panel.tax-form.store'), w8benData(['signed_name' => 'Başka Biri']))
        ->assertSessionHasErrors('signed_name');

    $this->actingAs($this->user)
        ->post(route('panel.tax-form.store'), w8benData(['certify' => null]))
        ->assertSessionHasErrors('certify');

    expect(TaxForm::query()->count())->toBe(0);
});

it('supersedes the previous form when a new one is signed', function () {
    $this->actingAs($this->user)->post(route('panel.tax-form.store'), w8benData());
    $this->actingAs($this->user)->post(route('panel.tax-form.store'), w8benData(['city' => 'Ankara']));

    $forms = TaxForm::query()->orderBy('id')->get();

    expect($forms->pluck('status')->all())->toBe(['superseded', 'valid'])
        ->and(app(Withdrawals::class)->validTaxForm($this->user)?->data['city'])->toBe('Ankara');
});

it('lets only the owner and finance admins download the PDF', function () {
    $this->actingAs($this->user)->post(route('panel.tax-form.store'), w8benData());
    $form = TaxForm::query()->sole();

    $this->actingAs($this->user)
        ->get(route('panel.tax-form.download', $form))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    $this->actingAs(User::factory()->create())
        ->get(route('panel.tax-form.download', $form))
        ->assertForbidden();

    $this->actingAs(Admin::factory()->withRole(AdminRole::Finance)->create(), 'admin')
        ->get(AdminUrls::taxForm($form))
        ->assertOk();

    $this->actingAs(Admin::factory()->withRole(AdminRole::ReviewEditor)->create(), 'admin')
        ->get(AdminUrls::taxForm($form))
        ->assertForbidden();
});

it('never exposes sequential ids in tax form links', function () {
    $this->actingAs($this->user)->post(route('panel.tax-form.store'), w8benData());
    $form = TaxForm::query()->sole();

    expect(route('panel.tax-form.download', $form))->toContain($form->ulid);

    $this->actingAs($this->user)->get('/panel/hesap/vergi-formu/'.$form->id.'/pdf')->assertNotFound();
});

it('asks for billing details before a form can be signed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('panel.tax-form.store'), w8benData())
        ->assertRedirect(route('panel.finance.profile'));

    expect(TaxForm::query()->count())->toBe(0);
});
