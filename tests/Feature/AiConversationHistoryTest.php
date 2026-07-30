<?php

namespace Tests\Feature;

use App\Models\AiConversation;
use App\Models\User;
use App\Services\Agent\ToolCallOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiConversationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function mockOrchestrator(string $reply = 'Sure, here you go.'): void
    {
        $this->mock(ToolCallOrchestrator::class, function ($mock) use ($reply) {
            $mock->shouldReceive('run')->andReturn(['reply' => $reply, 'pending' => [], 'executed' => []]);
        });
    }

    public function test_admin_chat_creates_a_conversation_on_the_first_message(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->mockOrchestrator('Hello there!');

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('admin.ai.chat'), ['message' => 'What sold best today?']);

        $response->assertOk();
        $this->assertStringContainsString('"conversation_id"', $response->streamedContent());

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $admin->id,
            'context' => 'admin',
            'title' => 'What sold best today?',
        ]);

        $conversation = AiConversation::first();
        $this->assertSame('user', $conversation->messages[0]['role']);
        $this->assertSame('What sold best today?', $conversation->messages[0]['content']);
        $this->assertSame('Hello there!', $conversation->messages[1]['content']);
    }

    public function test_admin_chat_appends_to_an_existing_conversation_when_an_id_is_given(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $conversation = AiConversation::create([
            'user_id' => $admin->id,
            'context' => 'admin',
            'title' => 'Earlier question',
            'messages' => [
                ['kind' => 'text', 'role' => 'user', 'content' => 'Earlier question'],
                ['kind' => 'text', 'role' => 'assistant', 'content' => 'Earlier answer'],
            ],
            'last_message_at' => now()->subHour(),
        ]);

        $this->mockOrchestrator('Second answer.');

        $this->actingAs($admin)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('admin.ai.chat'), ['message' => 'Follow-up question', 'conversation_id' => $conversation->id])
            ->streamedContent();

        $conversation->refresh();
        $this->assertCount(4, $conversation->messages);
        $this->assertSame('Follow-up question', $conversation->messages[2]['content']);
        $this->assertSame('Earlier question', $conversation->title, 'title should not be overwritten once set');
    }

    public function test_admin_chat_ignores_a_conversation_id_belonging_to_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $theirConversation = AiConversation::create([
            'user_id' => $otherAdmin->id,
            'context' => 'admin',
            'messages' => [],
        ]);

        $this->mockOrchestrator();

        $this->actingAs($admin)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('admin.ai.chat'), ['message' => 'Hi', 'conversation_id' => $theirConversation->id])
            ->streamedContent();

        $this->assertDatabaseCount('ai_conversations', 2);
        $this->assertSame(0, AiConversation::find($theirConversation->id)->messages ? count(AiConversation::find($theirConversation->id)->messages) : 0);
    }

    public function test_staff_chat_persists_under_the_staff_context(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->mockOrchestrator('Sure!');

        $this->actingAs($staff)
            ->withHeader('Accept', 'text/event-stream')
            ->post(route('staff.ai.chat'), ['message' => 'How do I void a sale?']);

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $staff->id,
            'context' => 'staff',
        ]);
    }

    public function test_index_only_returns_the_current_users_own_conversations_for_the_given_context(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);

        AiConversation::create(['user_id' => $admin->id, 'context' => 'admin', 'title' => 'Mine', 'messages' => [], 'last_message_at' => now()]);
        AiConversation::create(['user_id' => $otherAdmin->id, 'context' => 'admin', 'title' => 'Not mine', 'messages' => [], 'last_message_at' => now()]);
        AiConversation::create(['user_id' => $admin->id, 'context' => 'staff', 'title' => 'Wrong context', 'messages' => [], 'last_message_at' => now()]);
        // last_message_at null (never actually chatted) shouldn't show up in the list.
        AiConversation::create(['user_id' => $admin->id, 'context' => 'admin', 'title' => null, 'messages' => []]);

        $response = $this->actingAs($admin)->getJson(route('ai.conversations.index', ['context' => 'admin']));

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['title' => 'Mine']);
    }

    public function test_show_is_forbidden_for_a_conversation_belonging_to_another_user(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $theirs = AiConversation::create(['user_id' => $otherAdmin->id, 'context' => 'admin', 'messages' => []]);

        $this->actingAs($admin)->getJson(route('ai.conversations.show', $theirs))->assertForbidden();
    }

    public function test_destroy_removes_only_the_owners_conversation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $conversation = AiConversation::create(['user_id' => $admin->id, 'context' => 'admin', 'messages' => []]);

        $this->actingAs($admin)->deleteJson(route('ai.conversations.destroy', $conversation))->assertOk();

        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation->id]);
    }
}
