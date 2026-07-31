<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Regression: the notification bell and pending-AI-actions dropdowns in the
 * header (resources/views/components/notification-bell.blade.php and
 * agent-pending-badge.blade.php) were missing x-cloak on their x-show="open"
 * panel. This app has no SPA routing — every navigation is a full page
 * reload — so on each one, before Alpine finishes initializing, the dropdown
 * briefly rendered in its raw (visible) HTML state instead of hidden,
 * reported as "the notification and pending AI actions open by themselves
 * when I click to other pages". Same bug class as
 * feedback_x_cloak_on_deferred_alpine (memory), just missed on these two.
 */
class HeaderDropdownXCloakTest extends TestCase
{
    public function test_notification_bell_dropdown_has_x_cloak(): void
    {
        $content = file_get_contents(resource_path('views/components/notification-bell.blade.php'));

        $this->assertMatchesRegularExpression('/x-show="open"\s+x-cloak/', $content);
    }

    public function test_agent_pending_badge_dropdown_has_x_cloak(): void
    {
        $content = file_get_contents(resource_path('views/components/agent-pending-badge.blade.php'));

        $this->assertMatchesRegularExpression('/x-show="open"\s+x-cloak/', $content);
    }
}
