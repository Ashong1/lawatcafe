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
        $this->assertSame(2, substr_count($blade, '<p x-show="open"'));
    }

    public function test_streamed_text_is_revealed_at_a_steady_pace(): void
    {
        $js = file_get_contents(resource_path('js/agent-chat.js'));

        $this->assertStringContainsString('const typeOut = ', $js);
        $this->assertStringNotContainsString('assistantEntry.content += event.text;', $js);
    }
}
