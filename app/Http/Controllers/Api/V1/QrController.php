<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SamplePart;
use App\Services\QrService;
use Illuminate\Http\Response;

class QrController extends Controller
{
    public function __construct(private readonly QrService $qr)
    {
    }

    /**
     * Return the part's QR code as SVG (image/svg+xml).
     */
    public function show(SamplePart $samplePart): Response
    {
        return response($this->qr->svg($samplePart), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
