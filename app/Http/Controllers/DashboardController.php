<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Sale;
use App\Services\SalesForecastService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function index(Request $request, SalesForecastService $forecastService)
    {
        $filters = $request->validate([
            'history' => ['sometimes', 'string', Rule::in(array_keys(SalesForecastService::HISTORY_PERIODS))],
            'forecast' => ['sometimes', 'string', Rule::in(array_keys(SalesForecastService::FORECAST_PERIODS))],
        ]);
        $forecasting = $forecastService->dashboard(
            $filters['history'] ?? 'last_7_days',
            $filters['forecast'] ?? 'next_7_days'
        );
        $historyPeriods = SalesForecastService::HISTORY_PERIODS;
        $forecastPeriods = SalesForecastService::FORECAST_PERIODS;

        // 1. TODAY SALES
        $todaySales = Sale::whereDate('created_at', Carbon::today())->sum('total_amount') ?? 0;

        // 2. TOTAL TRANSACTIONS TODAY
        $transactions = Sale::whereDate('created_at', Carbon::today())->count();

        // 3. TOTAL PRODUCTS
        $products = Product::count();

        // 4. LOW STOCK COUNT
        $lowStockCount = Product::where('stock', '<=', 5)->count();

        // 5. RECENT SALES
        $recentSales = Sale::latest()->limit(5)->get();

        // 7. INVENTORY ALERTS & NOTIFICATION DATA
        $outOfStockProducts = Product::where('stock', 0)->latest()->get();
        $lowStockProducts = Product::where('stock', '>', 0)
            ->where('stock', '<=', 5)
            ->latest()
            ->get();

        $stockAlertItems = Product::where('stock', '<=', 5)->get();

        // Count for notification badge
        $notificationCount = $outOfStockProducts->count() + $lowStockProducts->count();

        return view('dashboard', compact(
            'todaySales',
            'transactions',
            'products',
            'lowStockCount',
            'recentSales',
            'forecasting',
            'historyPeriods',
            'forecastPeriods',
            'outOfStockProducts',
            'lowStockProducts',
            'stockAlertItems',
            'notificationCount'
        ));
    }
}
