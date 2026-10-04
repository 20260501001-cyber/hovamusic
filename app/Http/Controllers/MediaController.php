<?php

namespace App\Http\Controllers;

use App\Models\MediaFile;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

/**
 * Özel diskteki dosyaları yalnızca imzalı, süreli adresle ve sahiplik kontrolüyle verir.
 * İndirme (indir=1) audit log'a yazılır; tarayıcıda dinleme ve kapak önizlemesi yazılmaz.
 */
class MediaController extends Controller
{
    public function __invoke(Request $request, MediaFile $media, AuditLogger $audit): BinaryFileResponse
    {
        Gate::forUser($request->user())->authorize('view', $media);

        $path = $media->absolutePath();
        abort_unless(is_file($path), 404);

        $name = str_replace(['/', '\\', '%'], '_', $media->original_name ?: basename($media->path));
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', Str::ascii($name)) ?: 'dosya';
        $disposition = $request->boolean('indir') ? HeaderUtils::DISPOSITION_ATTACHMENT : HeaderUtils::DISPOSITION_INLINE;

        if ($request->boolean('indir')) {
            $audit->record('media.downloaded', $media, ['file' => $name, 'kind' => $media->kind->value], $request->user());
        }

        return response()->file($path, [
            'Content-Type' => $media->mime,
            'Content-Disposition' => HeaderUtils::makeDisposition($disposition, $name, $fallback),
            'Cache-Control' => 'private, max-age=600',
        ]);
    }
}
