<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Owner: "add all of the available free ai models as much as possible". Every
 * free, tool-capable, text-answering model in OpenRouter's catalog joins the
 * cascade after the admin's own list — as stand-ins, not extra attempts.
 */
class FreeModelDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    private function model(string $id, array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => $id,
            'pricing' => ['prompt' => '0', 'completion' => '0'],
            'supported_parameters' => ['tools'],
            'architecture' => ['input_modalities' => ['text'], 'output_modalities' => ['text']],
            'context_length' => 100000,
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Setting::set('ai_models_openrouter', json_encode(['mine/first:free']));
    }

    private function fakeCatalog(): void
    {
        Http::fake(['openrouter.ai/api/v1/models' => Http::response(['data' => [
            $this->model('mine/first:free'),
            $this->model('big/model:free', ['context_length' => 900000]),
            $this->model('small/model:free', ['context_length' => 8000]),
            $this->model('paid/model', ['pricing' => ['prompt' => '0.000001', 'completion' => '0.000002']]),
            $this->model('no/tools:free', ['supported_parameters' => ['temperature']]),
            $this->model('music/model', ['architecture' => ['output_modalities' => ['text', 'audio']]]),
            $this->model('nvidia/nemotron-3.5-content-safety:free'),
            $this->model('stealth/space-bunny-alpha'),
        ]])]);
    }

    public function test_every_usable_free_model_joins_after_the_admins_list(): void
    {
        $this->fakeCatalog();
        $this->assertSame(
            ['mine/first:free', 'big/model:free', 'small/model:free'],
            app(AIService::class)->cascadeModels()
        );
    }

    public function test_a_model_this_account_is_refused_drops_out(): void
    {
        $this->fakeCatalog();
        $ai = app(AIService::class);
        $key = (new \ReflectionMethod($ai, 'modelStatusCacheKey'))->invoke($ai, 'openrouter', 'big/model:free');
        Cache::put($key, ['status' => 'failed', 'reason' => 'forbidden', 'at' => now()->timestamp], now()->addWeek());

        $this->assertNotContains('big/model:free', $ai->cascadeModels());
    }

    public function test_the_admins_list_still_works_if_the_catalog_is_down(): void
    {
        Http::fake(['openrouter.ai/api/v1/models' => Http::response([], 500)]);

        $this->assertSame(['mine/first:free'], app(AIService::class)->cascadeModels());
    }

    public function test_a_403_is_recorded_as_forbidden(): void
    {
        $ai = app(AIService::class);

        $this->assertSame('forbidden', (new \ReflectionMethod($ai, 'failureReason'))->invoke($ai, 403));
        $this->assertSame('http_429', (new \ReflectionMethod($ai, 'failureReason'))->invoke($ai, 429));
    }
}
