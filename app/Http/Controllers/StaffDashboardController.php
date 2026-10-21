<?php 


namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

class StaffDashboardController extends Controller
{
    public function index()
    {
        $today = Sale::whereDate('created_at', today());

        // Stock alert threshold used by the dashboard.
        $lowStockThreshold = 5;

        $lowStockQuery = Product::where('stock', '<=', $lowStockThreshold);

        return view('staff.dashboard', [
            'metrics' => [
                "Today's Sales" => '₱' . number_format(
                    (clone $today)->sum('total_amount'),
                    2
                ),

                'Outstanding Balance' => '₱' . number_format(
                    Sale::sum(DB::raw('total_amount - paid_amount')),
                    2
                ),

                'Transactions Today' => (clone $today)
                    ->distinct()
                    ->count('receipt_no'),

                'Items Sold Today' => (clone $today)->sum('qty'),

                'Pending Orders' => Order::whereIn('status', [
                    'Pending',
                    'Processing',
                    'Ready',
                    'Out for Delivery',
                ])->count(),

                'Deliveries Completed Today' => Order::where('type', 'Delivery')
                    ->whereDate('completed_at', today())
                    ->count(),

                'Low Stock Items' => (clone $lowStockQuery)
                    ->where('stock', '>', 0)
                    ->count(),
            ],

            'topProducts' => Sale::where(
                'created_at',
                '>=',
                now()->subDays(30)
            )
                ->select('item_name')
                ->selectRaw('SUM(qty) as quantity')
                ->groupBy('item_name')
                ->orderByDesc('quantity')
                ->limit(5)
                ->get(),

            // Show out-of-stock products first, then low-stock products.
            'lowStock' => $lowStockQuery
                ->orderBy('stock')
                ->orderBy('name')
                ->limit(5)
                ->get(),

            'recentOrders' => Order::latest('id')
                ->limit(5)
                ->get(),

            'lowStockThreshold' => $lowStockThreshold,
        ]);
    }
}
