<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\SystemAlert;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Notifications are database-backed and, until now, permanent — nothing in the
 * app ever removed one, so the bell only ever grew. These pin the two ways out
 * of that and, more importantly, the scoping: every route resolves the row
 * through the signed-in user's own relation, so someone else's notification is
 * not merely rejected but never in the set to begin with.
 */
class NotificationDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $title = 'Test alert'): string
    {
        $user->notify(new SystemAlert($title, 'Body text'));

        return $user->notifications()->latest()->first()->id;
    }

    public function test_a_user_can_delete_their_own_notification(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $id = $this->notify($user);

        $this->actingAs($user)
            ->deleteJson(route('notifications.destroy', $id))
            ->assertOk()
            ->assertJson(['success' => true, 'unread_count' => 0]);

        $this->assertSame(0, $user->fresh()->notifications()->count());
    }

    /**
     * The scoping test. A notification id is a UUID and not guessable, but "not
     * guessable" is not an authorization model — the route must not touch a row
     * it does not own even when handed the id directly.
     */
    public function test_a_user_cannot_delete_someone_elses_notification(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'admin']);
        $id = $this->notify($owner, 'Private to the owner');

        $this->actingAs($other)
            ->deleteJson(route('notifications.destroy', $id))
            ->assertNotFound();

        $this->assertSame(1, $owner->fresh()->notifications()->count());
    }

    public function test_deleting_an_unread_notification_lowers_the_badge(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $first = $this->notify($user, 'One');
        $this->notify($user, 'Two');

        $this->actingAs($user)
            ->deleteJson(route('notifications.destroy', $first))
            ->assertOk()
            ->assertJson(['unread_count' => 1]);
    }

    /**
     * Unread means nobody has looked at it — a shift shortage, a rejected AI
     * action, a delivery awaiting confirmation. A bulk clear that discarded
     * those would lose exactly the ones worth keeping.
     */
    public function test_clearing_read_notifications_leaves_the_unread_ones(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $readId = $this->notify($user, 'Already seen');
        $this->notify($user, 'Not yet seen');

        $user->notifications()->where('id', $readId)->first()->markAsRead();

        $this->actingAs($user)
            ->deleteJson(route('notifications.destroy-read'))
            ->assertOk()
            ->assertJson(['success' => true, 'deleted' => 1, 'unread_count' => 1]);

        $remaining = $user->fresh()->notifications()->get();
        $this->assertCount(1, $remaining);
        $this->assertSame('Not yet seen', $remaining->first()->data['title']);
    }

    /** A bulk clear must not reach past the signed-in user either. */
    public function test_clearing_read_notifications_only_touches_your_own(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $other = User::factory()->create(['role' => 'staff']);

        foreach ([$user, $other] as $person) {
            $id = $this->notify($person, 'Seen');
            $person->notifications()->where('id', $id)->first()->markAsRead();
        }

        $this->actingAs($user)->deleteJson(route('notifications.destroy-read'))->assertOk();

        $this->assertSame(0, $user->fresh()->notifications()->count());
        $this->assertSame(1, $other->fresh()->notifications()->count());
    }

    /** Nothing to clear is a no-op, not an error. */
    public function test_clearing_with_nothing_read_is_harmless(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->notify($user);

        $this->actingAs($user)
            ->deleteJson(route('notifications.destroy-read'))
            ->assertOk()
            ->assertJson(['deleted' => 0, 'unread_count' => 1]);

        $this->assertSame(1, $user->fresh()->notifications()->count());
    }

    public function test_deleting_an_unknown_id_is_a_404_rather_than_an_error(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user)
            ->deleteJson(route('notifications.destroy', 'not-a-real-id'))
            ->assertNotFound();
    }

    /**
     * The bulk route is declared before the wildcard one. Reversed, 'read'
     * matches as an id and the clear silently becomes a lookup for a
     * notification named "read" — a 404 where the user expected a clear-out.
     */
    public function test_the_bulk_route_is_not_swallowed_by_the_wildcard(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $id = $this->notify($user);
        $user->notifications()->where('id', $id)->first()->markAsRead();

        $this->actingAs($user)
            ->delete('/notifications/read')
            ->assertOk()
            ->assertJson(['deleted' => 1]);
    }

    public function test_a_guest_cannot_delete_notifications(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $id = $this->notify($user);

        $this->delete(route('notifications.destroy', $id))->assertRedirect(route('login'));

        $this->assertSame(1, DB::table('notifications')->count());
    }

    /** The panel has to offer the actions, or the routes are unreachable. */
    public function test_the_bell_renders_a_delete_control_and_a_bulk_clear(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $html = $this->actingAs($user)->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('remove(notif)', $html);
        $this->assertStringContainsString('clearRead()', $html);
        // The row itself navigates on click; the dismiss must not.
        $this->assertStringContainsString('@click.stop="remove(notif)"', $html);
        // The dead placeholder it replaced.
        $this->assertStringNotContainsString('View All Activity', $html);
    }
}
