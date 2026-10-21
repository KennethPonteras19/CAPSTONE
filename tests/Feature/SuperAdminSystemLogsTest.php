<?php

namespace Tests\Feature;

use App\Http\Middleware\ActivityLogger;
use App\Models\SuperAdmin;
use Illuminate\Support\Facades\File;
use Tests\AuthenticationTestCase;

class SuperAdminSystemLogsTest extends AuthenticationTestCase
{
    private string $logDirectory;

    private SuperAdmin $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDirectory = sys_get_temp_dir().'/tmta-log-tests-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->logDirectory);
        config([
            'logging.channels.single.path' => $this->logDirectory.'/laravel.log',
            'logging.channels.daily.path' => $this->logDirectory.'/laravel.log',
        ]);
        $this->withoutMiddleware(ActivityLogger::class);
        $this->superAdmin = SuperAdmin::create([
            'name' => 'Log Administrator',
            'username' => 'log.admin',
            'email' => 'logs@example.test',
            'password' => bcrypt('password123'),
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        if (isset($this->logDirectory)) {
            File::deleteDirectory($this->logDirectory);
        }

        parent::tearDown();
    }

    private function seedHistory(int $count = 625): void
    {
        $lines = [];
        for ($i = 1; $i <= $count; $i++) {
            $timestamp = now()->startOfDay()->addSeconds($i)->format('Y-m-d H:i:s');
            $severity = $i === 1 ? 'WARNING' : 'INFO';
            $event = $i === 1 ? 'Oldest unique event' : "Activity {$i}";
            $lines[] = "[{$timestamp}] testing.{$severity}: {$event}";
        }
        File::put($this->logDirectory.'/laravel.log', implode("\n", $lines)."\n");
    }

    public function test_numbered_pages_include_history_beyond_the_old_limit(): void
    {
        $this->seedHistory();
        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.system.logs'))
            ->assertOk()
            ->assertSee('Page 1 of 25.')
            ->assertSee('System log pages')
            ->assertViewHas('logs', fn ($logs) => $logs->count() === 25 && $logs->total() === 625);

        $this->getJson(route('superadmin.system.logs.stream', ['page' => 25]))
            ->assertOk()
            ->assertJsonPath('current_page', 25)
            ->assertJsonPath('total', 625)
            ->assertJsonCount(25, 'logs')
            ->assertJsonPath('logs.24.event', 'Oldest unique event');
    }

    public function test_search_and_severity_filter_the_entire_history_before_pagination(): void
    {
        $this->seedHistory();
        $this->actingAs($this->superAdmin, 'superadmin')
            ->getJson(route('superadmin.system.logs.stream', ['search' => 'OLDEST UNIQUE', 'severity' => 'warning']))
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('logs.0.event', 'Oldest unique event');
    }

    public function test_page_links_preserve_filters_and_page_requests_do_not_truncate_logs(): void
    {
        $this->seedHistory();
        $before = File::get($this->logDirectory.'/laravel.log');
        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.system.logs', ['page' => 2, 'search' => 'Activity', 'severity' => 'info']))
            ->assertOk()
            ->assertSee('search=Activity', false)
            ->assertSee('severity=info', false)
            ->assertSee('Page 2 of 25.');
        $this->assertSame($before, File::get($this->logDirectory.'/laravel.log'));
    }

    public function test_rotated_history_is_included_and_sorted_using_24_hour_time(): void
    {
        File::put($this->logDirectory.'/laravel-2026-09-21.log', "[2026-09-21 23:00:00] testing.WARNING: Archived event\n");
        File::put($this->logDirectory.'/laravel.log', implode("\n", [
            '[2026-09-22 09:00:00] testing.INFO: Morning event',
            '[2026-09-22 13:00:00] testing.CRITICAL: Afternoon event',
            '#0 Ignore stack trace line',
        ]));

        $this->actingAs($this->superAdmin, 'superadmin')
            ->getJson(route('superadmin.system.logs.stream'))
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('logs.0.event', 'Afternoon event')
            ->assertJsonPath('logs.2.event', 'Archived event');
        $this->getJson(route('superadmin.system.logs.stream', ['severity' => 'danger']))
            ->assertOk()->assertJsonPath('total', 1);
    }

    public function test_empty_and_out_of_range_pages_have_valid_pagination(): void
    {
        $this->actingAs($this->superAdmin, 'superadmin')
            ->getJson(route('superadmin.system.logs.stream', ['page' => 999]))
            ->assertOk()->assertJsonPath('current_page', 1)->assertJsonPath('total', 0);

        $this->seedHistory(26);
        $this->getJson(route('superadmin.system.logs.stream', ['page' => 999]))
            ->assertOk()->assertJsonPath('current_page', 2)->assertJsonCount(1, 'logs');
        $this->getJson(route('superadmin.system.logs.stream', ['page' => -1]))
            ->assertUnprocessable();
        $this->getJson(route('superadmin.system.logs.stream', ['search' => ['invalid']]))
            ->assertUnprocessable();
    }

    public function test_log_content_is_escaped_in_both_rendered_views(): void
    {
        File::put($this->logDirectory.'/laravel.log', '[2026-09-22 13:00:00] testing.INFO: <script>alert("test")</script>');
        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.system.logs'))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false);
        $response = $this->getJson(route('superadmin.system.logs.stream'))->assertOk();
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
        $this->assertStringNotContainsString('<script>alert(', $response->json('html'));
    }

    public function test_explicit_clear_clears_active_and_rotated_logs_only(): void
    {
        $this->seedHistory();
        File::put($this->logDirectory.'/laravel-2026-09-21.log', '[2026-09-21 13:00:00] testing.INFO: Archived');
        File::put($this->logDirectory.'/unrelated.log', 'Keep this');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->deleteJson(route('superadmin.system.logs.clear'))->assertOk()->assertJsonPath('success', true);

        $this->assertSame('', File::get($this->logDirectory.'/laravel.log'));
        $this->assertSame('', File::get($this->logDirectory.'/laravel-2026-09-21.log'));
        $this->assertSame('Keep this', File::get($this->logDirectory.'/unrelated.log'));
    }

    public function test_log_endpoints_require_superadmin_authentication(): void
    {
        $this->get(route('superadmin.system.logs'))->assertRedirect(route('login'));
        $this->getJson(route('superadmin.system.logs.stream'))->assertUnauthorized();
        $this->deleteJson(route('superadmin.system.logs.clear'))->assertUnauthorized();
    }
}
