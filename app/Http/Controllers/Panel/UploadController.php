<?php

namespace App\Http\Controllers\Panel;

use App\Domain\Media\ChunkedUploads;
use App\Domain\Media\OffsetMismatch;
use App\Domain\Media\UploadRejected;
use App\Domain\Plans\PlanGate;
use App\Enums\UploadStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Panel\StartUploadRequest;
use App\Models\UploadSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Ses dosyalarının parça parça (yarıda kalırsa kaldığı yerden) yüklenmesi.
 * İstemci her parçayı Upload-Offset başlığıyla gönderir; sunucunun beklediği
 * konum farklıysa 409 ile doğru konumu döner.
 */
class UploadController extends Controller
{
    public function __construct(private readonly ChunkedUploads $uploads) {}

    public function store(StartUploadRequest $request, PlanGate $plans): JsonResponse
    {
        $gate = $plans->canUpload($request->user());

        if (! $gate->allowed) {
            return response()->json(['message' => $gate->reason, 'plans_url' => route('panel.plans.index')], 422);
        }

        try {
            $session = $this->uploads->start(
                $request->user(),
                $request->track(),
                $request->string('name')->toString(),
                $request->integer('size'),
                $request->string('fingerprint')->toString(),
            );
        } catch (UploadRejected $rejected) {
            return response()->json(['message' => $rejected->getMessage()], 422);
        }

        return response()->json($this->state($session), $session->wasRecentlyCreated ? 201 : 200);
    }

    public function show(UploadSession $upload): JsonResponse
    {
        Gate::authorize('update', $upload);

        return response()->json($this->state($upload));
    }

    public function update(Request $request, UploadSession $upload): JsonResponse
    {
        Gate::authorize('update', $upload);
        $upload->loadMissing('track.release');

        if ($upload->track === null || ! $request->user()->can('update', $upload->track->release)) {
            abort(403);
        }

        $offset = $request->header('Upload-Offset');

        if (! is_string($offset) || ! ctype_digit($offset)) {
            return response()->json(['message' => __('media.upload.bad_chunk')], 422);
        }

        try {
            $session = $this->uploads->append($upload, (int) $offset, $request->getContent(true));
        } catch (OffsetMismatch $mismatch) {
            return response()->json(['offset' => $mismatch->received, 'complete' => false], 409);
        } catch (UploadRejected $rejected) {
            return response()->json(['message' => $rejected->getMessage()], 422);
        }

        return response()->json($this->state($session));
    }

    public function destroy(UploadSession $upload): Response
    {
        Gate::authorize('update', $upload);

        if ($upload->status === UploadStatus::Uploading) {
            $this->uploads->cancel($upload);
        }

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function state(UploadSession $session): array
    {
        return [
            'id' => $session->ulid,
            'url' => route('panel.uploads.update', $session),
            'offset' => $session->received_bytes,
            'size' => $session->total_size,
            'chunk_size' => ChunkedUploads::CHUNK_SIZE,
            'complete' => $session->status === UploadStatus::Completed,
            'status' => $session->status->value,
        ];
    }
}
