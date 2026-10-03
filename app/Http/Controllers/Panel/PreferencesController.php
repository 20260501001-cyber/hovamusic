<?php

namespace App\Http\Controllers\Panel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\UpdatePreferencesRequest;
use Illuminate\Http\RedirectResponse;

class PreferencesController extends Controller
{
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back()->with('status', 'preferences-updated');
    }
}
