<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\TaxForms;
use App\Http\Controllers\Controller;
use App\Models\TaxForm;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Vergi formu PDF'i: imzalı ve süreli adresle, yalnızca finans yetkisi olan admin.
 */
class TaxFormDownloadController extends Controller
{
    public function __invoke(Request $request, TaxForm $taxForm, AuditLogger $audit): StreamedResponse
    {
        $admin = $request->user('admin');
        Gate::forUser($admin)->authorize('view', $taxForm);
        abort_if($taxForm->pdf_path === null || ! Storage::disk(TaxForms::DISK)->exists($taxForm->pdf_path), 404);

        $audit->record('tax_form.downloaded', $taxForm, actor: $admin);

        return Storage::disk(TaxForms::DISK)->download($taxForm->pdf_path, $taxForm->form_type->value.'-'.$taxForm->ulid.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
