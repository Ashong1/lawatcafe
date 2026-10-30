<?php

namespace Tests\Unit;

use App\Services\Agent\ScopeGuard;
use PHPUnit\Framework\TestCase;

/** Owner (Oct 30): staff/owner chat must not answer things unrelated to the system, bullet-proof. */
class ScopeGuardTest extends TestCase
{
    public function test_obvious_off_topic_messages_are_declined_before_the_ai(): void
    {
        foreach (['what is 3 plus 3', '3+3', '12 * 7?', 'who won the 2022 world cup?', 'write me a poem about rain',
            'translate good morning to French', 'solve x^2 = 9', 'what is the capital of Japan', 'write a python function'] as $m) {
            $this->assertTrue(ScopeGuard::declineBefore($m, []), $m);
        }
    }

    public function test_shop_questions_pass(): void
    {
        foreach (['how much did we sell today?', 'magkano benta kahapon?', 'If we sell 3 lattes at 120 pesos each, how much is that?',
            '3 x ₱120', 'who is using the most data on the wifi?', 'block the device that is downloading', 'what is the price of a latte?',
            'how do I add a new staff account?', 'is the internet down?'] as $m) {
            $this->assertFalse(ScopeGuard::declineBefore($m, []), $m);
            $this->assertFalse(ScopeGuard::replaceAfter($m, [], ['reply' => 'Sure.']), $m);
        }
    }

    public function test_a_follow_up_in_a_shop_conversation_passes(): void
    {
        $this->assertFalse(ScopeGuard::replaceAfter('and yesterday?', ['how much were sales today?'], ['reply' => '₱4,200.']));
        $this->assertFalse(ScopeGuard::declineBefore('what about 3 plus 3 more?', ['how many lattes are left in stock?']));
    }

    public function test_an_off_topic_answer_without_tools_is_replaced(): void
    {
        $this->assertTrue(ScopeGuard::replaceAfter('who painted the mona lisa', [], ['reply' => 'Leonardo da Vinci.']));
        // A tool ran: the AI was working on the shop, leave it.
        $this->assertFalse(ScopeGuard::replaceAfter('who painted the mona lisa', [], ['reply' => 'x', 'executed' => [['tool' => 'getSalesSummary']]]));
    }

    public function test_greetings_and_questions_about_the_assistant_pass(): void
    {
        foreach (['hi, are you alive?', 'thanks!', 'what can you do?', 'salamat', 'good morning'] as $m) {
            $this->assertFalse(ScopeGuard::declineBefore($m, []), $m);
            $this->assertFalse(ScopeGuard::replaceAfter($m, [], ['reply' => 'Hello!']), $m);
        }
    }
}
