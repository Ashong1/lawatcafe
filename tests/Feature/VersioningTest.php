<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The version was maintained by hand in three places — composer.json and the
 * sidebar badge in each of the two shells — and bumped with a three-way sed on
 * every commit. That works right up until one of them is missed, at which point
 * the number a user reads off the sidebar disagrees with what is deployed and
 * there is nothing to catch it.
 *
 * composer.json is now the only source; config/app.php reads it and the views
 * read the config. These assert that it stays that way, and that the format
 * stays parseable, since docs/VERSIONING.md gives each segment a meaning.
 */
class VersioningTest extends TestCase
{
    use RefreshDatabase;

    private function composerVersion(): string
    {
        return json_decode(file_get_contents(base_path('composer.json')), true)['version'];
    }

    public function test_the_app_version_comes_from_composer_json(): void
    {
        $this->assertSame($this->composerVersion(), config('app.version'));
    }

    public function test_the_version_has_four_numeric_segments(): void
    {
        $this->assertMatchesRegularExpression(
            '/^\d+\.\d+\.\d+\.\d+$/',
            $this->composerVersion(),
            'Version must be MAJOR.MINOR.PATCH.BUILD — see docs/VERSIONING.md.'
        );
    }

    /**
     * The badge is the only place most people ever see a version, so it has to
     * be the real one rather than a string that was correct at the time.
     */
    public function test_both_shells_render_the_real_version(): void
    {
        $version = $this->composerVersion();

        $admin = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/dashboard')->assertOk()->getContent();
        $this->assertStringContainsString("Lawa't Kape v{$version}", $admin);

        $staff = $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->get('/staff-dashboard')->assertOk()->getContent();
        $this->assertStringContainsString("Lawa't Kape v{$version}", $staff);
    }

    /**
     * The regression that matters. A hard-coded version in a view renders
     * correctly on the day it is written and silently rots afterwards — which
     * is exactly the state this replaced.
     */
    public function test_no_view_hard_codes_a_version_string(): void
    {
        $offenders = [];
        $views = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views')));

        foreach ($views as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            // The `v` prefix is required, not optional: a bare four-part number
            // also describes every IPv4 address, and this app's network settings
            // views are full of them as placeholders. Versions are always
            // written "v1.2.3.4" here, so that is what to look for.
            if (preg_match('/\bv\d+\.\d+\.\d+\.\d+\b/', file_get_contents($file->getPathname()), $m)) {
                $offenders[] = $file->getFilename().': '.$m[0];
            }
        }

        $this->assertSame([], $offenders, 'Views must read config("app.version"), not a literal.');
    }

    /** A release with no entry is a number nobody can look up. */
    public function test_the_changelog_documents_the_current_minor(): void
    {
        [$major, $minor] = explode('.', $this->composerVersion());

        $this->assertStringContainsString(
            "## {$major}.{$minor}.",
            file_get_contents(base_path('CHANGELOG.md')),
            "CHANGELOG.md has no entry for the {$major}.{$minor} line."
        );
    }
}
