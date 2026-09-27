<?php

namespace Tests\Feature;

use App\Models\AiFeedback;
use App\Models\AiLesson;
use App\Models\User;
use App\Notifications\SystemAlert;
use App\Services\Agent\CapabilityGap;
use App\Services\Agent\LessonLibrary;
use App\Services\Agent\ToolCallOrchestrator;
use App\Services\AIService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * "If the AI agent cannot do it, let it learn it itself." A reply that says
 * "I can't" becomes a capability gap; ai:resolve-gaps turns each gap into a
 * verified skill, a page pointer, or a drafted tool request.
 */
class CapabilityGapLearningTest extends TestCase
{
    use RefreshDatabase;

    private const REAL_REPLY = "I'm able to block devices on the Wi-Fi network (by MAC address), but I don't have a tool to block individual websites or domains.";

    public function test_the_reply_from_the_report_is_recognised_as_a_gap(): void
    {
        $this->assertTrue(CapabilityGap::looksLikeGap(self::REAL_REPLY));
        // Exactly as the model wrote it live: curly apostrophes, non-breaking hyphen.
        $this->assertTrue(CapabilityGap::looksLikeGap("I\u{2019}m able to block devices on the Wi\u{2011}Fi network (by MAC address), but I don\u{2019}t have a tool to block individual websites or domains."));
        $this->assertFalse(CapabilityGap::looksLikeGap('Today\'s sales are PHP 4,000.'));
    }

    private function chat(string $role, string $route, array $result): void
    {
        $this->mock(ToolCallOrchestrator::class, fn ($m) => $m->shouldReceive('run')->andReturn($result));

        $this->actingAs(User::factory()->create(['role' => $role]))
            ->withHeader('Accept', 'text/event-stream')
            ->post(route($route), ['message' => 'can you block these sites'])
            ->assertOk()->streamedContent();
    }

    public function test_an_admin_i_cant_reply_is_recorded(): void
    {
        $this->chat('admin', 'admin.ai.chat', ['reply' => self::REAL_REPLY, 'pending' => [], 'executed' => []]);

        $gap = AiFeedback::where('signal', AiFeedback::SIGNAL_CAPABILITY_GAP)->sole();
        $this->assertSame('admin', $gap->audience);
        $this->assertSame('can you block these sites', $gap->user_message);
    }

    public function test_a_turn_where_a_tool_ran_is_not_a_gap(): void
    {
        $this->chat('admin', 'admin.ai.chat', ['reply' => "I can't block that one, but I blocked the rest.", 'pending' => [], 'executed' => [['tool' => 'blockSites']]]);

        $this->assertSame(0, AiFeedback::where('signal', AiFeedback::SIGNAL_CAPABILITY_GAP)->count());
    }

    public function test_guest_chats_never_teach_the_assistant(): void
    {
        $this->mock(ToolCallOrchestrator::class, fn ($m) => $m->shouldReceive('run')->andReturn(['reply' => self::REAL_REPLY, 'pending' => [], 'executed' => []]));

        $this->withHeader('Accept', 'text/event-stream')->post(route('portal.chat'), ['message' => 'block sites'])->streamedContent();

        $this->assertSame(0, AiFeedback::where('signal', AiFeedback::SIGNAL_CAPABILITY_GAP)->count());
    }

    private function gap(string $audience = 'admin'): AiFeedback
    {
        return AiFeedback::create([
            'audience' => $audience, 'signal' => AiFeedback::SIGNAL_CAPABILITY_GAP, 'sentiment' => -1,
            'user_message' => 'can you block these sites', 'assistant_reply' => self::REAL_REPLY,
        ]);
    }

