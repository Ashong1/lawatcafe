<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Evaluators (Oct 28, R2 31:50): group the log by kind and trace records by date. */
class ActivityLogCategoriesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_event_belongs_to_exactly_one_category(): void
    {
        $grouped = array_merge(...array_values(ActivityLog::CATEGORIES));

        $this->assertEqualsCanonicalizing(array_keys(ActivityLog::LABELS), $grouped);
    }

    public function test_the_log_filters_by_category_and_date(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ActivityLog::record('signed_in', $admin, 'login-today');
        ActivityLog::record('backup_made', null, 'backup-today');
        ActivityLog::record('signed_in', $admin, 'login-last-week');
        ActivityLog::latest('id')->first()->forceFill(['created_at' => now()->subWeek()])->save();

        $this->actingAs($admin)->get(route('security', ['category' => 'Sign-ins', 'from' => today()->toDateString()]))
            ->assertOk()->assertSee('login-today')->assertDontSee('backup-today')->assertDontSee('login-last-week')
            ->assertSee('Stock History');
    }
}
