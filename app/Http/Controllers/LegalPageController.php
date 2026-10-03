<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class LegalPageController extends Controller
{
    public function __invoke(string $slug): View
    {
        $title = config("hova.legal_pages.{$slug}");

        abort_if($title === null, 404);

        return view('legal.show', [
            'slug' => $slug,
            'title' => $title,
            'body' => view()->exists("legal.pages.{$slug}") ? view("legal.pages.{$slug}") : null,
        ]);
    }
}
