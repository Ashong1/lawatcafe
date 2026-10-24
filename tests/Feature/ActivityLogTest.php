<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_sign_in_wrong_password_and_sign_out_are_recorded(): void
    {
        $user = User::factory()->create(['role' => 'staff', 'username' => 'ana']);
        $client = $this->withoutMiddleware(ValidateCsrfToken::class);

        $client->post('/login', ['email' => 'ana', 'password' => 'not-it']);
        $client->post('/login', ['email' => 'ana', 'password' => 'password']);
        $client->post('/logout');

        $this->assertSame(['sign_in_failed', 'signed_in', 'signed_out'], ActivityLog::orderBy('id')->pluck('event')->all());
        $failed = ActivityLog::where('event', 'sign_in_failed')->first();
        $this->assertSame($user->id, $failed->user_id);
        $this->assertSame('Tried: ana', $failed->detail);
        $this->assertStringNotContainsString('not-it', ActivityLog::pluck('detail')->join(' '), 'passwords are never logged');
    }

    public function test_removing_an_account_is_recorded_against_the_owner(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff', 'name' => 'Ben']);

        $this->actingAs($owner)->delete(route('accounts.destroy', $staff));

        $this->assertDatabaseHas('activity_logs', ['event' => 'account_removed', 'user_id' => $owner->id, 'detail' => 'Ben']);
    }

    public function test_only_the_owner_can_read_the_log(): void
    {
        ActivityLog::record('signed_in', User::factory()->create(['name' => 'Ana']));

        $this->actingAs(User::factory()->create(['role' => 'admin']))->get(route('activity-log'))->assertOk()->assertSee('Ana')->assertSee('Signed in');
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('activity-log'))->assertRedirect();
    }

    public function test_a_failed_backup_is_logged_for_the_owner_to_see(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

        $this->artisan('backup:database')->assertFailed();

        $this->assertDatabaseHas('activity_logs', ['event' => 'backup_failed', 'detail' => 'Access denied']);
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }
}
