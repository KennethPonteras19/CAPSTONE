<?php

namespace App\Services;

use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;

class SalesForecastService
{
    public const HISTORY_PERIODS = [
        'today' => 'Today',
        'last_7_days' => 'Last 7 Days',
        'last_month' => 'Last Month',
        'last_quarter' => 'Last Quarter',
        'last_year' => 'Last Year',
    ];

    public const FORECAST_PERIODS = [
        'today' => 'Today',
        'next_7_days' => 'Next 7 Days',
        'next_month' => 'Next Month',
    ];

    public function dashboard(string $historyPeriod, string $forecastPeriod): array
    {
        $asOf = CarbonImmutable::now();
        $today = $asOf->startOfDay();
        // Historical windows contain completed days; today is a separate view.
        $start = match ($historyPeriod) {
            'today' => $today,
            'last_month' => $today->subMonthNoOverflow(),
            'last_quarter' => $today->subMonthsNoOverflow(3),
            'last_year' => $today->subYearNoOverflow(),
            default => $today->subDays(7),
        };
        $end = $historyPeriod === 'today' ? $today->addDay() : $today;

        $daily = $this->dailySales($start, $end, $asOf);
        $buckets = [];
        $days = 0;
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            // Keep longer histories readable without dropping any sales.
            $label = match ($historyPeriod) {
                'last_year' => $date->format('M Y'),
                'last_quarter' => $date->startOfWeek()->max($start)->format('M d, Y'),
                default => $date->format('M d, Y'),
            };
            $buckets[$label] = ($buckets[$label] ?? 0) + ($daily[$date->toDateString()] ?? 0);
            $days++;
        }

        return [
            'history' => [
                'period' => $historyPeriod,
                'start' => $start->toDateString(),
                'end' => $end->subDay()->toDateString(),
                'as_of' => $asOf->toDateTimeString(),
                'range' => $start->format('M d, Y').' – '.$end->subDay()->format('M d, Y'),
                'labels' => array_keys($buckets),
                'values' => array_map(fn ($value) => round($value, 2), array_values($buckets)),
                'total' => round(array_sum($buckets), 2),
                'daily_average' => round(array_sum($buckets) / $days, 2),
                'has_sales' => count($daily) > 0,
                'grouping' => match ($historyPeriod) {
                    'last_year' => 'Monthly totals (partial months at period boundaries)',
                    'last_quarter' => 'Weekly totals (partial weeks at period boundaries)',
                    default => 'Daily totals',
                },
            ],
            'forecast' => $this->forecast($today, $forecastPeriod),
        ];
    }

    private function sales(): Builder
    {
        // Include booked sales regardless of payment status, matching admin sales totals.
        return Sale::forReporting();
    }

    private function dailySales(CarbonImmutable $start, CarbonImmutable $end, ?CarbonImmutable $asOf = null): array
    {
        return $this->sales()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->where('created_at', '<=', $asOf ?? CarbonImmutable::now())
            ->selectRaw('DATE(created_at) as sale_date, SUM(total_amount) as total')
            ->groupBy('sale_date')->orderBy('sale_date')
            ->pluck('total', 'sale_date')->map(fn ($value) => (float) $value)->all();
    }

    private function forecast(CarbonImmutable $today, string $period): array
    {
        // Use complete days only; changing the history filter must not change predictions.
        $firstSale = $this->sales()->where('created_at', '<', $today)->min('created_at');
        $trainingStart = $firstSale
            ? CarbonImmutable::parse($firstSale)->startOfDay()->max($today->subDays(56))
            : $today;
        $training = $this->dailySales($trainingStart, $today);
        $weekdayTotals = array_fill(0, 7, 0.0);
        $weekdayCounts = array_fill(0, 7, 0);
        $trainingDays = 0;
        for ($date = $trainingStart; $date->lt($today); $date = $date->addDay()) {
            // Zero-sale days after recording began are part of the average.
            $weekdayTotals[$date->dayOfWeek] += $training[$date->toDateString()] ?? 0;
            $weekdayCounts[$date->dayOfWeek]++;
            $trainingDays++;
        }

        $available = $trainingDays >= 14;
        $weekdays = [];
        foreach (['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'] as $index => $name) {
            $weekdays[] = [
                'day' => $name,
                'samples' => $weekdayCounts[$index],
                'total' => round($weekdayTotals[$index], 2),
                'average' => $weekdayCounts[$index] > 0
                    ? round($weekdayTotals[$index] / $weekdayCounts[$index], 2) : null,
            ];
        }
        $start = $period === 'today' ? $today : $today->addDay();
        $end = match ($period) {
            'today' => $start->addDay(),
            'next_month' => $start->addMonthNoOverflow(),
            default => $start->addDays(7),
        };
        $labels = [];
        $values = [];
        for ($date = $start; $date->lt($end); $date = $date->addDay()) {
            $labels[] = $date->format('M d, Y');
            $values[] = $available
                ? round(max(0, $weekdayTotals[$date->dayOfWeek] / $weekdayCounts[$date->dayOfWeek]), 2)
                : null;
        }

        return [
            'period' => $period,
            'start' => $start->toDateString(),
            'end' => $end->subDay()->toDateString(),
            'range' => $start->format('M d, Y').' – '.$end->subDay()->format('M d, Y'),
            'labels' => $labels,
            'values' => $values,
            'total' => $available ? round(array_sum($values), 2) : null,
            'daily_average' => $available ? round(array_sum($values) / count($values), 2) : null,
            'available' => $available,
            'training_days' => $trainingDays,
            'training_active_days' => count(array_filter($training, fn ($value) => $value > 0)),
            'weekdays' => $weekdays,
            'training_range' => $trainingDays > 0
                ? $trainingStart->format('M d, Y').' – '.$today->subDay()->format('M d, Y') : null,
        ];
    }
}
