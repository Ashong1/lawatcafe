<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withoutMiddleware(ValidateCsrfToken::class)
            ->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_a_wrong_password_says_how_many_attempts_are_left(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors(['email' => trans('auth.failed').' 2 attempts left.']);

        foreach (range(1, 1) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }
        $this->assertStringEndsWith('1 attempt left.', session('errors')->first('email'));
    }

    public function test_the_last_wrong_attempt_locks_sign_in_with_a_countdown(): void
    {
        $user = User::factory()->create();

        foreach (range(1, 3) as $_) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->assertStringContainsString('try again in', session('errors')->first('email'));
        $this->assertGreaterThan(now()->getTimestamp(), session('login_locked_until'));

        // Even the right password is refused until the countdown ends.
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();
        $this->get('/login')->assertSee('lockedFor > 0', false)->assertSee('You can try again in', false);
    }
}
