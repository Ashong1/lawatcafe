<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: the header labelled every user by the LAYOUT they happened to be
 * rendered in, not by their actual role — admin.blade.php said "Admin Status:"
 * for everyone, so the super_admin account was reported as a plain admin.
 *
 * User::roleLabel() is now the single source, so these assert the rendered
 * label rather than the helper in isolation: the bug was that the view never
 * consulted the role at all, which a unit test of the helper would have missed
 * entirely.
 */
class RoleLabelDisplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_super_admin_header_does_not_claim_to_be_an_admin(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'super_admin']))
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Super Admin:', false);
        // The old hardcoded label must be gone, not merely joined by a new one.
        $response->assertDontSee('Admin Status:', false);
    }

    public function test_an_admin_header_reports_admin(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('Admin:', false);
        $response->assertDontSee('Super Admin:', false);
    }

    public function test_a_staff_header_reports_staff(): void
    {
        $response = $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Staff:', false);
        $response->assertDontSee('Staff Status:', false);
    }

    /**
     * The accounts table branched on `role === 'admin'` with a catch-all else,
     * so any role that was not literally "admin" rendered as "Barista".
     */
    public function test_the_accounts_table_labels_each_role_rather_than_falling_back(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        User::factory()->create(['role' => 'admin', 'name' => 'Ada Admin']);
        User::factory()->create(['role' => 'staff', 'name' => 'Sam Staff']);

        $response = $this->actingAs($superAdmin)->get(route('accounts.index'));

        $response->assertOk();
        $response->assertSee('Ada Admin', false);
        $response->assertSee('Sam Staff', false);

        // Read the badges themselves rather than searching the whole page:
        // "Barista" also appears as the AI widget's name, and "Admin" matches
        // half the sidebar, so a bare assertSee/assertDontSee proves nothing.
        preg_match_all(
            '/rounded-lg text-\[10px\] font-black uppercase tracking-widest border[^"]*">\s*([^<\s][^<]*?)\s*</',
            $response->getContent(),
            $matches
        );

        // Not an exact list: the suite's own baseline users show up here too.
        // What matters is that every badge is a real role label, that both
        // roles present are named correctly, and that nothing fell through to
        // the old catch-all.
        $this->assertContains('Admin', $matches[1]);
        $this->assertContains('Staff', $matches[1]);
        $this->assertEmpty(array_diff($matches[1], ['Admin', 'Staff']));

        // super_admin is excluded from this list entirely (AccountController),
        // so its label must not appear here even though it is the viewer.
        $this->assertNotContains('Super Admin', $matches[1]);
    }

    public function test_an_unrecognised_role_is_not_echoed_back_as_if_official(): void
    {
        $user = User::factory()->make(['role' => 'wizard']);

        $this->assertSame('Unknown Role', $user->roleLabel());
    }
}
