<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Agent\ChatImage;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Staff, admin and super_admin can attach a photo to a Barista AI message.
 * The photo goes to the model as an OpenAI-format image part for that turn,
 * only through image-capable models, and is never stored.
 */
class ChatImageAttachmentTest extends TestCase
{
    use RefreshDatabase;

    private array $sent = [];

    /** A real 1x1 PNG — GD isn't installed, so no generating one. */
    private function photo(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=';
    }

    private function captureOrchestrator(): void
    {
        $this->mock(ToolCallOrchestrator::class, function ($mock) {
            $mock->shouldReceive('run')->andReturnUsing(function ($messages) {
                $this->sent = $messages;

                return ['reply' => 'Got it.', 'pending' => [], 'executed' => []];
            });
        });
    }

    public static function roles(): array
    {
        return [
            'staff' => ['staff', 'staff.ai.chat'],
            'admin' => ['admin', 'admin.ai.chat'],
            'super_admin' => ['super_admin', 'admin.ai.chat'],
        ];
    }

    #[DataProvider('roles')]
    public function test_a_photo_reaches_the_model_as_an_image_part(string $role, string $route): void
    {
        $this->captureOrchestrator();
        $user = User::factory()->create(['role' => $role]);
        $photo = $this->photo();

        $this->actingAs($user)->withHeader('Accept', 'text/event-stream')
            ->post(route($route), ['message' => 'Receive this delivery', 'image' => $photo])
            ->assertOk()->streamedContent();

        $last = end($this->sent);
        $this->assertSame('user', $last['role']);
        $this->assertSame(['type' => 'text', 'text' => 'Receive this delivery'], $last['content'][0]);
        $this->assertSame(['type' => 'image_url', 'image_url' => ['url' => $photo]], $last['content'][1]);
        $this->assertStringContainsString('PHOTO ATTACHED', $this->sent[0]['content']);
    }

    public function test_a_photo_alone_with_no_text_is_accepted(): void
    {
        $this->captureOrchestrator();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->withHeader('Accept', 'text/event-stream')
            ->post(route('admin.ai.chat'), ['image' => $this->photo()])
            ->assertOk()->streamedContent();

        $this->assertSame('image_url', end($this->sent)['content'][1]['type']);
    }

    public function test_text_only_messages_are_unchanged(): void
    {
        $this->captureOrchestrator();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->withHeader('Accept', 'text/event-stream')
            ->post(route('admin.ai.chat'), ['message' => 'hello'])
            ->assertOk()->streamedContent();

        $this->assertSame('hello', end($this->sent)['content']);
        $this->assertStringNotContainsString('PHOTO ATTACHED', $this->sent[0]['content']);
    }

    public function test_a_non_image_payload_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $fake = 'data:image/png;base64,'.base64_encode('<?php echo "not a picture";');

        $this->actingAs($admin)->postJson(route('admin.ai.chat'), ['message' => 'x', 'image' => $fake])
            ->assertStatus(422)->assertJsonValidationErrors('image');
    }

    public function test_an_empty_message_with_no_photo_is_still_rejected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->postJson(route('admin.ai.chat'), [])
            ->assertStatus(422)->assertJsonValidationErrors('message');
    }

    public function test_history_records_a_marker_not_the_image(): void
    {
        $this->assertSame(ChatImage::HISTORY_MARKER.' look', ChatImage::historyText('look', 'data:image/jpeg;base64,xx'));
        $this->assertSame('look', ChatImage::historyText('look', null));
    }

    public function test_image_turns_only_cascade_through_image_capable_models(): void
    {
        Cache::put('openrouter_image_models', [
            'google/gemma-4-26b-a4b-it:free', 'openrouter/free', 'nvidia/nemotron-3.5-content-safety:free',
            'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
        ], 60);
        $ai = app(AIService::class);

        $models = $ai->imageCapableModels(['openrouter/free', 'openai/gpt-oss-20b:free', 'google/gemma-4-26b-a4b-it:free']);

        // Admin's own image model first, then known-good ones they didn't list.
        $this->assertSame(['google/gemma-4-26b-a4b-it:free', 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free'], $models);
        // openrouter/free answered a receipt with "User Safety: safe" — never a photo target.
        $this->assertNotContains('openrouter/free', $models);
        $this->assertNotContains('nvidia/nemotron-3.5-content-safety:free', $models);

        $this->assertTrue(AIService::hasImage([['role' => 'user', 'content' => ChatImage::userContent('x', 'data:image/jpeg;base64,xx')]]));
        $this->assertFalse(AIService::hasImage([['role' => 'user', 'content' => 'x']]));
    }
}
