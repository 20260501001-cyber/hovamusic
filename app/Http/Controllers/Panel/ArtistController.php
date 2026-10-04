<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

class ArtistController extends Controller
{
    public function __invoke(): View
    {
        return view('panel.artists.index');
    }
}
