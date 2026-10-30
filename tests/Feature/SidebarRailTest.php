<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner's video (Oct 30): the collapsed sidebar's group icons did nothing and labels overflowed while collapsing. */
class SidebarRailTest extends TestCase
{
    use RefreshDatabase;

    public function test_collapsed_group_icons_open_the_sidebar_and_labels_hide_at_once(): void
    {
        $html = $this->actingAs(User::factory()->create(['role' => 'super_admin']))->get('/dashboard')->assertOk()->getContent();

        foreach (['inventory', 'network', 'ai', 'settings', 'system'] as $menu) {
            $this->assertStringContainsString("@click=\"toggleMenu('$menu')\"", $html);
        }
        $this->assertStringNotContainsString('menus.inventory = !menus.inventory', $html);
        $this->assertStringContainsString("'grid-template-rows: 0fr; transition: none'", $html);
        $this->assertStringContainsString('whitespace-nowrap uppercase">Lawa\'t Kape v', $html);
        $this->assertSame(0, substr_count(explode('<nav x-ref="sidebarNav"', $html)[1] ?? '', 'x-transition:leave="transition ease-in-out duration-300"'));
    }

    /** Owner's 2nd video: labels flashed in the collapsed rail for a frame while a page loaded, before Alpine ran. */
    public function test_the_rail_hides_labels_before_any_script_runs(): void
    {
        $css = file_get_contents(resource_path('css/base.css'));

        $this->assertStringContainsString('.lk-sidebar-rail [x-show="navLabelsVisible"]', $css);
        $this->assertStringContainsString('.lk-sidebar-rail .lk-brand-mark + div', $css);
        $this->assertStringContainsString('aside:not(.lk-sidebar-rail) [x-show="!navLabelsVisible"]', $css);
    }
}
