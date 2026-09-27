<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\AIService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Regression: the guest prompt carried the menu and Wi-Fi prices but no
 * store hours, so a portal guest asking "are you open now?" got "I'm not
 * sure about the shop's hours" even though the owner had them configured.
 */
class AIServiceGuestStoreInfoTest extends TestCase
{
    use RefreshDatabase;

    private function setHours(string $open, string $close): void
    {
        Setting::set('store_open_time', $open);
        Setting::set('store_close_time', $close);
    }

    public function test_guest_prompt_includes_store_hours_and_open_state(): void
    {
        $this->setHours('08:00', '22:00');
        Carbon::setTestNow(Carbon::parse('2026-09-27 10:53', 'Asia/Manila'));

        $prompt = app(AIService::class)->buildGuestSystemPrompt();

        $this->assertStringContainsString('Hours: 8:00 AM to 10:00 PM', $prompt);
        $this->assertStringContainsString('shop is OPEN', $prompt);
    }

    public function test_guest_prompt_does_not_assume_the_guest_is_connected(): void
    {
        $prompt = app(AIService::class)->buildGuestSystemPrompt();

        $this->assertStringContainsString('may not be connected yet', $prompt);
        $this->assertStringContainsString('Connect tab', $prompt);
    }

    public static function openStateProvider(): array
    {
        return [
            'mid-day, normal hours' => ['08:00', '22:00', '12:00', true],
            'exactly at opening' => ['08:00', '22:00', '08:00', true],
            'exactly at closing' => ['08:00', '22:00', '22:00', false],
            'before opening' => ['08:00', '22:00', '07:59', false],
            'late night, past-midnight hours' => ['16:00', '02:00', '01:30', true],
            'evening, past-midnight hours' => ['16:00', '02:00', '20:00', true],
            'morning, past-midnight hours' => ['16:00', '02:00', '09:00', false],
        ];
    }

    #[DataProvider('openStateProvider')]
    public function test_open_state_is_computed_correctly(string $open, string $close, string $at, bool $expectOpen): void
    {
        $this->setHours($open, $close);

        $info = app(AIService::class)->getStoreInfoContext(Carbon::parse("2026-09-27 {$at}", 'Asia/Manila'));

        $this->assertStringContainsString($expectOpen ? 'shop is OPEN' : 'shop is CLOSED', $info);
    }
}
