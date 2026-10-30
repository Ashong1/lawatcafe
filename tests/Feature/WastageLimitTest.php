<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Evaluators (Oct 28): spoilage is one opened package at a time, so a single
 * wastage entry can't be more than one package holds — or more than is in stock.
 */
class WastageLimitTest extends TestCase
{
    use RefreshDatabase;

    private function logWaste(Ingredient $ingredient, float $quantity)
    {
        return $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('inventory.wastage.store'), [
                'ingredient_id' => $ingredient->id, 'quantity' => $quantity, 'reason' => 'Spoiled',
            ]);
    }

    public function test_more_than_one_package_is_refused(): void
    {
        $milk = Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'packaging_unit' => 'box', 'capacity_per_pack' => 1000, 'current_stock' => 5000]);

        $this->logWaste($milk, 1200)->assertSessionHasErrors(['quantity' => 'One opened box holds 1,000 ml. Log each package separately.']);
        $this->assertEquals(5000, $milk->fresh()->current_stock);

        $this->logWaste($milk, 1000)->assertSessionHasNoErrors();
        $this->assertEquals(4000, $milk->fresh()->current_stock);
    }

    public function test_without_packaging_only_the_stock_limits_it(): void
    {
        $syrup = Ingredient::create(['name' => 'Syrup', 'unit' => 'ml', 'current_stock' => 300]);

        $this->logWaste($syrup, 400)->assertSessionHasErrors(['quantity' => 'Only 300 ml in stock.']);
        $this->logWaste($syrup, 300)->assertSessionHasNoErrors();
    }
}
