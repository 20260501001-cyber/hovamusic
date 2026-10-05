{{-- W-8BEN / W-8BEN-E yerine geçen form (substitute form). PDF olarak saklanır. --}}
@php
    $isEntity = $form->form_type === \App\Enums\TaxFormType::W8BenE;
    $v = fn (string $key): string => filled($data[$key] ?? null) ? (string) $data[$key] : '—';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $isEntity ? 'Form W-8BEN-E' : 'Form W-8BEN' }}</title>
<style>
    @page { margin: 28px 34px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: black; line-height: 1.4; }
    h1 { font-size: 15px; margin: 0 0 2px; }
    h2 { font-size: 11px; margin: 14px 0 4px; padding: 3px 6px; background: black; color: white; }
    .muted { color: dimgray; }
    table { width: 100%; border-collapse: collapse; }
    td { border: 1px solid gray; padding: 4px 6px; vertical-align: top; }
    td.label { width: 42%; color: dimgray; }
    .box { border: 1px solid black; padding: 8px; margin-top: 8px; }
    ol { margin: 4px 0 0 16px; padding: 0; }
    li { margin-bottom: 3px; }
</style>
</head>
<body>
    <h1>{{ $isEntity ? 'Form W-8BEN-E (Substitute)' : 'Form W-8BEN (Substitute)' }}</h1>
    <div class="muted">
        @if ($isEntity)
            Certificate of Status of Beneficial Owner for United States Tax Withholding and Reporting (Entities)
        @else
            Certificate of Foreign Status of Beneficial Owner for United States Tax Withholding and Reporting (Individuals)
        @endif
        · Electronically completed through Hova Music · Reference: {{ $form->ulid }}
    </div>

    <h2>Part I — Identification of Beneficial Owner</h2>
    <table>
        @if ($isEntity)
            <tr><td class="label">1 Name of organization that is the beneficial owner</td><td>{{ $v('organization_name') }}</td></tr>
            <tr><td class="label">2 Country of incorporation or organization</td><td>{{ $country($data['incorporation_country'] ?? null) }}</td></tr>
            <tr><td class="label">4 Chapter 3 status (entity type)</td><td>{{ $v('chapter3_status') }}</td></tr>
            <tr><td class="label">5 Chapter 4 status (FATCA status)</td><td>{{ $v('chapter4_status') }}@if (filled($data['chapter4_other'] ?? null)) — {{ $data['chapter4_other'] }}@endif</td></tr>
        @else
            <tr><td class="label">1 Name of individual who is the beneficial owner</td><td>{{ $v('name') }}</td></tr>
            <tr><td class="label">2 Country of citizenship</td><td>{{ $country($data['citizenship'] ?? null) }}</td></tr>
        @endif
        <tr><td class="label">{{ $isEntity ? '6' : '3' }} Permanent residence address</td><td>{{ $v('address') }}<br>{{ $v('city') }}<br>{{ $country($data['country'] ?? null) }}</td></tr>
        <tr><td class="label">{{ $isEntity ? '7' : '4' }} Mailing address (if different)</td><td>{{ $v('mailing_address') }}</td></tr>
        <tr><td class="label">{{ $isEntity ? '8' : '5' }} U.S. taxpayer identification number (if required)</td><td>{{ $v('us_tin') }}</td></tr>
        @if ($isEntity)
            <tr><td class="label">9a GIIN</td><td>{{ $v('giin') }}</td></tr>
        @endif
        <tr><td class="label">{{ $isEntity ? '9b' : '6a' }} Foreign tax identifying number</td><td>{{ $v('foreign_tin') }}</td></tr>
        @unless ($isEntity)
            <tr><td class="label">8 Date of birth (YYYY-MM-DD)</td><td>{{ $v('date_of_birth') }}</td></tr>
        @endunless
    </table>

    <h2>Part II — Claim of Tax Treaty Benefits</h2>
    <table>
        <tr><td class="label">Beneficial owner is a resident of (country) within the meaning of the income tax treaty between the United States and that country</td><td>{{ $country($data['treaty_country'] ?? null) }}</td></tr>
        <tr><td class="label">Special rates and conditions: article, rate of withholding, type of income</td><td>{{ $v('treaty_article') }} · {{ $v('treaty_rate') }} · {{ $v('treaty_income_type') }}</td></tr>
    </table>

    <h2>Part {{ $isEntity ? 'XXX' : 'III' }} — Certification</h2>
    <div>Under penalties of perjury, I declare that I have examined the information on this form and to the best of my knowledge and belief it is true, correct, and complete. I further certify under penalties of perjury that:</div>
    <ol>
        @if ($isEntity)
            <li>The entity identified on line 1 of this form is the beneficial owner of all the income or proceeds to which this form relates, is using this form to certify its status for chapter 4 purposes, or is submitting this form for purposes of section 6050W or 6050Y;</li>
            <li>The entity identified on line 1 of this form is not a U.S. person;</li>
        @else
            <li>I am the individual that is the beneficial owner (or am authorized to sign for the individual that is the beneficial owner) of all the income or proceeds to which this form relates or am using this form to document myself for chapter 4 purposes;</li>
            <li>The person named on line 1 of this form is not a U.S. person;</li>
        @endif
        <li>This form relates to income not effectively connected with the conduct of a trade or business in the United States, or effectively connected income that is not subject to tax under an applicable income tax treaty;</li>
        <li>For broker transactions or barter exchanges, the beneficial owner is an exempt foreign person as defined in the instructions.</li>
    </ol>
    <div style="margin-top:4px">I agree that I will submit a new form within 30 days if any certification made on this form becomes incorrect.</div>

    <div class="box">
        <table>
            <tr><td class="label">Signature (electronic)</td><td>/s/ {{ $form->signed_name }}</td></tr>
            @if ($form->signer_capacity)
                <tr><td class="label">Capacity in which acting</td><td>{{ $form->signer_capacity }}</td></tr>
            @endif
            <tr><td class="label">Date (UTC)</td><td>{{ $form->signed_at->utc()->format('Y-m-d H:i:s') }}</td></tr>
            <tr><td class="label">Signed from IP address</td><td>{{ $form->ip_address }}</td></tr>
            <tr><td class="label">Account</td><td>{{ $user->ulid }}</td></tr>
            <tr><td class="label">Valid until</td><td>{{ $form->expires_at?->format('Y-m-d') }}</td></tr>
        </table>
    </div>
</body>
</html>
