<?php

namespace App\Domain\Finance;

use App\Enums\EntityType;
use App\Enums\TaxFormType;
use App\Models\TaxForm;
use App\Models\User;
use App\Support\Locale\Countries;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * W-8BEN (bireysel) ve W-8BEN-E (şirket) formu: profil bilgileriyle doldurulur,
 * kullanıcı adını yazıp beyanı onaylayarak elektronik olarak imzalar; form PDF
 * olarak saklanır. Yeni form öncekini geçersiz kılar. Form, imza yılından sonraki
 * üçüncü takvim yılının sonuna kadar geçerlidir.
 */
class TaxForms
{
    public const DISK = 'private';

    /**
     * W-8BEN-E Bölüm I satır 4 (Chapter 3 statüsü) için temel seçenekler.
     *
     * @var list<string>
     */
    public const CHAPTER3_STATUSES = ['corporation', 'partnership', 'disregarded_entity', 'simple_trust', 'grantor_trust', 'complex_trust', 'estate', 'foreign_government', 'tax_exempt_organization', 'private_foundation', 'international_organization'];

    /**
     * W-8BEN-E Bölüm I satır 5 (Chapter 4 / FATCA statüsü) için temel seçenekler.
     *
     * @var list<string>
     */
    public const CHAPTER4_STATUSES = ['active_nffe', 'passive_nffe', 'publicly_traded_nffe', 'exempt_beneficial_owner', 'nonreporting_iga_ffi', 'other'];

    public function typeFor(User $user): TaxFormType
    {
        return ($user->profile?->entity_type ?? EntityType::Individual)->taxForm();
    }

    /**
     * Profil bilgilerinden ön doldurma.
     *
     * @return array<string, string|null>
     */
    public function prefill(User $user): array
    {
        $profile = $user->profile;

        return [
            'name' => $profile?->legal_name ?? $user->name,
            'organization_name' => $profile?->company_name,
            'citizenship' => $profile?->citizenship ?? $profile?->country,
            'incorporation_country' => $profile?->country,
            'address' => $profile?->address_line,
            'city' => trim(($profile?->city ?? '').' '.($profile?->postal_code ?? '')) ?: null,
            'country' => $profile?->country,
            'foreign_tin' => $profile?->tax_id,
            'date_of_birth' => $profile?->date_of_birth,
            'treaty_country' => $profile?->country,
        ];
    }

    /**
     * @param  array<string, mixed>  $data  Doğrulanmış form alanları
     */
    public function sign(User $user, TaxFormType $type, array $data, string $signedName, ?string $capacity, Request $request): TaxForm
    {
        $signedAt = now();

        $form = new TaxForm([
            'form_type' => $type,
            'data' => $data,
            'signed_name' => trim($signedName),
            'signer_capacity' => $capacity !== null ? trim($capacity) : null,
            'signed_at' => $signedAt,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'status' => 'valid',
            'expires_at' => $signedAt->copy()->addYears(3)->endOfYear()->toDateString(),
        ]);
        $form->user()->associate($user);

        $pdf = $this->render($form, $user);
        $form->save();

        $path = "tax-forms/{$user->ulid}/{$form->ulid}.pdf";
        Storage::disk(self::DISK)->put($path, $pdf);

        DB::transaction(function () use ($user, $form, $path, $pdf): void {
            $user->taxForms()->whereKeyNot($form->id)->where('status', 'valid')->update(['status' => 'superseded']);
            $form->forceFill(['pdf_path' => $path, 'pdf_sha256' => hash('sha256', $pdf)])->save();
        });

        return $form;
    }

    public function render(TaxForm $form, User $user): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $options->setChroot(resource_path('views/pdf'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml(view('pdf.tax-form', [
            'form' => $form,
            'data' => $form->data,
            'user' => $user,
            'country' => fn (?string $code): string => $code ? Countries::name($code).' ('.$code.')' : '—',
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
