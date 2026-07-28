<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VoucherDestructiveActionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_destroy_a_single_voucher(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $voucher = Voucher::create(['code' => 'LAWA-DEL01', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);

        $response = $this->actingAs($admin)->delete(route('network.vouchers.destroy', $voucher));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }

    public function test_admin_can_bulk_delete_selected_vouchers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $v1 = Voucher::create(['code' => 'LAWA-BULK1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);
        $v2 = Voucher::create(['code' => 'LAWA-BULK2', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);
        $keep = Voucher::create(['code' => 'LAWA-KEEP1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);

        $response = $this->actingAs($admin)->post(route('network.vouchers.bulk-delete'), [
            'ids' => [$v1->id, $v2->id],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('vouchers', ['id' => $v1->id]);
        $this->assertDatabaseMissing('vouchers', ['id' => $v2->id]);
        $this->assertDatabaseHas('vouchers', ['id' => $keep->id]);
    }

    public function test_bulk_delete_rejects_a_nonexistent_voucher_id(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $valid = Voucher::create(['code' => 'LAWA-VALID1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);

        $response = $this->actingAs($admin)->post(route('network.vouchers.bulk-delete'), [
            'ids' => [$valid->id, 999999],
        ]);

        $response->assertSessionHasErrors('ids.1');
        $this->assertDatabaseHas('vouchers', ['id' => $valid->id]);
    }

    public function test_admin_can_purge_expired_used_vouchers(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Voucher::create([
            'code' => 'LAWA-EXPIRED1', 'duration_minutes' => 60, 'tier' => 'free',
            'is_used' => true, 'used_at' => now()->subHours(2),
        ]);
        $active = Voucher::create([
            'code' => 'LAWA-ACTIVE1', 'duration_minutes' => 60, 'tier' => 'free',
            'is_used' => true, 'used_at' => now(),
        ]);

        $response = $this->actingAs($admin)->post(route('network.vouchers.purge'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('vouchers', ['code' => 'LAWA-EXPIRED1']);
        $this->assertDatabaseHas('vouchers', ['id' => $active->id]);
    }

    public function test_staff_cannot_reach_any_destructive_voucher_endpoint(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $voucher = Voucher::create(['code' => 'LAWA-PROTECT1', 'duration_minutes' => 60, 'tier' => 'free', 'is_used' => false]);

        $this->actingAs($staff)->delete(route('network.vouchers.destroy', $voucher))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('network.vouchers.bulk-delete'), ['ids' => [$voucher->id]])->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('network.vouchers.purge'))->assertRedirect(route('staff.dashboard'));

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
    }
}
