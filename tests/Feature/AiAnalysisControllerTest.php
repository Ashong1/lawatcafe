<?php

namespace Tests\Feature;

use App\Models\AiAnalysisRun;
use App\Models\AiFinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiAnalysisControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function makeRun(string $narrative, array $findings): AiAnalysisRun
    {
        $run = AiAnalysisRun::create(['narrative' => $narrative, 'signal_count' => count($findings)]);

        foreach ($findings as $f) {
            AiFinding::create(array_merge(['run_id' => $run->id], $f));
        }

        return $run;
    }

    public function test_admin_sees_every_run_and_its_narrative(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->makeRun('Admin-only narrative here', [
            ['type' => 'repeat_mac_abuse', 'severity' => 'danger', 'summary' => 'Suspicious device', 'audience' => 'admin'],
        ]);
        $this->makeRun('Staff-relevant narrative here', [
            ['type' => 'low_stock_high_demand', 'severity' => 'warning', 'summary' => 'Milk running low', 'audience' => 'staff'],
        ]);

        $response = $this->actingAs($admin)->get(route('ai.analysis.index'));

        $response->assertOk();
        $response->assertSee('Admin-only narrative here');
        $response->assertSee('Staff-relevant narrative here');
        $response->assertSee('Suspicious device');
        $response->assertSee('Milk running low');
    }

    public function test_staff_only_sees_runs_with_staff_audience_findings(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->makeRun('Admin-only narrative here', [
            ['type' => 'repeat_mac_abuse', 'severity' => 'danger', 'summary' => 'Suspicious device', 'audience' => 'admin'],
        ]);
        $this->makeRun('Staff-relevant narrative here', [
            ['type' => 'low_stock_high_demand', 'severity' => 'warning', 'summary' => 'Milk running low', 'audience' => 'staff'],
        ]);

        $response = $this->actingAs($staff)->get(route('ai.analysis.index'));

        $response->assertOk();
        $response->assertDontSee('Admin-only narrative here');
        $response->assertDontSee('Suspicious device');
        $response->assertSee('Staff-relevant narrative here');
        $response->assertSee('Milk running low');
    }
}
