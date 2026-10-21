<?php

namespace App\Services;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class SalesReportService
{
    public function __construct(private SalesForecastService $forecasts) {}

    public function build(string $historyPeriod, string $forecastPeriod): array
    {
        $forecasting = $this->forecasts->dashboard($historyPeriod, $forecastPeriod);
        $history = $forecasting['history'];
        $start = CarbonImmutable::parse($history['start']);
        $end = CarbonImmutable::parse($history['end'])->addDay();
        $sales = Sale::forReporting()->where('created_at', '>=', $start)->where('created_at', '<', $end)
            ->where('created_at', '<=', $history['as_of']);
        $daily = (clone $sales)->selectRaw('DATE(created_at) as date, SUM(total_amount) as total, SUM(qty) as units, SUM(paid_amount) as paid')
            ->groupBy('date')->get()->keyBy('date');

        // POS orders use their ID. Legacy receipts are grouped by day and cashier
        // because legacy receipt numbers are not globally unique.
        $receiptGroups = (clone $sales)
            ->selectRaw('DATE(created_at) as sale_date, order_id, CASE WHEN order_id IS NULL THEN receipt_no ELSE NULL END as legacy_receipt, CASE WHEN order_id IS NULL THEN staff_name ELSE NULL END as legacy_staff')
            ->groupBy('sale_date', 'order_id', 'legacy_receipt', 'legacy_staff');
        $transactionCounts = DB::query()->fromSub($receiptGroups, 'receipts')
            ->selectRaw('sale_date, COUNT(*) as transactions')->groupBy('sale_date')->pluck('transactions', 'sale_date');

        $rows = [];
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $row = $daily->get($key);
            $total = round((float) ($row?->total ?? 0), 2);
            $paid = round((float) ($row?->paid ?? 0), 2);
            $transactions = (int) $transactionCounts->get($key, 0);
            $rows[] = [
                'date' => $key, 'day' => $date->format('l'), 'total' => $total,
                'units' => (int) ($row?->units ?? 0), 'transactions' => $transactions,
                'paid' => $paid, 'outstanding' => round(max(0, $total - $paid), 2),
                'average_order' => $transactions > 0 ? round($total / $transactions, 2) : 0,
            ];
        }

        // Compare against the immediately preceding window with the same number
        // of days, without gaps or overlaps when month lengths differ.
        $previousStart = $start->subDays(count($rows));
        $previousTotal = round((float) Sale::forReporting()->where('created_at', '>=', $previousStart)
            ->where('created_at', '<', $start)->sum('total_amount'), 2);
        $change = round($history['total'] - $previousTotal, 2);
        $transactions = array_sum(array_column($rows, 'transactions'));
        $paid = round(array_sum(array_column($rows, 'paid')), 2);
        $bestDay = collect($rows)->where('total', '>', 0)->sortByDesc('total')->first();

        $topProducts = (clone $sales)->selectRaw('item_name, SUM(qty) as units, SUM(total_amount) as total')
            ->groupBy('item_name')->orderByDesc('total')->orderBy('item_name')->limit(10)->get()
            ->map(fn ($product) => [
                'name' => $product->item_name, 'units' => (int) $product->units,
                'total' => round((float) $product->total, 2),
                'share' => $history['total'] > 0 ? round($product->total / $history['total'] * 100, 1) : 0,
            ])->all();

        $forecastRows = [];
        $cumulative = 0;
        $forecastStart = CarbonImmutable::parse($forecasting['forecast']['start']);
        foreach ($forecasting['forecast']['values'] as $offset => $value) {
            $date = $forecastStart->addDays($offset);
            $cumulative += $value ?? 0;
            $forecastRows[] = [
                'date' => $date->toDateString(), 'day' => $date->format('l'), 'estimate' => $value,
                'cumulative' => $value !== null ? round($cumulative, 2) : null,
            ];
        }

        return [
            'forecasting' => $forecasting,
            'summary' => [
                'transactions' => $transactions, 'units' => array_sum(array_column($rows, 'units')),
                'paid' => $paid, 'outstanding' => round(array_sum(array_column($rows, 'outstanding')), 2),
                'average_order' => $transactions > 0 ? round($history['total'] / $transactions, 2) : 0,
                'active_days' => count(array_filter($rows, fn ($row) => $row['transactions'] > 0)),
                'days' => count($rows), 'best_day' => $bestDay,
            ],
            'comparison' => [
                'range' => $previousStart->format('M d, Y').' – '.$start->subDay()->format('M d, Y'),
                'total' => $previousTotal, 'change' => $change,
                'percent' => $previousTotal > 0 ? round($change / $previousTotal * 100, 1) : null,
            ],
            'daily' => $rows, 'products' => $topProducts, 'forecast_daily' => $forecastRows,
            'generated_at' => CarbonImmutable::now()->format('M d, Y h:i A').' '.config('app.timezone'),
        ];
    }
}
