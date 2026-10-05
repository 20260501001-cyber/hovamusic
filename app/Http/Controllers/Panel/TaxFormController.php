<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Finance\TaxForms;
use App\Domain\Finance\Withdrawals;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\SignTaxFormRequest;
use App\Models\TaxForm;
use App\Support\Locale\Countries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaxFormController extends Controller
{
    public function create(Request $request, TaxForms $taxForms, Withdrawals $withdrawals): View
    {
        $user = $request->user();

        return view('panel.finance.tax-form', [
            'type' => $taxForms->typeFor($user),
            'prefill' => $taxForms->prefill($user),
            'current' => $withdrawals->validTaxForm($user),
            'profileComplete' => $user->profile?->isComplete() ?? false,
            'countries' => Countries::options(),
            'chapter3' => collect(TaxForms::CHAPTER3_STATUSES)->mapWithKeys(fn (string $s): array => [$s => __('finance.tax_forms.chapter3.'.$s)])->all(),
            'chapter4' => collect(TaxForms::CHAPTER4_STATUSES)->mapWithKeys(fn (string $s): array => [$s => __('finance.tax_forms.chapter4.'.$s)])->all(),
        ]);
    }

    public function store(SignTaxFormRequest $request, TaxForms $taxForms): RedirectResponse
    {
        $user = $request->user();

        if (! ($user->profile?->isComplete() ?? false)) {
            return redirect()->route('panel.finance.profile')->with('flash', __('finance.tax_form_page.profile_missing'));
        }

        $taxForms->sign($user, $request->formType(), $request->formData(), $request->validated('signed_name'), $request->validated('signer_capacity'), $request);

        return redirect()->route('panel.tax-form.create')->with('flash', __('finance.tax_form_page.signed'));
    }

    public function download(Request $request, TaxForm $taxForm): StreamedResponse
    {
        Gate::authorize('view', $taxForm);
        abort_if($taxForm->pdf_path === null || ! Storage::disk(TaxForms::DISK)->exists($taxForm->pdf_path), 404);

        $name = ($taxForm->form_type->value === 'w8bene' ? 'W-8BEN-E' : 'W-8BEN').'-'.$taxForm->signed_at->format('Y-m-d').'.pdf';

        return Storage::disk(TaxForms::DISK)->download($taxForm->pdf_path, $name, ['Content-Type' => 'application/pdf']);
    }
}
