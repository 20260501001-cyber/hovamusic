<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Privacy\PrivacyRequests;
use App\Enums\DataRequestStatus;
use App\Enums\DataRequestType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StoreDataRequestRequest;
use App\Models\DataRequest;
use App\Notifications\DataRequestCompleted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivacyController extends Controller
{
    public function store(StoreDataRequestRequest $request, PrivacyRequests $requests): RedirectResponse
    {
        $type = DataRequestType::from($request->validated('type'));

        try {
            $requests->open($request->user(), $type, $request->validated('message'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['type' => $e->getMessage()], 'privacy');
        }

        return redirect()->to(route('panel.account').'#veriler')->with('flash', __('privacy.sent.'.$type->value));
    }

    public function download(Request $request, DataRequest $dataRequest): StreamedResponse
    {
        abort_unless(
            $dataRequest->user_id === $request->user()->id
            && $dataRequest->type === DataRequestType::Export
            && $dataRequest->status === DataRequestStatus::Completed
            && $dataRequest->export_path !== null
            && $dataRequest->completed_at?->greaterThan(now()->subDays(DataRequestCompleted::DOWNLOAD_DAYS)),
            404,
        );

        return Storage::disk('private')->download($dataRequest->export_path, 'hova-music-verilerim.json', [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
