<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_provider_statuses_returns_all_three_providers_with_model_lists(): void
    {
        $statuses = app(AIService::class)->getProviderStatuses();

        $this->assertArrayHasKey('gemini', $statuses);
        $this->assertArrayHasKey('groq', $statuses);
        $this->assertArrayHasKey('openrouter', $statuses);

        foreach (['gemini', 'groq', 'openrouter'] as $provider) {
            $this->assertNotEmpty($statuses[$provider]['models']);
            foreach ($statuses[$provider]['models'] as $model) {
                $this->assertSame('never_tested', $model['status']);
            }
            $this->assertFalse($statuses[$provider]['circuit']['open']);
        }
    }

    public function test_test_provider_records_success_for_every_healthy_model(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => 'OK']]]]],
            ], 200),
        ]);

        $result = app(AIService::class)->testProvider('gemini');

        $this->assertGreaterThan(0, $result['ok']);
        $this->assertSame(0, $result['failed']);

        $statuses = app(AIService::class)->getProviderStatuses();
        foreach ($statuses['gemini']['models'] as $model) {
            $this->assertSame('ok', $model['status']);
        }
        $this->assertFalse($statuses['gemini']['circuit']['open']);
    }

    public function test_test_provider_records_failure_and_reason_for_unreachable_models(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response([], 500),
        ]);

        $result = app(AIService::class)->testProvider('groq');

        $this->assertSame(0, $result['ok']);
        $this->assertGreaterThan(0, $result['failed']);

        $statuses = app(AIService::class)->getProviderStatuses();
        foreach ($statuses['groq']['models'] as $model) {
            $this->assertSame('failed', $model['status']);
            $this->assertSame('http_500', $model['reason']);
        }
        $this->assertSame(1, $statuses['groq']['circuit']['failure_count']);
    }

    public function test_super_admin_can_view_ai_providers_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->get(route('admin.settings.ai-providers'))->assertOk();
    }

    public function test_super_admin_can_save_api_keys(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.settings.ai-providers.update'), [
            'gemini_api_key' => 'gemini-test-key',
            'groq_api_key' => 'groq-test-key',
            'openrouter_api_key' => 'openrouter-test-key',
        ])->assertRedirect();

        $this->assertSame('gemini-test-key', \App\Models\Setting::get('gemini_api_key'));
        $this->assertSame('groq-test-key', \App\Models\Setting::get('groq_api_key'));
        $this->assertSame('openrouter-test-key', \App\Models\Setting::get('openrouter_api_key'));
    }

    public function test_plain_admin_is_blocked_from_ai_providers_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.settings.ai-providers'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_super_admin_can_trigger_test_now(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
            ], 200),
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post(route('admin.settings.ai-providers.test', 'openrouter'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_unknown_provider_returns_404(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        // Literal path (not the route() helper) since the route's own regex
        // constraint on {provider} would reject an out-of-list value at URL
        // generation time already.
        $this->actingAs($superAdmin)->post('/admin/settings/ai-providers/gpt5/test')
            ->assertNotFound();
    }
}
