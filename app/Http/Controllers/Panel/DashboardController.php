<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        return view('panel.dashboard', [
            'user' => $user,
            'releases' => $user->releases()->with(['cover', 'artists'])->latest('updated_at')->limit(5)->get(),
        ]);
    }
}
