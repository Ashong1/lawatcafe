<?php

namespace Tests\Feature;

use App\Models\Ingredient;
use App\Models\InventoryLog;
use App\Models\User;
use App\Services\Agent\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Owner (Oct 30): stock can't be added without its expiry date, on the page and through Barista AI, unless it doesn't expire. */
class RestockExpiryRequiredTest extends TestCase
{
    use RefreshDatabase;

    private function milk(): Ingredient
    {
        return Ingredient::create(['name' => 'Milk', 'unit' => 'ml', 'current_stock' => 0]);
    }

    public function test_the_page_needs_an_expiry_date_or_doesnt_expire(): void
    {
        $milk = $this->milk();
        $owner = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)->post(route('inventory.ingredients.add-stock', $milk), ['added_amount' => 1000])
            ->assertSessionHasErrors('expires_at');
        $this->assertEquals(0, $milk->fresh()->current_stock);

        $this->actingAs($owner)->post(route('inventory.ingredients.add-stock', $milk), ['added_amount' => 1000, 'expires_at' => now()->addDays(10)->toDateString()])
            ->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('inventory.ingredients.add-stock', $milk), ['added_amount' => 500, 'no_expiry' => '1'])
            ->assertSessionHasNoErrors();

        $this->assertEquals(1500, $milk->fresh()->current_stock);
        $this->assertSame(1, InventoryLog::whereNotNull('expires_at')->count());

        $this->actingAs($owner)->get(route('inventory.ingredients.index'))->assertSee("Doesn't expire", false);
    }

    public function test_barista_ai_must_ask_for_the_expiry_date_first(): void
    {
        $milk = $this->milk();
        $tool = app(ToolRegistry::class)->forAudience(ToolRegistry::AUDIENCE_ADMIN)['restockIngredient'];

        $none = $tool->execute(['ingredient_id' => $milk->id, 'added_amount' => 10], null)->toArray();
        $this->assertFalse($none['success']);
        $this->assertStringContainsString('expiry date', $none['message']);
        $this->assertEquals(0, $milk->fresh()->current_stock);

        $this->assertTrue($tool->execute(['ingredient_id' => $milk->id, 'added_amount' => 10, 'expires_at' => now()->addWeek()->toDateString()], null)->toArray()['success']);
        $this->assertTrue($tool->execute(['ingredient_id' => $milk->id, 'added_amount' => 5, 'no_expiry' => true], null)->toArray()['success']);
        $this->assertFalse($tool->execute(['ingredient_id' => $milk->id, 'added_amount' => 5, 'expires_at' => now()->subDay()->toDateString()], null)->toArray()['success']);

        $this->assertArrayHasKey('expires_at', $tool->parametersSchema()['properties']);
    }
}
