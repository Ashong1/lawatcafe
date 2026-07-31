<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Services\Agent\Tools\CheckStockLevelsTool;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: "how many stocks does the milk have" got back "No ingredients
 * are currently low on stock" — checkStockLevels only ever reported
 * ingredients AT/BELOW their low-stock threshold, with no way to look up
 * one specific ingredient's actual stock (which is what a "how much do we
 * have" question actually needs). Extended with an optional
 * ingredient_id/ingredient_name lookup mirroring restockIngredient's
 * existing convention.
 */
class CheckStockLevelsToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_arguments_still_lists_low_stock_ingredients(): void
    {
        Ingredient::create(['name' => 'Milk', 'current_stock' => 500, 'unit' => 'ml', 'low_stock_threshold' => 1000]);
        Ingredient::create(['name' => 'Coffee Beans', 'current_stock' => 5000, 'unit' => 'g', 'low_stock_threshold' => 1000]);

        $result = app(CheckStockLevelsTool::class)->execute([], null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('Milk', $result->message);
        $this->assertStringNotContainsString('Coffee Beans', $result->message);
    }

    public function test_no_arguments_and_nothing_low_says_so(): void
    {
        Ingredient::create(['name' => 'Sugar', 'current_stock' => 5000, 'unit' => 'g', 'low_stock_threshold' => 1000]);

        $result = app(CheckStockLevelsTool::class)->execute([], null);

        $this->assertSame('No ingredients are currently low on stock.', $result->message);
    }

    public function test_ingredient_name_reports_exact_stock_even_when_not_low(): void
    {
        Ingredient::create(['name' => 'Whole Milk', 'current_stock' => 8000, 'unit' => 'ml', 'low_stock_threshold' => 1000]);

        $result = app(CheckStockLevelsTool::class)->execute(['ingredient_name' => 'milk'], null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('8000ml in stock', $result->message);
        $this->assertFalse($result->data['is_low']);
    }

    public function test_ingredient_name_flags_when_that_ingredient_is_low(): void
    {
        Ingredient::create(['name' => 'Whole Milk', 'current_stock' => 200, 'unit' => 'ml', 'low_stock_threshold' => 1000]);

        $result = app(CheckStockLevelsTool::class)->execute(['ingredient_name' => 'milk'], null);

        $this->assertTrue($result->success);
        $this->assertStringContainsString('low-stock threshold', $result->message);
        $this->assertTrue($result->data['is_low']);
    }

    public function test_ingredient_id_takes_precedence_and_works(): void
    {
        $ingredient = Ingredient::create(['name' => 'Oat Milk', 'current_stock' => 3000, 'unit' => 'ml', 'low_stock_threshold' => 500]);

        $result = app(CheckStockLevelsTool::class)->execute(['ingredient_id' => $ingredient->id], null);

        $this->assertTrue($result->success);
        $this->assertSame($ingredient->id, $result->data['id']);
    }

    public function test_unknown_ingredient_name_fails_gracefully(): void
    {
        $result = app(CheckStockLevelsTool::class)->execute(['ingredient_name' => 'unobtainium'], null);

        $this->assertFalse($result->success);
        $this->assertStringContainsString('Could not find', $result->message);
    }
}
