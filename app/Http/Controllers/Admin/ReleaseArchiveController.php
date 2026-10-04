<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Delivery\ReleaseArchive;
use App\Http\Controllers\Controller;
use App\Models\Release;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "Tümünü indir": imzalı ve süreli adresle, yalnızca inceleme yetkisi olan admin.
 * Her indirme audit log'a yazılır.
 */
class ReleaseArchiveController extends Controller
{
    public function __invoke(Request $request, Release $release, AuditLogger $audit): StreamedResponse
    {
        $admin = $request->user('admin');
        Gate::forUser($admin)->authorize('download', $release);

        $archive = new ReleaseArchive($release);

        $audit->record('release.downloaded', $release, [
            'file' => $archive->fileName(),
            'tracks' => $release->tracks->count(),
        ], $admin);

        return response()->streamDownload(function () use ($archive): void {
            @set_time_limit(0);
            $archive->stream();
        }, $archive->fileName(), [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'private, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
