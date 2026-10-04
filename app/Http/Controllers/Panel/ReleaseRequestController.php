<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Releases\ReleaseRequests;
use App\Domain\Releases\RequestNotAllowed;
use App\Enums\RequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreReleaseRequestRequest;
use App\Models\Release;
use Illuminate\Http\RedirectResponse;

class ReleaseRequestController extends Controller
{
    public function store(StoreReleaseRequestRequest $request, Release $release, ReleaseRequests $requests): RedirectResponse
    {
        $type = RequestType::from($request->validated('type'));

        try {
            $requests->open($release, $request->user(), $type, $request->validated('message'));
        } catch (RequestNotAllowed $e) {
            return back()->withErrors(['type' => $e->getMessage()], 'request')->withInput();
        }

        return redirect()
            ->route('panel.releases.show', $release)
            ->with('flash', __('release.requests.sent.'.$type->value));
    }
}
