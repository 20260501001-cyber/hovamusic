<?php

namespace App\Http\Controllers;

use App\Domain\Users\Impersonation;
use App\Filament\Resources\Users\UserResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __invoke(Request $request, Impersonation $impersonation): RedirectResponse
    {
        $log = $impersonation->end($request);

        if ($log !== null && auth('admin')->check()) {
            return redirect()->to(UserResource::getUrl('view', ['record' => $log->user], panel: 'admin'));
        }

        return redirect()->route('home');
    }
}
