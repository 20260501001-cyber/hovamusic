<?php

namespace App\Http\Controllers;

use App\Domain\Legal\LegalDocuments;
use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    public function __invoke(string $slug, LegalDocuments $documents): View
    {
        $document = $documents->document($slug);

        abort_if($document === null || ! $document->is_public, 404);

        return view('legal.show', [
            'document' => $document,
            'version' => $document->currentVersion,
        ]);
    }
}
