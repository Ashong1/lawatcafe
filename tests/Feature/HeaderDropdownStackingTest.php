<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The header's dropdowns (notifications, profile) paint over the page. The
 * header and page share one stacking context, so the header's z-index must be
 * higher than any layer used inside page content: on an equal z-index the page
 * wins because it comes later, which is how dashboard cards (relative z-10)
 * showed through the notifications panel.
 */
class HeaderDropdownStackingTest extends TestCase
{
    private const DROPDOWN_PANELS = [
        'components/notification-bell.blade.php',
        'components/agent-pending-badge.blade.php',
        'components/dropdown.blade.php',
    ];

    private function headerZ(string $layout): int
    {
        $html = file_get_contents(resource_path("views/layouts/{$layout}.blade.php"));
        preg_match('/<header class="[^"]*\bz-(\d+)\b/', $html, $m);

        return (int) ($m[1] ?? 0);
    }

    public function test_the_header_sits_above_every_in_page_layer(): void
    {
        $highestInPage = 0;
        foreach (File::allFiles(resource_path('views')) as $file) {
            // The layouts, and the dropdown panels themselves, which open from the header.
            $relative = str_replace('\\', '/', $file->getRelativePathname());
            if (str_starts_with($relative, 'layouts/') || in_array($relative, self::DROPDOWN_PANELS, true)) {
                continue;
            }
            foreach (file($file->getPathname()) as $line) {
                // Fixed bars and modals are meant to cover the header.
                if (str_contains($line, 'fixed') || ! preg_match_all('/(?<![\w:-])z-(\d+)\b/', $line, $m)) {
                    continue;
                }
                $highestInPage = max($highestInPage, ...array_map('intval', $m[1]));
            }
        }

        foreach (['admin', 'staff'] as $layout) {
            $this->assertGreaterThan($highestInPage, $this->headerZ($layout), "{$layout} header must outrank page content (highest in-page z-{$highestInPage})");
            $this->assertLessThan(40, $this->headerZ($layout), "{$layout} header must stay under bars (z-40) and modals (z-50)");
        }
    }
}
