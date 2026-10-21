<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\SalesForecastService;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportsController extends Controller
{
    public function index(Request $request, SalesReportService $reports)
    {
        $filters = $request->validate([
            'history' => ['sometimes', 'string', Rule::in(array_keys(SalesForecastService::HISTORY_PERIODS))],
            'forecast' => ['sometimes', 'string', Rule::in(array_keys(SalesForecastService::FORECAST_PERIODS))],
        ]);

        return view('reports', [
            'report' => $reports->build($filters['history'] ?? 'last_7_days', $filters['forecast'] ?? 'next_7_days'),
            'historyPeriods' => SalesForecastService::HISTORY_PERIODS,
            'forecastPeriods' => SalesForecastService::FORECAST_PERIODS,
            'lowStock' => Product::where('stock', '<=', 5)->orderBy('stock')->orderBy('name')->get(),
        ]);
    }
}
