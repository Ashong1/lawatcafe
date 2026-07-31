<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_only_the_authenticated_users_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $admin->notify(new SystemAlert('Low stock', 'Milk is running low.'));
        $otherAdmin->notify(new SystemAlert('Shift issue', 'Cash drawer short.'));

        $response = $this->actingAs($admin)->getJson(route('notifications.index'));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame('Low stock', $data[0]['data']['title']);
    }

    public function test_get_unread_count_reflects_only_unread_notifications(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->notify(new SystemAlert('First', 'One.'));
        $admin->notify(new SystemAlert('Second', 'Two.'));

        $this->actingAs($admin)->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 2]);

        $admin->unreadNotifications()->first()->markAsRead();

        // Re-fetch rather than reuse $admin: actingAs() sets the exact object
        // passed in as the authenticated user, and the assertOk() call above
        // already cached the `unreadNotifications` relation onto it — a real
        // browser request never shares a PHP object/relation cache across
        // requests, so a fresh instance here is what makes this test actually
        // exercise the controller's live query instead of testing a stale
        // in-process cache.
        $this->actingAs($admin->fresh())->getJson(route('notifications.unread-count'))
            ->assertOk()
            ->assertJson(['count' => 1]);
    }

    public function test_mark_as_read_marks_a_single_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->notify(new SystemAlert('First', 'One.'));
        $notification = $admin->unreadNotifications->first();

        $this->actingAs($admin)->postJson(route('notifications.read', $notification->id))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_silently_ignores_an_unknown_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson(route('notifications.read', '00000000-0000-0000-0000-000000000000'))
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_mark_as_read_cannot_be_used_to_read_another_users_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $otherAdmin->notify(new SystemAlert('Not yours', 'Belongs to someone else.'));
        $notification = $otherAdmin->unreadNotifications->first();

        $this->actingAs($admin)->postJson(route('notifications.read', $notification->id))
            ->assertOk();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_mark_all_as_read_marks_every_unread_notification(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->notify(new SystemAlert('First', 'One.'));
        $admin->notify(new SystemAlert('Second', 'Two.'));

        $this->actingAs($admin)->postJson(route('notifications.read-all'))
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertSame(0, $admin->unreadNotifications()->count());
    }

    public function test_guest_cannot_reach_any_notification_endpoint(): void
    {
        $this->getJson(route('notifications.index'))->assertUnauthorized();
        $this->getJson(route('notifications.unread-count'))->assertUnauthorized();
        $this->postJson(route('notifications.read-all'))->assertUnauthorized();
    }
}
