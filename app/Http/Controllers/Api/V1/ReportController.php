<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * Download the generated report PDF.
     *
     * Allowed: VERIFYING_OFFICER, REGISTRATION_OFFICER, ADMIN, and the FSO who
     * collected the sample. Analysts are deliberately excluded — the report carries
     * the business identity and would breach the blind wall.
     */
    public function show(Request $request, string $blindCode): StreamedResponse
    {
        $part = SamplePart::where('blind_code', $blindCode)
            ->with(['labResult', 'samplingEvent'])
            ->firstOrFail();

        $this->authorizeAccess($request, $part);

        $path = $part->labResult?->report_pdf_path;
        if ($path === null || ! Storage::disk('local')->exists($path)) {
            abort(404, 'The report for this sample has not been generated yet.');
        }

        return Storage::disk('local')->response($path, basename($path), [
            'Content-Type' => 'application/pdf',
        ]);
    }

    private function authorizeAccess(Request $request, SamplePart $part): void
    {
        $user = $request->user();

        $allowedRoles = [
            UserRole::VERIFYING_OFFICER,
            UserRole::REGISTRATION_OFFICER,
            UserRole::ADMIN,
        ];

        if (in_array($user->role, $allowedRoles, true)) {
            return;
        }

        // The collecting FSO may retrieve the report for their own sample.
        if ($user->role === UserRole::FSO && $part->samplingEvent->fso_id === $user->id) {
            return;
        }

        abort(403, 'Your role is not permitted to download this report.');
    }
}
