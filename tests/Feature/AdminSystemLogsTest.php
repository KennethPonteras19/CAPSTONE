<?php

namespace Tests\Feature;

use App\Http\Middleware\ActivityLogger;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Tests\AuthenticationTestCase;

class AdminSystemLogsTest extends AuthenticationTestCase
{
    private string $logDirectory;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logDirectory = sys_get_temp_dir().'/tmta-admin-logs-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->logDirectory);
        config([
            'logging.channels.single.path' => $this->logDirectory.'/laravel.log',
            'logging.channels.daily.path' => $this->logDirectory.'/laravel.log',
        ]);
        $this->withoutMiddleware(ActivityLogger::class);
        $this->admin = Admin::create([
            'name' => 'Admin Reader',
            'username' => 'admin.reader',
            'email' => 'reader@example.test',
            'password' => 'password123',
            'is_active' => true,
            'permissions' => ['system_logs'],
        ]);
        File::put($this->logDirectory.'/laravel.log', "[2026-09-22 13:00:00] testing.INFO: Current event\n");
        File::put($this->logDirectory.'/laravel-2026-09-21.log', "[2026-09-21 13:00:00] testing.WARNING: Archived event\n");
    }

    protected function tearDown(): void
    {
        if (isset($this->logDirectory)) {
            File::deleteDirectory($this->logDirectory);
        }

        parent::tearDown();
    }

    public function test_admin_can_read_and_filter_active_and_rotated_logs(): void
    {
        $this->actingAs($this->admin, 'admin')->get(route('admin.system.logs'))
            ->assertOk()->assertSee('Read-only access')
            ->assertSee('Current event')->assertSee('Archived event')
            ->assertDontSee('clearAllBtn')->assertDontSee('data-clear-url')
            ->assertDontSee('Delete All Records')->assertDontSee('/superadmin/system-logs');

        $this->getJson(route('admin.system.logs.stream', ['search' => 'ARCHIVED', 'severity' => 'warning']))
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('logs.0.event', 'Archived event');
    }

    public function test_admin_cannot_mutate_logs_or_use_superadmin_clear_endpoint(): void
    {
        $paths = File::glob($this->logDirectory.'/*.log');
        $before = array_map(fn ($path) => File::get($path), $paths);
        $this->actingAs($this->admin, 'admin');

        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            foreach (['/admin/system-logs', '/admin/system-logs/stream'] as $uri) {
                $this->json(strtoupper($method), $uri)->assertStatus(405);
            }
        }
        $this->deleteJson('/admin/system-logs/clear')->assertNotFound();
        $this->deleteJson('/admin/system-logs/1')->assertNotFound();
        $this->deleteJson(route('superadmin.system.logs.clear'))->assertUnauthorized();
        $this->get(route('admin.system.logs'))->assertOk();
        $this->getJson(route('admin.system.logs.stream'))->assertOk();

        $this->assertSame($before, array_map(fn ($path) => File::get($path), $paths));
    }

    public function test_admin_pagination_preserves_filters_and_uses_admin_links(): void
    {
        $lines = [];
        for ($i = 1; $i <= 51; $i++) {
            $lines[] = "[2026-09-22 13:00:00] testing.INFO: Activity {$i}";
        }
        File::put($this->logDirectory.'/laravel.log', implode("\n", $lines));
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.system.logs', ['page' => 2, 'search' => 'Activity', 'severity' => 'info']))
            ->assertOk()->assertSee('Page 2 of 3.')
            ->assertSee('search=Activity', false)->assertSee('severity=info', false)
            ->assertSee('/admin/system-logs?', false)->assertDontSee('/superadmin/system-logs');
        $response = $this->getJson(route('admin.system.logs.stream', ['page' => 3, 'severity' => 'info']))
            ->assertOk()->assertJsonPath('total', 51)->assertJsonCount(1, 'logs');
        $this->assertStringContainsString('/admin/system-logs?', $response->json('html'));
    }

    public function test_guests_and_other_roles_cannot_read_admin_logs(): void
    {
        $this->get(route('admin.system.logs'))->assertRedirect(route('login'));
        $this->getJson(route('admin.system.logs.stream'))->assertUnauthorized();
        foreach (['staff' => new Staff, 'customer' => new Customer] as $guard => $user) {
            $this->actingAs($user, $guard)->getJson(route('admin.system.logs'))->assertUnauthorized();
            $this->getJson(route('admin.system.logs.stream'))->assertUnauthorized();
        }
    }

    public function test_module_permission_is_required_and_controls_navigation(): void
    {
        $this->admin->update(['permissions' => ['settings']]);
        $this->actingAs($this->admin, 'admin')->getJson(route('admin.system.logs'))->assertForbidden();
        $this->getJson(route('admin.system.logs.stream'))->assertForbidden();
        $this->get(route('settings'))->assertOk()->assertDontSee(route('admin.system.logs'));
        $this->admin->update(['permissions' => ['settings', 'system_logs']]);
        $this->get(route('settings'))->assertOk()->assertSee(route('admin.system.logs'));
    }

    public function test_log_markup_is_escaped_and_invalid_filters_are_rejected(): void
    {
        File::put($this->logDirectory.'/laravel.log', '[2026-09-22 13:00:00] testing.INFO: <script>alert(1)</script>');
        $this->actingAs($this->admin, 'admin')->get(route('admin.system.logs'))
            ->assertOk()->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(', false);
        $response = $this->getJson(route('admin.system.logs.stream'))->assertOk();
        $this->assertStringContainsString('&lt;script&gt;', $response->json('html'));
        $this->assertStringNotContainsString('<script>alert(', $response->json('html'));
        $this->getJson(route('admin.system.logs.stream', ['page' => -1]))->assertUnprocessable();
        $this->getJson(route('admin.system.logs.stream', ['severity' => 'invalid']))->assertUnprocessable();
    }

    public function test_live_polling_does_not_generate_more_log_entries(): void
    {
        $this->withMiddleware(ActivityLogger::class);
        Log::spy();
        $this->actingAs($this->admin, 'admin')->getJson(route('admin.system.logs.stream'))->assertOk();
        Log::shouldNotHaveReceived('info');
    }
}
