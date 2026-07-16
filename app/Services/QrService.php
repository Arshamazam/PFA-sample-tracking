<?php

namespace App\Services;

use App\Models\SamplePart;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Generates QR codes for sample parts. Uses bacon/bacon-qr-code's pure-PHP SVG
 * backend (no GD/imagick), which is safe on shared hosting.
 *
 * The QR encodes the public tracking URL for the part's qr_token. That public
 * route is implemented in Phase 6, but the URL is stable and encoded now so that
 * physical labels printed today remain valid.
 */
class QrService
{
    /**
     * The public tracking URL encoded in a part's QR code.
     */
    public function trackingUrl(SamplePart $part): string
    {
        return rtrim(config('app.url'), '/').'/track/p/'.$part->qr_token;
    }

    /**
     * Render the part's QR code as an SVG string.
     */
    public function svg(SamplePart $part, int $size = 256): string
    {
        $writer = new Writer(
            new ImageRenderer(
                new RendererStyle($size, 1),
                new SvgImageBackEnd(),
            )
        );

        return $writer->writeString($this->trackingUrl($part));
    }

    /**
     * The QR as a base64 SVG data URI, for embedding in an <img> (e.g. the report
     * PDF, where dompdf renders SVG images via svg-lib).
     */
    public function svgDataUri(SamplePart $part, int $size = 256): string
    {
        return 'data:image/svg+xml;base64,'.base64_encode($this->svg($part, $size));
    }
}
