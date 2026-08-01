<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard for the text-[#A1887F] -> text-[#6D4C41] contrast fix (see
 * /root/.claude/plans/misty-plotting-bird.md, Phase 3.1). #A1887F computes to
 * ~3.3:1 against white/#FDF8F5/#FAFAFA, failing WCAG AA (needs 4.5:1); #6D4C41
 * computes to ~7.2-7.6:1. The two layout files are deliberately excluded from
 * the bulk replace — most of their occurrences are the correct dark-sidebar
 * icon color (against #3E2723) which must not change; only the one "Admin/
 * Staff Status:" label in each (a light-background context) was fixed, as
 * part of the header-clipping edit.
 */
class UiUxColorContrastTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_layout_sidebar_color_is_untouched_but_header_label_is_fixed(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/dashboard');

        $response->assertOk();
        // Sidebar icon color against the dark background — must remain.
        $response->assertSee('text-[#A1887F] group-hover:text-amber-100', false);
        // The one light-background exception — must be fixed.
        $response->assertSee('text-[#6D4C41] group-hover:text-[#3E2723]', false);
    }

    public function test_staff_layout_sidebar_color_is_untouched_but_header_label_is_fixed(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('text-[#A1887F] group-hover:text-amber-100', false);
        $response->assertSee('text-[#6D4C41] group-hover:text-[#3E2723]', false);
    }

    public function test_inventory_products_page_no_longer_uses_the_failing_contrast_color(): void
    {
        // A rendered admin page always includes the shared layout's (correct,
        // untouched) sidebar occurrences too, so this checks the view file's
        // own source directly rather than the full rendered response.
        $source = file_get_contents(resource_path('views/inventory/products.blade.php'));

        $this->assertStringNotContainsString('text-[#A1887F]', $source);
        $this->assertStringContainsString('text-[#6D4C41]', $source);
    }

    public function test_only_the_two_layout_files_still_contain_the_failing_contrast_color(): void
    {
        $viewsPath = resource_path('views');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($viewsPath));
        $filesWithOldColor = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            if (str_contains(file_get_contents($file->getPathname()), 'text-[#A1887F]')) {
                $filesWithOldColor[] = str_replace($viewsPath.'/', '', $file->getPathname());
            }
        }

        sort($filesWithOldColor);
        $this->assertSame(
            ['layouts/admin.blade.php', 'layouts/staff.blade.php'],
            $filesWithOldColor
        );
    }
}
