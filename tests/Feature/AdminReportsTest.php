<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Staff;
use App\Services\SalesForecastService;
use App\Services\SalesReportService;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Existing purchase item migrations never create their table. Use the
        // same isolated setup as the forecasting and authentication suites.
        foreach (glob(database_path('migrations/*.php')) as $path) {
            if (! str_contains(basename($path), 'purchase_order_items')) {
                (require $path)->up();
            }
        }
        $this->travelTo(CarbonImmutable::parse('2026-09-23 12:00:00'));
    }

    private function sale(string $date, array $attributes = []): void
    {
        $sale = new Sale(array_merge([
            'receipt_no' => 'R-001', 'staff_name' => 'Cashier', 'item_name' => 'Cement',
            'qty' => 1, 'total_amount' => 100, 'paid_amount' => 0,
            'status' => 'Completed', 'payment_status' => 'Unpaid',
        ], $attributes));
        $sale->created_at = $date;
        $sale->updated_at = $date;
        $sale->save();
    }

    private function admin(array $permissions = ['reports', 'dashboard']): Admin
    {
        return Admin::create(['name' => 'Report Admin', 'username' => 'reports', 'email' => 'reports@example.test',
            'password' => 'password123', 'is_active' => true, 'permissions' => $permissions]);
    }

    public function test_reports_reconcile_sales_payments_transactions_and_product_totals(): void
    {
        $this->sale('2026-09-10', ['total_amount' => 200]);
        $this->sale('2026-09-17', ['total_amount' => 100, 'paid_amount' => 50, 'qty' => 2]);
        $this->sale('2026-09-17', ['total_amount' => 50, 'paid_amount' => 50, 'qty' => 3, 'item_name' => 'Nails']);
        $this->sale('2026-09-17', ['receipt_no' => 'R-002', 'total_amount' => 150]);
        $this->sale('2026-09-23 12:00:00', ['total_amount' => 200, 'paid_amount' => 200, 'qty' => 4]);
        $this->sale('2026-09-23 12:00:01', ['total_amount' => 9000]);
        $this->sale('2026-09-24', ['total_amount' => 9000]);
        foreach (['Cancelled', 'Held', 'Quotation'] as $status) {
            $this->sale('2026-09-17', ['total_amount' => 9999, 'status' => $status]);
        }

        $report = app(SalesReportService::class)->build('last_7_days', 'next_7_days');
        $this->assertEquals(300, $report['forecasting']['history']['total']);
        $this->assertEquals(2, $report['summary']['transactions']);
        $this->assertEquals(6, $report['summary']['units']);
        $this->assertEquals(100, $report['summary']['paid']);
        $this->assertEquals(200, $report['summary']['outstanding']);
        $this->assertEquals(150, $report['summary']['average_order']);
        $this->assertSame(1, $report['summary']['active_days']);
        $this->assertSame('2026-09-17', $report['summary']['best_day']['date']);
        $this->assertCount(7, $report['daily']);
        $this->assertEquals(0, $report['daily'][0]['total']);
        $this->assertEquals(2, $report['daily'][1]['transactions']);
        $this->assertEquals(150, $report['daily'][1]['average_order']);
        $this->assertEquals(200, $report['comparison']['total']);
        $this->assertEquals(100, $report['comparison']['change']);
        $this->assertEquals(50, $report['comparison']['percent']);
        $this->assertSame('Cement', $report['products'][0]['name']);
        $this->assertEquals(250, $report['products'][0]['total']);
        $this->assertEquals(3, $report['products'][0]['units']);
        $this->assertEquals(83.3, $report['products'][0]['share']);
        $this->assertEquals(300, array_sum(array_column($report['daily'], 'total')));
        $this->assertEquals(300, array_sum(array_column($report['products'], 'total')));
    }

    public function test_pos_order_lines_count_once_and_legacy_receipts_are_separated_by_cashier(): void
    {
        $order = Order::create(['number' => 'POS-1', 'request_key' => 'request-1', 'staff_name' => 'Cashier',
            'type' => 'Walk-in', 'status' => 'Completed', 'subtotal' => 200, 'total_amount' => 200]);
        $this->sale('2026-09-17', ['order_id' => $order->id, 'receipt_no' => 'POS-1']);
        $this->sale('2026-09-17', ['order_id' => $order->id, 'receipt_no' => 'POS-1', 'item_name' => 'Nails']);
        $this->sale('2026-09-17');
        $this->sale('2026-09-17', ['staff_name' => 'Another cashier']);
        $report = app(SalesReportService::class)->build('last_7_days', 'next_7_days');
        $this->assertSame(3, $report['summary']['transactions']);
        $this->assertEquals(400, $report['forecasting']['history']['total']);
    }

    public function test_report_forecasts_match_dashboard_and_daily_estimates_reconcile(): void
    {
        for ($i = 56; $i > 0; $i--) {
            $date = CarbonImmutable::today()->subDays($i);
            $this->sale($date->toDateString(), ['total_amount' => ($date->dayOfWeek + 1) * 100]);
        }
        $report = app(SalesReportService::class)->build('last_month', 'next_month');
        $this->assertSame(app(SalesForecastService::class)->dashboard('last_month', 'next_month'), $report['forecasting']);
        $forecast = $report['forecasting']['forecast'];
        $this->assertSame(56, array_sum(array_column($forecast['weekdays'], 'samples')));
        $this->assertEquals(100, $forecast['weekdays'][0]['average']);
        $this->assertEquals(700, $forecast['weekdays'][6]['average']);
        $this->assertCount(30, $report['forecast_daily']);
        $this->assertSame('2026-09-24', $report['forecast_daily'][0]['date']);
        $this->assertSame('Thursday', $report['forecast_daily'][0]['day']);
        $this->assertEquals(12300, array_sum(array_column($report['forecast_daily'], 'estimate')));
        $this->assertEquals($forecast['total'], $report['forecast_daily'][29]['cumulative']);
    }

    public function test_comparisons_use_adjacent_equal_length_rolling_periods_and_handle_declines(): void
    {
        $this->sale('2026-07-24', ['total_amount' => 400]);
        $this->sale('2026-08-22 23:59:59', ['total_amount' => 600]);
        $this->sale('2026-08-23', ['total_amount' => 500]);
        $this->sale('2026-09-24', ['total_amount' => 999]);
        $report = app(SalesReportService::class)->build('last_month', 'next_7_days');
        $this->assertSame('Jul 23, 2026 – Aug 22, 2026', $report['comparison']['range']);
        $this->assertEquals(1000, $report['comparison']['total']);
        $this->assertEquals(-500, $report['comparison']['change']);
        $this->assertEquals(-50, $report['comparison']['percent']);
        $this->assertCount(31, $report['daily']);
        $this->assertCount(365, app(SalesReportService::class)->build('last_year', 'next_month')['daily']);
    }

    public function test_empty_reports_have_zero_totals_and_unavailable_forecasts(): void
    {
        $report = app(SalesReportService::class)->build('last_7_days', 'next_7_days');
        $this->assertSame(0, $report['summary']['transactions']);
        $this->assertEquals(0, $report['summary']['average_order']);
        $this->assertNull($report['summary']['best_day']);
        $this->assertNull($report['comparison']['percent']);
        $this->assertEmpty($report['products']);
        foreach ($report['forecast_daily'] as $row) {
            $this->assertNull($row['estimate']);
            $this->assertNull($row['cumulative']);
        }
        $this->actingAs($this->admin(), 'admin')->get('/reports')->assertOk()
            ->assertSee('Not enough sales history yet')->assertSee('No product sales in this period.');
    }

    public function test_all_report_filter_combinations_render_and_keep_current_stock_separate(): void
    {
        $this->actingAs($this->admin(['reports']), 'admin');
        $this->sale('2026-08-01');
        Product::create(['name' => 'Low stock sample', 'stock' => 3, 'price' => 100, 'category' => 'Materials', 'status' => 'Low Stock']);
        foreach (array_keys(SalesForecastService::HISTORY_PERIODS) as $history) {
            foreach (array_keys(SalesForecastService::FORECAST_PERIODS) as $forecast) {
                $this->get('/reports?history='.$history.'&forecast='.$forecast)->assertOk()
                    ->assertSee('Daily sales breakdown')->assertSee('Day-by-day forecast')
                    ->assertSee('How the forecast is calculated')->assertSee('Low stock sample')
                    ->assertViewHas('report', fn ($data) => $data['forecasting']['history']['period'] === $history
                        && $data['forecasting']['forecast']['period'] === $forecast);
            }
        }
        $this->getJson('/reports?forecast=next_year')->assertUnprocessable()->assertJsonValidationErrors('forecast');
        $this->getJson('/reports?history[]=last_month')->assertUnprocessable()->assertJsonValidationErrors('history');
    }

    public function test_report_requires_admin_reports_permission(): void
    {
        $this->get('/reports')->assertRedirect();
        $staff = Staff::create(['name' => 'Cashier', 'username' => 'cashier', 'password' => 'password123', 'role' => 'Staff', 'is_active' => true]);
        $this->actingAs($staff, 'staff')->get('/reports')->assertRedirect();
        $this->actingAs($this->admin(['dashboard']), 'admin')->getJson('/reports')->assertForbidden();
    }
}
