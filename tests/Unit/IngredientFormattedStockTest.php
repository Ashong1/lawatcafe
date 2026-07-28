<?php

namespace Tests\Unit;

use App\Models\Ingredient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngredientFormattedStockTest extends TestCase
{
    use RefreshDatabase;

    private function ingredient(float $stock, string $unit): Ingredient
    {
        return Ingredient::create([
            'name' => 'Test', 'current_stock' => $stock, 'unit' => $unit,
            'low_stock_threshold' => 100, 'status' => 'In Stock',
        ]);
    }

    public function test_grams_converts_to_kilograms_above_1000(): void
    {
        $result = $this->ingredient(2500, 'g')->formattedStock();
        $this->assertSame('2.5', $result['value']);
        $this->assertSame('kg', $result['unit']);
    }

    public function test_grams_converts_to_milligrams_below_one(): void
    {
        $result = $this->ingredient(0.5, 'g')->formattedStock();
        $this->assertSame('500', $result['value']);
        $this->assertSame('mg', $result['unit']);
    }

    public function test_grams_stays_as_grams_in_normal_range(): void
    {
        $result = $this->ingredient(250, 'g')->formattedStock();
        $this->assertSame('250', $result['value']);
        $this->assertSame('g', $result['unit']);
    }

    public function test_milliliters_converts_to_liters_above_1000(): void
    {
        $result = $this->ingredient(1500, 'ml')->formattedStock();
        $this->assertSame('1.5', $result['value']);
        $this->assertSame('L', $result['unit']);
    }

    public function test_units_other_than_g_or_ml_are_left_unconverted(): void
    {
        $result = $this->ingredient(12, 'pcs')->formattedStock();
        $this->assertSame('12', $result['value']);
        $this->assertSame('pcs', $result['unit']);
    }

    public function test_trailing_zeros_are_trimmed(): void
    {
        $result = $this->ingredient(5, 'ml')->formattedStock();
        $this->assertSame('5', $result['value']);
    }
}
