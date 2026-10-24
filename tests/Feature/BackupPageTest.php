<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class BackupPageTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = storage_path('framework/testing/backups');
        File::ensureDirectoryExists($this->dir);
        $this->mock(BackupService::class)->makePartial()->shouldReceive('dir')->andReturn($this->dir);
        File::put($this->dir.'/lawat_db_2026-10-20_233000.sql.gz', gzencode('-- MariaDB dump'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);
        parent::tearDown();
    }

    public function test_the_owner_sees_and_can_download_backups_staff_cannot(): void
    {
        $owner = User::factory()->create(['role' => 'admin']);

        $this->actingAs($owner)->get(route('security'))->assertOk()->assertSee('Oct 20, 2026')->assertSee('Restore');
        $this->actingAs($owner)->get(route('backups.download', 'lawat_db_2026-10-20_233000.sql.gz'))->assertOk();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->get(route('security'))->assertRedirect();
    }

    public function test_only_listed_backup_files_can_be_downloaded(): void
    {
        File::put(storage_path('framework/testing/secret.sql.gz'), 'x');

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('backups.download', '..%2Fsecret.sql.gz'))->assertNotFound();
    }

    public function test_a_wrong_password_restores_nothing(): void
    {
        Process::fake();

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('backups.restore', 'lawat_db_2026-10-20_233000.sql.gz'), ['password' => 'nope'])
            ->assertSessionHasErrors('password');

        Process::assertNothingRan();
    }

    public function test_a_backup_that_cannot_be_made_stops_the_restore(): void
    {
        Process::fake(['*' => Process::result(errorOutput: 'Access denied', exitCode: 2)]);

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->post(route('backups.restore', 'lawat_db_2026-10-20_233000.sql.gz'), ['password' => 'password'])
            ->assertSessionHas('error');

        Process::assertRanTimes(fn ($p) => str_contains(implode(' ', (array) $p->command), 'gunzip'), 0);
        $this->assertDatabaseHas('activity_logs', ['event' => 'backup_failed']);
    }
}
