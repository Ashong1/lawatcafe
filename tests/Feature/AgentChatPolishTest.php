<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Owner (Oct 30): the reply should flow, and the tool's raw result stays folded behind "View more". */
class AgentChatPolishTest extends TestCase
{
    public function test_the_action_result_is_folded_behind_view_more(): void
    {
        $blade = file_get_contents(resource_path('views/components/agent-chat.blade.php'));

        $this->assertSame(2, substr_count($blade, "x-text=\"open ? 'View less' : 'View more'\""));
        // Owner's 3rd video: three checks meant three "View more" links. One card per run now.
        $this->assertSame(2, substr_count($blade, "x-if=\"msg.kind === 'executed' && history[index - 1]?.kind !== 'executed'\""));
        $this->assertSame(2, substr_count($blade, 'x-for="(a, n) in actionRun(index)"'));
    }

    public function test_streamed_text_is_revealed_at_a_steady_pace(): void
    {
        $js = file_get_contents(resource_path('js/agent-chat.js'));

        $this->assertStringContainsString('const typeOut = ', $js);
        $this->assertStringNotContainsString('assistantEntry.content += event.text;', $js);
        $this->assertStringContainsString('window.innerWidth >= 1024 ? 820 : 550', $js);
    }

    /** Owner (Oct 30): arrow up/down brings back earlier messages to send again. */
    public function test_arrow_keys_recall_earlier_messages(): void
    {
        $blade = file_get_contents(resource_path('views/components/agent-chat.blade.php'));
        $js = file_get_contents(resource_path('js/agent-chat.js'));

        $this->assertSame(2, substr_count($blade, '@keydown.up="recall(-1, $event)" @keydown.down="recall(1, $event)"'));
        $this->assertStringContainsString('recall(step, e)', $js);
    }
}
