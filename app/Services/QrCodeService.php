<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use Illuminate\Support\Facades\Log;

/**
 * Renders a QR code as inline SVG with no external requests: codes appear on
 * the pre-auth portal (no internet — a remote image would hang and read as
 * "the Wi-Fi is broken") and on printed slips. SVG needs no imagick/gd, scales
 * for phones and thermal printers, and embeds with no second request.
 */
class QrCodeService
{
    /**
     * @param  int  $size  Rendered width/height in pixels.
     * @param  int  $margin  Quiet-zone width in modules. Below 2 many scanners
     *                       simply fail to see the code at all, so this is not
     *                       a purely cosmetic border.
     */
    public function svg(string $text, int $size = 160, int $margin = 2): string
    {
        try {
            $matrix = Encoder::encode($text, ErrorCorrectionLevel::M())->getMatrix();
        } catch (\Throwable $e) {
            // A QR is always an alternative route to something the guest can
            // still reach another way, so a failure here must never take a page
            // down with it.
            Log::warning('QrCodeService: could not encode value.', ['error' => $e->getMessage()]);

            return '';
        }

        $modules = $matrix->getWidth();
        $total = $modules + ($margin * 2);

        $rects = '';
        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    // One rect per dark module. Verbose, but it renders
                    // identically everywhere and needs no path arithmetic.
                    $rects .= '<rect x="'.($x + $margin).'" y="'.($y + $margin).'" width="1" height="1"/>';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" '
            .'viewBox="0 0 '.$total.' '.$total.'" shape-rendering="crispEdges" role="img" aria-hidden="true">'
            .'<rect width="'.$total.'" height="'.$total.'" fill="#ffffff"/>'
            .'<g fill="#000000">'.$rects.'</g>'
            .'</svg>';
    }
}
