<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Sale;
use App\Models\Staff;
use App\Services\SalesForecastService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminForecastingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Match the authentication suite's isolated setup: the existing purchase
        // item migrations alter a table they never create. Forecasts do not use it.
        foreach (glob(database_path('migrations/*.php')) as $path) {
            if (! str_contains(basename($path), 'purchase_order_items')) {
                (require $path)->up();
            }
        }
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00:00'));
    }

    private function sale(string $date, float $amount, string $status = 'Completed'): void
    {
        $sale = new Sale([
            'receipt_no' => 'TEST', 'staff_name' => 'Cashier', 'item_name' => 'Cement',
            'qty' => 1, 'total_amount' => $amount, 'status' => $status,
            'payment_status' => 'Unpaid', 'paid_amount' => 0,
        ]);
        $sale->created_at = $date;
        $sale->updated_at = $date;
        $sale->save();
    }

    private function admin(): Admin
    {
        return Admin::create(['name' => 'Admin', 'username' => 'forecast-admin',
            'email' => 'forecast@example.test', 'password' => 'password123', 'is_active' => true]);
    }

    public static function historyPeriods(): array
    {
        return [
            ['last_7_days', '2026-09-16', '2026-09-22', 7, 7],
            ['last_month', '2026-08-23', '2026-09-22', 31, 31],
            ['last_quarter', '2026-06-23', '2026-09-22', 14, 92],
            ['last_year', '2025-09-23', '2026-09-22', 13, 365],
        ];
    }

    #[DataProvider('historyPeriods')]
    public function test_history_uses_completed_days_and_excludes_today_and_future_sales(
        string $period, string $start, string $end, int $buckets, int $days
    ): void {
        $this->sale(CarbonImmutable::parse($start)->subSecond()->toDateTimeString(), 999);
        $this->sale($start.' 00:00:00', 100);
        $this->sale($start.' 12:00:00', 50.25);
        $this->sale($end.' 23:59:59', 200);
        $this->sale(CarbonImmutable::parse($end)->addDay()->toDateTimeString(), 999);
        foreach (['Cancelled', 'Held', 'Quotation'] as $status) {
            $this->sale($start.' 14:00:00', 999, $status);
        }

        $history = app(SalesForecastService::class)->dashboard($period, 'next_7_days')['history'];
        $this->assertSame($start, $history['start']);
        $this->assertSame($end, $history['end']);
        $this->assertCount($buckets, $history['values']);
        $this->assertEquals(350.25, $history['total']);
        $this->assertEquals(round(350.25 / $days, 2), $history['daily_average']);
        $this->assertContains(0.0, $history['values']);
    }

    public function test_forecasts_follow_weekday_patterns_and_ignore_today_future_and_old_sales(): void
    {
        $today = CarbonImmutable::today();
        for ($date = $today->subDays(56); $date->lt($today); $date = $date->addDay()) {
            $this->sale($date->toDateTimeString(), ($date->dayOfWeek + 1) * 100);
        }
        $this->sale($today->subDays(57)->toDateTimeString(), 999999);
        $this->sale($today->toDateTimeString(), 999999);
        $this->sale($today->addDay()->toDateTimeString(), 999999);
        $this->sale($today->subDay()->toDateTimeString(), 999999, 'Cancelled');

        $service = app(SalesForecastService::class);
        $forecast = $service->dashboard('last_7_days', 'next_7_days')['forecast'];
        $this->assertTrue($forecast['available']);
        $this->assertSame(56, $forecast['training_days']);
        $this->assertSame('2026-09-24', $forecast['start']);
        $this->assertSame('2026-09-30', $forecast['end']);
        $this->assertEquals([500, 600, 700, 100, 200, 300, 400], $forecast['values']);
        $this->assertEquals(2800, $forecast['total']);
        $this->assertSame($forecast, $service->dashboard('last_year', 'next_7_days')['forecast']);

        $month = $service->dashboard('last_month', 'next_month')['forecast'];
        $this->assertSame('2026-09-24', $month['start']);
        $this->assertSame('2026-10-23', $month['end']);
        $this->assertCount(30, $month['values']);
        $this->assertEquals(12300, $month['total']);
        $this->assertEquals(round(12300 / 30, 2), $month['daily_average']);
    }

    public function test_sparse_sales_include_zero_days_in_weekday_averages(): void
    {
        $this->sale('2026-09-09 12:00:00', 140);
        $forecast = app(SalesForecastService::class)->dashboard('last_7_days', 'next_7_days')['forecast'];
        $this->assertTrue($forecast['available']);
        $this->assertSame(14, $forecast['training_days']);
        $this->assertEquals([0, 0, 0, 0, 0, 0, 70], $forecast['values']);
        $this->assertEquals(70, $forecast['total']);
    }

    public function test_empty_and_insufficient_history_do_not_display_fake_predictions(): void
    {
        $service = app(SalesForecastService::class);
        $empty = $service->dashboard('last_7_days', 'next_month');
        $this->assertFalse($empty['history']['has_sales']);
        $this->assertEquals(0, $empty['history']['total']);
        $this->assertFalse($empty['forecast']['available']);
        $this->assertNull($empty['forecast']['total']);
        $this->assertSame(array_fill(0, 30, null), $empty['forecast']['values']);

        $this->sale('2026-09-10 12:00:00', 200);
        $insufficient = $service->dashboard('last_7_days', 'next_7_days')['forecast'];
        $this->assertSame(13, $insufficient['training_days']);
        $this->assertFalse($insufficient['available']);
        $this->assertNull($insufficient['daily_average']);
    }

    public static function calendarBoundaries(): array
    {
        return [
            ['2026-01-01', 'last_quarter', '2025-10-01', '2025-12-31', '2026-01-02', '2026-02-01', 31],
            ['2028-03-01', 'last_month', '2028-02-01', '2028-02-29', '2028-03-02', '2028-04-01', 31],
            ['2028-01-31', 'last_year', '2027-01-31', '2028-01-30', '2028-02-01', '2028-02-29', 29],
            ['2026-12-31', 'last_month', '2026-11-30', '2026-12-30', '2027-01-01', '2027-01-31', 31],
            ['2028-02-29', 'last_year', '2027-02-28', '2028-02-28', '2028-03-01', '2028-03-31', 31],
            ['2026-03-31', 'last_month', '2026-02-28', '2026-03-30', '2026-04-01', '2026-04-30', 30],
        ];
    }

    #[DataProvider('calendarBoundaries')]
    public function test_calendar_boundaries_and_leap_years(
        string $today, string $period, string $start, string $end,
        string $nextStart, string $nextEnd, int $days
    ): void {
        $this->travelTo(CarbonImmutable::parse($today));
        $data = app(SalesForecastService::class)->dashboard($period, 'next_month');
        $this->assertSame($start, $data['history']['start']);
        $this->assertSame($end, $data['history']['end']);
        $this->assertSame($nextStart, $data['forecast']['start']);
        $this->assertSame($nextEnd, $data['forecast']['end']);
        $this->assertCount($days, $data['forecast']['values']);
    }

    public function test_admin_can_render_all_history_and_forecast_options(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->sale('2026-08-01 12:00:00', 100);
        foreach (array_keys(SalesForecastService::HISTORY_PERIODS) as $history) {
            foreach (array_keys(SalesForecastService::FORECAST_PERIODS) as $forecast) {
                $this->get('/dashboard?history='.$history.'&forecast='.$forecast)
                    ->assertOk()->assertSee('Sales Forecasting')->assertSee('Estimated total')
                    ->assertViewHas('forecasting', fn ($data) => $data['history']['period'] === $history
                        && $data['forecast']['period'] === $forecast);
            }
        }
    }

    public function test_today_is_separate_and_forecasts_use_only_completed_training_days(): void
    {
        $this->sale('2026-09-09 12:00:00', 140);
        $this->sale('2026-09-23 00:00:00', 50);
        $this->sale('2026-09-23 12:00:00', 25);
        $this->sale('2026-09-23 12:00:01', 999);
        $data = app(SalesForecastService::class)->dashboard('today', 'today');
        $this->assertSame('2026-09-23', $data['history']['start']);
        $this->assertSame('2026-09-23', $data['history']['end']);
        $this->assertEquals([75], $data['history']['values']);
        $this->assertSame('2026-09-23', $data['forecast']['start']);
        $this->assertSame('2026-09-23', $data['forecast']['end']);
        $this->assertEquals([70], $data['forecast']['values']);
        $past = app(SalesForecastService::class)->dashboard('last_7_days', 'next_7_days');
        $this->assertEquals(0, $past['history']['total']);
    }

    public function test_dashboard_shows_insufficient_history_and_validates_filters(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->get('/dashboard')->assertOk()->assertSee('Not enough sales history yet');
        $this->getJson('/dashboard?forecast=next_year')->assertUnprocessable()->assertJsonValidationErrors('forecast');
        $this->getJson('/dashboard?history=invalid')->assertUnprocessable()->assertJsonValidationErrors('history');
        $this->getJson('/dashboard?history[]=last_month')->assertUnprocessable()->assertJsonValidationErrors('history');
    }

    public function test_dashboard_forecasts_require_admin_dashboard_access(): void
    {
        $this->get('/dashboard')->assertRedirect();
        $staff = Staff::create(['name' => 'Staff', 'username' => 'cashier', 'password' => 'password123',
            'role' => 'Staff', 'is_active' => true]);
        $this->actingAs($staff, 'staff')->get('/dashboard')->assertRedirect();
        $admin = $this->admin();
        $admin->update(['permissions' => ['sales']]);
        $this->actingAs($admin, 'admin')->getJson('/dashboard')->assertForbidden();
    }
}