    private function resolutions(array $resolutions): void
    {
        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('resolveCapabilityGaps')->andReturn($resolutions));
    }

    public function test_a_verified_skill_is_learned_and_reaches_the_prompt(): void
    {
        $this->gap();
        $this->resolutions([[
            'gap' => 0, 'resolution' => 'skill', 'trigger' => 'block websites for guests', 'title' => 'Block websites',
            'steps' => [['tool' => 'listBlockedSites', 'how' => 'check what is already blocked'], ['tool' => 'blockSites', 'how' => 'pass every domain the user listed or showed']],
        ]]);

        $this->artisan('ai:resolve-gaps')->assertSuccessful();

        $skill = AiLesson::where('kind', AiLesson::KIND_SKILL)->sole();
        $this->assertSame(AiLesson::STATUS_APPROVED, $skill->status);
        $this->assertNotNull(AiFeedback::sole()->distilled_at);

        $block = app(LessonLibrary::class)->promptBlockFor('admin');
        $this->assertStringContainsString('LEARNED SKILLS', $block);
        $this->assertStringContainsString('call blockSites', $block);
    }

    public function test_a_skill_naming_a_tool_that_does_not_exist_is_rejected(): void
    {
        $this->gap();
        $this->resolutions([[
            'gap' => 0, 'resolution' => 'skill', 'trigger' => 'block websites', 'title' => 'x',
            'steps' => [['tool' => 'blockSites', 'how' => ''], ['tool' => 'runShellCommand', 'how' => 'invented']],
        ]]);

        $this->artisan('ai:resolve-gaps')->assertSuccessful();

        $this->assertSame(0, AiLesson::count());
    }

    public function test_a_staff_skill_cannot_use_admin_only_tools(): void
    {
        $this->gap('staff');
        $this->resolutions([['gap' => 0, 'resolution' => 'skill', 'trigger' => 'block websites', 'title' => 'x', 'steps' => [['tool' => 'blockSites', 'how' => '']]]]);

        $this->artisan('ai:resolve-gaps')->assertSuccessful();

        $this->assertSame(0, AiLesson::count());
    }

    public function test_a_page_pointer_must_be_a_real_page_the_role_can_open(): void
    {
        $this->gap('staff');
        $this->resolutions([
            ['gap' => 0, 'resolution' => 'page', 'trigger' => 'block websites', 'title' => 'x', 'page_route' => 'network.site-blocking'],
        ]);

        $this->artisan('ai:resolve-gaps')->assertSuccessful();
        $this->assertSame(0, AiLesson::count(), 'Site Blocking is admin-only; staff must not be sent there.');

        $this->gap('admin');
        $this->resolutions([
            ['gap' => 0, 'resolution' => 'page', 'trigger' => 'change opening hours', 'title' => 'Hours', 'page_route' => 'admin.settings.store', 'page_instruction' => 'edit Opening Time.'],
        ]);
        $this->artisan('ai:resolve-gaps')->assertSuccessful();

        $lesson = AiLesson::sole();
        $this->assertSame(AiLesson::KIND_LESSON, $lesson->kind);
        $this->assertStringContainsString('/settings/store', $lesson->body);
    }

    public function test_a_tool_request_waits_for_super_admin_and_never_enters_a_prompt(): void
    {
        Notification::fake();
        $owner = User::factory()->create(['role' => 'super_admin']);
        $this->gap();
        $this->resolutions([[
            'gap' => 0, 'resolution' => 'tool_request', 'trigger' => 'schedule a staff shift', 'title' => 'x',
            'tool_request' => ['name' => 'scheduleShift', 'description' => 'Create a shift.', 'inputs' => [['name' => 'staff', 'type' => 'string', 'description' => 'who']], 'uses' => 'Shift model'],
        ]]);

        $this->artisan('ai:resolve-gaps')->assertSuccessful();

        $request = AiLesson::sole();
        $this->assertSame(AiLesson::KIND_TOOL_REQUEST, $request->kind);
        $this->assertSame(AiLesson::STATUS_PROPOSED, $request->status);
        $this->assertSame('super_admin', $request->audience);
        Notification::assertSentTo($owner, SystemAlert::class);

        $request->update(['status' => AiLesson::STATUS_APPROVED]);
        LessonLibrary::forget('super_admin');
        $this->assertStringNotContainsString('scheduleShift', app(LessonLibrary::class)->promptBlockFor('super_admin'));
    }

    public function test_an_outage_leaves_gaps_for_the_next_run(): void
    {
        $this->gap();
        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('resolveCapabilityGaps')->andReturn(null));

        $this->artisan('ai:resolve-gaps')->assertSuccessful();

        $this->assertNull(AiFeedback::sole()->distilled_at);
    }

    public function test_ai_learn_does_not_consume_gaps(): void
    {
        $this->gap();
        $this->mock(AIService::class, fn ($m) => $m->shouldReceive('distilLessons')->andReturn([]));

        $this->artisan('ai:learn');

        $this->assertNull(AiFeedback::sole()->distilled_at);
    }
}
