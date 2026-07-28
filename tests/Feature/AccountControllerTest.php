<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_admin_sees_only_staff_accounts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($admin)->get(route('accounts.index'));

        $response->assertOk();
        $users = $response->viewData('users');
        $this->assertTrue($users->contains('id', $staff->id));
        $this->assertFalse($users->contains('id', $otherAdmin->id));
        $this->assertFalse($users->contains('id', $superAdmin->id));
    }

    public function test_super_admin_sees_admin_and_staff_but_never_super_admin(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($superAdmin)->get(route('accounts.index'));

        $users = $response->viewData('users');
        $this->assertTrue($users->contains('id', $admin->id));
        $this->assertTrue($users->contains('id', $staff->id));
        $this->assertFalse($users->contains('id', $superAdmin->id));
    }

    public function test_plain_admin_cannot_create_an_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('accounts.store'), [
            'name' => 'Sneaky',
            'email' => 'sneaky@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'sneaky@example.com']);
    }

    public function test_no_one_can_create_a_super_admin_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post(route('accounts.store'), [
            'name' => 'Another Super Admin',
            'email' => 'super2@example.com',
            'password' => 'password123',
            'role' => 'super_admin',
        ]);

        $response->assertSessionHasErrors('role');
        $this->assertDatabaseMissing('users', ['email' => 'super2@example.com']);
    }

    public function test_super_admin_can_create_an_admin_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post(route('accounts.store'), [
            'name' => 'New Owner',
            'email' => 'owner@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseHas('users', ['email' => 'owner@example.com', 'role' => 'admin']);
    }

    public function test_plain_admin_cannot_edit_another_admin_account_directly(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->put(route('accounts.update', $otherAdmin), [
            'name' => 'Hijacked',
            'email' => $otherAdmin->email,
            'role' => 'staff',
        ]);

        $response->assertForbidden();
        $this->assertSame('admin', $otherAdmin->fresh()->role);
    }

    public function test_plain_admin_cannot_delete_the_super_admin_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($admin)->delete(route('accounts.destroy', $superAdmin));

        $response->assertForbidden();
        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_super_admin_can_manage_an_admin_account(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($superAdmin)->delete(route('accounts.destroy', $admin));

        $response->assertRedirect(route('accounts.index'));
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
    }
}
