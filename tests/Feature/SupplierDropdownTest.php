<?php

namespace Tests\Feature;

use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (Oct 30): pick a saved supplier from a dropdown instead of typing it, wherever one is asked for. */
class SupplierDropdownTest extends TestCase
{
    use RefreshDatabase;

    public function test_delivery_forms_offer_saved_suppliers_and_wastage_offers_common_reasons(): void
    {
        Supplier::create(['name' => 'Kapitbahay Dairy & Grocery']);
        $admin = User::factory()->create(['role' => 'admin']);

        foreach ([route('inventory.deliveries.index'), route('staff.deliveries.index')] as $url) {
            $this->actingAs($admin)->get($url)->assertOk()
                ->assertSee('data-supplier-select', false)
                ->assertSee('<option value="Kapitbahay Dairy &amp; Grocery">', false)
                ->assertSee('Someone else');
        }

        $this->actingAs($admin)->get(route('inventory.wastage.index'))->assertSee('<option value="Spoiled">', false);
    }
}
