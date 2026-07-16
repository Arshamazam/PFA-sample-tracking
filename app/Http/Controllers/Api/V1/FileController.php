<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Single controlled entry point for serving private stored files (rapid-test
 * photos, seal photos, witness signatures, report PDFs, ...). Every file in the
 * system is served through here so access is authenticated and path-checked in
 * exactly one place.
 */
class FileController extends Controller
{
    /**
     * Stream a file from the private 'local' disk, guarding against path traversal.
     */
    public function show(Request $request, string $path): StreamedResponse
    {
        // Reject anything that could escape the disk root before touching the FS.
        if ($path === '' || str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, "\0")) {
            abort(404);
        }

        $disk = Storage::disk('local');

        // Defence in depth: confirm the resolved real path stays inside the root.
        $root = realpath($disk->path(''));
        $full = realpath($disk->path($path));
        if ($root === false || $full === false || ! str_starts_with($full, $root)) {
            abort(404);
        }

        if (! $disk->exists($path)) {
            abort(404);
        }

        return $disk->response($path);
    }
}
