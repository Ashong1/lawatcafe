<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * System-wide polish pass (2026-09-28, design-critique + accessibility-review):
 * 7-11px uppercase labels, faux-bold font-black (900 isn't bundled), and
 * #8D6E63 text (4.3-4.6:1) were on nearly every page. These pin the result.
 */
class UiReadabilityFloorTest extends TestCase
{
    /** Printed on a 58mm thermal roll — small type is correct there. */
    private const PRINT_VIEWS = ['network/print-voucher', 'network/print-vouchers-batch', 'pos/receipt'];

    /** @return array<string, string> view name => markup without Blade comments */
    private function views(): array
    {
        $views = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            $path = $file->getPathname();
            if (! str_ends_with($path, '.blade.php') || str_contains($path, '/vendor/')) {
                continue;
            }
            $name = str_replace([resource_path('views').'/', '.blade.php'], '', $path);
            if (! in_array($name, self::PRINT_VIEWS, true)) {
                $views[$name] = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($path));
            }
        }

        return $views;
    }

    public function test_no_screen_text_below_12px_except_count_bubbles(): void
    {
        foreach ($this->views() as $name => $markup) {
            foreach (explode("\n", $markup) as $line) {
                if (! preg_match('/text-\[(?:[0-9]|1[01])px\]/', $line)) {
                    continue;
                }
                // A notification count in a 16px circle can't hold a 12px digit.
                $isCountBubble = str_contains($line, 'rounded-full') && preg_match('/(?<![\w-])w-4(?![\w.])/', $line);
                $this->assertTrue((bool) $isCountBubble, "{$name}: text below 12px — ".trim(substr($line, 0, 140)));
            }
        }
    }

    public function test_no_faux_bold_or_low_contrast_brown_text(): void
    {
        foreach ($this->views() as $name => $markup) {
            $this->assertDoesNotMatchRegularExpression('/\bfont-black\b/', $markup, "{$name} uses font-black (900 isn't bundled, so it's faux-bolded)");
            $this->assertStringNotContainsString('text-[#8D6E63]', $markup, "{$name} uses #8D6E63 text (4.3:1 on cream) — use #795548");
        }
    }

    public function test_removed_focus_outlines_are_replaced(): void
    {
        foreach ($this->views() as $name => $markup) {
            foreach (explode("\n", $markup) as $line) {
                if (str_contains($line, 'focus:outline-none')) {
                    $this->assertMatchesRegularExpression('/focus(?:-visible)?:(?:border|ring)/', $line, "{$name}: focus outline removed with nothing visible in its place");
                }
            }
        }
    }
}
