<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiProviderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_provider_statuses_returns_openrouter_with_a_model_list(): void
    {
        $statuses = app(AIService::class)->getProviderStatuses();

        $this->assertArrayHasKey('openrouter', $statuses);
        $this->assertArrayNotHasKey('gemini', $statuses);
        $this->assertArrayNotHasKey('groq', $statuses);

        $this->assertNotEmpty($statuses['openrouter']['models']);
        foreach ($statuses['openrouter']['models'] as $model) {
            $this->assertSame('never_tested', $model['status']);
        }
        $this->assertFalse($statuses['openrouter']['circuit']['open']);
        // Catalog is the full default list, unfiltered against the active
        // list — with only a handful of models, filtering out every
        // currently-active one left nothing to suggest in the common case
        // (fresh install, no prior swaps). Per-model exclusion (don't
        // suggest replacing X with X) happens at the view layer.
        $this->assertNotEmpty($statuses['openrouter']['catalog']);
    }

    public function test_catalog_is_the_full_default_list_regardless_of_active_overrides(): void
    {
        Http::fake(['openrouter.ai/*' => Http::response([], 500)]);

        $defaultModel = app(AIService::class)->activeModels('openrouter')[0];
        app(AIService::class)->replaceModel('openrouter', $defaultModel, 'my-custom-model');

        $catalog = app(AIService::class)->getProviderStatuses()['openrouter']['catalog'];

        // Still offers every curated default, including the one just
        // replaced — a plain string list untouched by the active list.
        $this->assertContains($defaultModel, $catalog);
    }

    public function test_more_free_models_lists_the_openrouter_backlog(): void
    {
        $statuses = app(AIService::class)->getProviderStatuses();

        $this->assertContains('poolside/laguna-s-2.1:free', $statuses['openrouter']['more_free_models']);
    }

    public function test_test_provider_records_success_for_every_healthy_model(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
            ], 200),
        ]);

        $result = app(AIService::class)->testProvider('openrouter');

        $this->assertGreaterThan(0, $result['ok']);
        $this->assertSame(0, $result['failed']);

        $statuses = app(AIService::class)->getProviderStatuses();
        foreach ($statuses['openrouter']['models'] as $model) {
            $this->assertSame('ok', $model['status']);
        }
        $this->assertFalse($statuses['openrouter']['circuit']['open']);
    }

    public function test_test_provider_records_failure_and_reason_for_unreachable_models(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([], 500),
        ]);

        $result = app(AIService::class)->testProvider('openrouter');

        $this->assertSame(0, $result['ok']);
        $this->assertGreaterThan(0, $result['failed']);

        $statuses = app(AIService::class)->getProviderStatuses();
        foreach ($statuses['openrouter']['models'] as $model) {
            $this->assertSame('failed', $model['status']);
            $this->assertSame('http_500', $model['reason']);
        }
        $this->assertSame(1, $statuses['openrouter']['circuit']['failure_count']);
    }

    public function test_super_admin_can_view_ai_providers_page(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->get(route('admin.settings.ai-providers'))->assertOk();
    }

    public function test_super_admin_can_save_api_key(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($superAdmin)->post(route('admin.settings.ai-providers.update'), [
            'openrouter_api_key' => 'openrouter-test-key',
        ])->assertRedirect();

        $this->assertSame('openrouter-test-key', Setting::get('openrouter_api_key'));
    }

    public function test_plain_admin_can_view_the_ai_providers_page(): void
    {
        // The shop owner (role=admin) should be able to plug in their own
        // provider account, even though the status/testing/model-swap
        // actions further down the page stay super_admin-only.
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.settings.ai-providers'))->assertOk();
    }

    public function test_plain_admin_can_save_api_key(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->post(route('admin.settings.ai-providers.update'), [
            'openrouter_api_key' => 'owner-openrouter-key',
        ])->assertRedirect();

        $this->assertSame('owner-openrouter-key', Setting::get('openrouter_api_key'));
    }

    public function test_plain_admin_does_not_see_super_admin_only_actions(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get(route('admin.settings.ai-providers'));

        $response->assertOk();
        $response->assertDontSee('Test Now');
        $response->assertDontSee('Reset to default models');
    }

    public function test_plain_admin_is_blocked_from_testing_a_provider(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(route('admin.settings.ai-providers.test', 'openrouter'));

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

    public function test_active_models_falls_back_to_defaults_when_no_override_saved(): void
    {
        $models = app(AIService::class)->activeModels('openrouter');

        $this->assertContains('openrouter/free', $models);
    }

    public function test_replace_model_swaps_and_verifies_with_a_healthy_result(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
            ], 200),
        ]);

        $defaultModel = app(AIService::class)->activeModels('openrouter')[0];

        $result = app(AIService::class)->replaceModel('openrouter', $defaultModel, 'a-new-model:free');

        $this->assertTrue($result['replaced']);
        $this->assertTrue($result['new_model_ok']);

        $models = app(AIService::class)->activeModels('openrouter');
        $this->assertContains('a-new-model:free', $models);
        $this->assertNotContains($defaultModel, $models);
    }

    public function test_replace_model_reports_unhealthy_replacement(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([], 500),
        ]);

        $defaultModel = app(AIService::class)->activeModels('openrouter')[0];

        $result = app(AIService::class)->replaceModel('openrouter', $defaultModel, 'still-broken-model');

        $this->assertTrue($result['replaced']);
        $this->assertFalse($result['new_model_ok']);
    }

    public function test_replace_model_fails_for_a_model_not_in_the_list(): void
    {
        $result = app(AIService::class)->replaceModel('openrouter', 'nonexistent-model', 'a-new-model:free');

        $this->assertFalse($result['replaced']);
    }

    public function test_reset_models_restores_the_default_list(): void
    {
        $ai = app(AIService::class);
        $defaultModel = $ai->activeModels('openrouter')[0];

        Setting::set('ai_models_openrouter', json_encode(['some-custom-model']));
        $this->assertSame(['some-custom-model'], $ai->activeModels('openrouter'));

        $ai->resetModels('openrouter');

        $this->assertContains($defaultModel, $ai->activeModels('openrouter'));
    }

    public function test_super_admin_can_replace_a_failed_model_via_the_route(): void
    {
        Http::fake([
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'OK']]],
            ], 200),
        ]);

        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $defaultModel = app(AIService::class)->activeModels('openrouter')[0];

        $response = $this->actingAs($superAdmin)->post(
            route('admin.settings.ai-providers.models.replace', 'openrouter'),
            ['old_model' => $defaultModel, 'new_model' => 'a-new-model:free']
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertContains('a-new-model:free', app(AIService::class)->activeModels('openrouter'));
    }

    public function test_replace_route_flashes_error_for_unknown_old_model(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post(
            route('admin.settings.ai-providers.models.replace', 'openrouter'),
            ['old_model' => 'nonexistent-model', 'new_model' => 'a-new-model:free']
        );

        $response->assertSessionHas('error');
    }

    public function test_super_admin_can_reset_provider_models_via_the_route(): void
    {
        Setting::set('ai_models_openrouter', json_encode(['some-custom-model']));
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $response = $this->actingAs($superAdmin)->post(route('admin.settings.ai-providers.models.reset', 'openrouter'));

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertContains('openrouter/free', app(AIService::class)->activeModels('openrouter'));
    }

    public function test_plain_admin_is_blocked_from_replacing_models(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post(
            route('admin.settings.ai-providers.models.replace', 'openrouter'),
            ['old_model' => 'openrouter/free', 'new_model' => 'a-new-model:free']
        );

        $response->assertRedirect(route('dashboard'));
    }
}
