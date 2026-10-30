<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Accounts are archived by the owner, never deleted (evaluators, Oct 28) — not even by their owner. */
class ProfileNoSelfDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_nobody_can_delete_their_own_account(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get('/profile')->assertOk()->assertDontSee('Delete Account');
        $this->actingAs($staff)->delete('/profile', ['password' => 'password'])->assertStatus(405);

        $this->assertModelExists($staff);
    }
}
