<?php

namespace Tests\Feature\Agent;

use App\Models\Category;
use App\Services\Agent\Tools\SuggestCategoryContentTool;
use App\Services\Agent\ToolRegistry;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestCategoryContentToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_is_auto_tier(): void
    {
        $this->assertSame('auto', app(SuggestCategoryContentTool::class)->permissionTier());
    }

    public function test_it_is_reachable_by_admin_but_not_staff_or_guest(): void
    {
        $registry = app(ToolRegistry::class);

        $this->assertArrayHasKey('suggestCategoryContent', $registry->forAudience(ToolRegistry::AUDIENCE_ADMIN));
        $this->assertArrayNotHasKey('suggestCategoryContent', $registry->forAudience(ToolRegistry::AUDIENCE_STAFF));
        $this->assertArrayNotHasKey('suggestCategoryContent', $registry->forAudience(ToolRegistry::AUDIENCE_GUEST));
    }

    public function test_it_applies_the_suggestion_to_a_category_found_by_id(): void
    {
        $category = Category::create(['name' => 'Pastries', 'icon' => 'coffee']);

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('suggestCategoryContent')
                ->once()
                ->with('Pastries')
                ->andReturn(['description' => 'Freshly baked treats.', 'icon' => 'croissant']);
        });

        $result = app(SuggestCategoryContentTool::class)->execute(['category_id' => $category->id], null);

        $this->assertTrue($result->success);
        $category->refresh();
        $this->assertSame('Freshly baked treats.', $category->description);
        $this->assertSame('croissant', $category->icon);
    }

    public function test_it_finds_a_category_by_partial_name_when_no_id_given(): void
    {
        $category = Category::create(['name' => 'Iced Beverages']);

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('suggestCategoryContent')
                ->once()
                ->andReturn(['description' => 'Cold drinks to beat the heat.', 'icon' => 'cup-soda']);
        });

        $result = app(SuggestCategoryContentTool::class)->execute(['category_name' => 'Iced'], null);

        $this->assertTrue($result->success);
        $this->assertSame($category->id, $result->data['category_id']);
    }

    public function test_it_fails_when_no_category_matches(): void
    {
        $result = app(SuggestCategoryContentTool::class)->execute(['category_name' => 'Nonexistent'], null);

        $this->assertFalse($result->success);
    }

    public function test_it_fails_gracefully_when_ai_cannot_generate_a_suggestion(): void
    {
        $category = Category::create(['name' => 'Pastries']);

        $this->mock(AIService::class, function ($mock) {
            $mock->shouldReceive('suggestCategoryContent')->once()->andReturn(null);
        });

        $result = app(SuggestCategoryContentTool::class)->execute(['category_id' => $category->id], null);

        $this->assertFalse($result->success);
        $category->refresh();
        $this->assertNull($category->description);
    }
}
