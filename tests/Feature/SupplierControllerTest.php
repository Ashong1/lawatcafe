<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_the_suppliers_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        Supplier::create(['name' => 'Kape Beans Co.']);

        $this->actingAs($admin)->get(route('inventory.suppliers.index'))
            ->assertOk()
            ->assertViewHas('suppliers', fn ($suppliers) => $suppliers->total() === 1);
    }

    public function test_admin_can_create_a_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('inventory.suppliers.store'), [
            'name' => 'Kape Beans Co.',
            'contact_person' => 'Juan Dela Cruz',
            'phone' => '09171234567',
            'email' => 'orders@kapebeans.test',
            'delivery_days' => ['Monday', 'Thursday'],
        ])->assertRedirect();

        $this->assertDatabaseHas('suppliers', ['name' => 'Kape Beans Co.', 'contact_person' => 'Juan Dela Cruz']);
    }

    public function test_create_rejects_a_malformed_email(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('inventory.suppliers.store'), [
            'name' => 'Kape Beans Co.',
            'email' => 'not-an-email',
        ])->assertSessionHasErrors('email');
    }

    public function test_admin_can_update_a_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['name' => 'Kape Beans Co.']);

        $this->actingAs($admin)->put(route('inventory.suppliers.update', $supplier), [
            'name' => 'Kape Beans Co. Renamed',
        ])->assertRedirect();

        $this->assertSame('Kape Beans Co. Renamed', $supplier->fresh()->name);
    }

    public function test_admin_can_delete_a_supplier(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $supplier = Supplier::create(['name' => 'Kape Beans Co.']);

        $this->actingAs($admin)->delete(route('inventory.suppliers.destroy', $supplier))
            ->assertRedirect();

        $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
    }

    public function test_staff_cannot_reach_supplier_endpoints(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)->get(route('inventory.suppliers.index'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($staff)->post(route('inventory.suppliers.store'), ['name' => 'X'])
            ->assertRedirect(route('staff.dashboard'));
    }
}
