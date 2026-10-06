<?php

namespace App\Http\Controllers;

use App\Domain\Legal\LegalDocuments;
use App\Support\Seo\SeoFactory;
use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    public function __invoke(string $slug, LegalDocuments $documents, SeoFactory $seo): View
    {
        $document = $documents->document($slug);

        abort_if($document === null || ! $document->is_public, 404);

        $meta = $seo->page('legal', ['title' => $document->title])->breadcrumb($document->title, route('legal.show', $document->slug));
        $meta->noindex = $meta->noindex || $document->currentVersion === null;

        return view('legal.show', [
            'seo' => $meta,
            'document' => $document,
            'version' => $document->currentVersion,
        ]);
    }
}
