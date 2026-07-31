<?php

namespace App\Services\Agent\Tools;

use App\Models\Category;
use App\Models\User;
use App\Services\Agent\Contracts\AgentTool;
use App\Services\Agent\ToolResult;
use App\Services\AIService;

class SuggestCategoryContentTool implements AgentTool
{
    public function __construct(protected AIService $ai) {}

    public function name(): string
    {
        return 'suggestCategoryContent';
    }

    public function description(): string
    {
        return "Generate and apply an AI-written description and a matching icon for an existing product category, by name or ID. Overwrites the category's current description/icon.";
    }

    public function parametersSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'category_id' => ['type' => 'integer', 'description' => 'Category ID (preferred if known)'],
                'category_name' => ['type' => 'string', 'description' => 'Category name, used if ID is not known'],
            ],
            'required' => [],
        ];
    }

    public function permissionTier(): string
    {
        return 'auto';
    }

    public function execute(array $arguments, ?User $actor, array $context = []): ToolResult
    {
        $category = null;
        if (! empty($arguments['category_id'])) {
            $category = Category::find($arguments['category_id']);
        } elseif (! empty($arguments['category_name'])) {
            $category = Category::where('name', 'like', '%'.$arguments['category_name'].'%')->first();
        }

        if (! $category) {
            return ToolResult::fail('Could not find a matching category.');
        }

        $suggestion = $this->ai->suggestCategoryContent($category->name);
        if (! $suggestion) {
            return ToolResult::fail("Could not generate a suggestion for \"{$category->name}\" right now.");
        }

        $category->update($suggestion);

        return ToolResult::ok(
            "Updated \"{$category->name}\" with a new description and the \"{$suggestion['icon']}\" icon.",
            ['category_id' => $category->id, ...$suggestion]
        );
    }
}
