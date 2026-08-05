<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Category descriptions must describe THIS shop's menu, not the dictionary
 * definition of the category's name.
 *
 * Given only a name, the model wrote a textbook definition — "Milk Based" came
 * back as "Creamy espresso and non-coffee drinks prepared with fresh steamed or
 * chilled milk" when that category held a single matcha latte and no espresso
 * at all, while every espresso drink sat in a separate "Coffee Based" category
 * the model had no idea existed. Two failures in one sentence: it claimed
 * products the shop does not sell in that category, and it described a sibling
 * category's contents.
 *
 * These assert the two pieces of context that prevent both, by inspecting the
 * prompt that actually goes out.
 */
class CategoryDescriptionContextTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProvider(array $payload = ['description' => 'A description.', 'icon' => 'milk']): void
    {
        Http::fake([
            '*' => Http::response([
                'choices' => [['message' => ['content' => json_encode($payload)]]],
            ], 200),
        ]);
    }

    /** The text of the prompt that was actually sent to a provider. */
    private function sentPrompt(): string
    {
        $prompt = '';

        Http::assertSent(function ($request) use (&$prompt) {
            $body = json_encode($request->data());
            if (str_contains($body, 'category descriptions')) {
                $prompt = $body;
            }

            return true;
        });

        // Fail loudly rather than return '': an empty prompt would make every
        // assertStringNotContainsString below pass for the wrong reason.
        $this->assertNotSame('', $prompt, 'No category-description prompt was sent to any provider.');

        return $prompt;
    }

    private function seedMenu(): void
    {
        Category::create(['name' => 'Milk Based', 'icon' => 'milk', 'sort_order' => 1]);
        Category::create(['name' => 'Coffee Based', 'icon' => 'coffee', 'sort_order' => 2]);
        Category::create(['name' => 'Pastries', 'icon' => 'croissant', 'sort_order' => 3]);

        Product::create(['name' => 'Matcha Latte', 'category' => 'Milk Based', 'price' => 130, 'status' => 'Active']);
        Product::create(['name' => 'Classic Latte', 'category' => 'Coffee Based', 'price' => 120, 'status' => 'Active']);
    }

    /** Without this the model invents drink types the category does not contain. */
    public function test_the_prompt_names_the_products_actually_in_the_category(): void
    {
        $this->seedMenu();
        $this->fakeProvider();

        app(AIService::class)->suggestCategoryContent('Milk Based');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Matcha Latte', $prompt);
        // And NOT the product that lives in a different category.
        $this->assertStringNotContainsString('Classic Latte', $prompt);
    }

    /** Without this two categories cheerfully describe each other's contents. */
    public function test_the_prompt_names_the_sibling_categories_to_avoid_overlap(): void
    {
        $this->seedMenu();
        $this->fakeProvider();

        app(AIService::class)->suggestCategoryContent('Milk Based');

        $prompt = $this->sentPrompt();

        $this->assertStringContainsString('Coffee Based', $prompt);
        $this->assertStringContainsString('Pastries', $prompt);
        $this->assertStringContainsString('must not overlap', $prompt);
    }

    /**
     * A brand-new category has no products yet. It must still get a
     * description, and the model must be told not to invent specifics.
     */
    public function test_an_empty_category_is_handled_without_inventing_products(): void
    {
        Category::create(['name' => 'Cold Brews', 'icon' => 'layers', 'sort_order' => 1]);
        $this->fakeProvider(['description' => 'Chilled slow-steeped drinks.', 'icon' => 'layers']);

        $result = app(AIService::class)->suggestCategoryContent('Cold Brews');

        $this->assertSame('Chilled slow-steeped drinks.', $result['description']);
        $this->assertStringContainsString('no products yet', $this->sentPrompt());
    }

    /** The icon is still validated against the allowed list, as before. */
    public function test_an_invented_icon_still_falls_back_to_a_safe_default(): void
    {
        $this->seedMenu();
        $this->fakeProvider(['description' => 'A description.', 'icon' => 'not-a-real-icon']);

        $result = app(AIService::class)->suggestCategoryContent('Milk Based');

        $this->assertSame('layers', $result['icon']);
    }
}
