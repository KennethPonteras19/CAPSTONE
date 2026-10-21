<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CustomerOrderService;
use App\Services\StaffOrderService;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerWorkspaceController extends Controller
{
    public function home(CustomerOrderService $service)
    {
        $featuredProducts = Product::where('stock', '>', 0)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(5)
            ->get()
            ->map(fn (Product $product): array => $service->product($product));

        return view('customer.home', compact('featuredProducts'));
    }

    public function order(Request $request, CustomerOrderService $service)
    {
        return view('customer.order', [
            'products' => Product::orderBy('name')->orderBy('id')->get()->map(fn ($product) => $service->product($product)),
            'customerCart' => $service->snapshot($service->cart($request->user('customer'))),
        ]);
    }

    public function cart(Request $request, CustomerOrderService $service)
    {
        return view('customer.cart', ['customerCart' => $service->snapshot($service->cart($request->user('customer')))]);
    }

    public function updateCart(Request $request, CustomerOrderService $service)
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'items' => ['present', 'array', 'list', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
        ]);

        return response()->json($service->updateCart($request->user('customer'), $data));
    }

    public function checkout(Request $request, CustomerOrderService $service)
    {
        return view('customer.checkout', [
            'customerCart' => $service->snapshot($service->cart($request->user('customer'))),
            'customer' => $request->user('customer'),
            'deliveryFee' => (float) config('customer.delivery_fee'),
            'minimumDate' => today()->addDay()->toDateString(),
        ]);
    }

    public function placeOrder(Request $request, CustomerOrderService $service, StaffOrderService $workflow)
    {
        $times = [];
        for ($minutes = 8 * 60; $minutes <= 17 * 60; $minutes += 15) {
            $times[] = sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'version' => ['required', 'integer', 'min:1'],
            'fulfillment' => ['required', Rule::in(['pickup', 'delivery'])],
            'name' => ['required', 'string', 'max:255'], 'phone' => ['required', 'string', 'max:40'],
            'address' => ['nullable', 'required_if:fulfillment,delivery', 'string', 'max:2000'],
            'date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'time' => ['required', Rule::in($times)],
            'items' => ['required', 'array', 'list', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.expected_price' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
        ]);
        $order = $service->checkout($request->user('customer'), $data, $workflow);

        return response()->json(['number' => $order->number, 'total' => $order->total_amount,
            'redirect' => route(ModuleAccess::allows('customer', 'order') ? 'customer.order' : ModuleAccess::landingRoute('customer'))]);
    }
}
