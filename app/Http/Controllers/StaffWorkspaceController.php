<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Sale;
use App\Services\StaffOrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffWorkspaceController extends Controller
{
    public function pos(Request $request)
    {
        $draft = $request->filled('order') ? Order::with('items')->findOrFail($request->integer('order')) : null;
        abort_if($draft && ! $draft->isDraft(), 409, 'Only held orders and quotations can be resumed.');

        return view('staff.pos', [
            'products' => Product::orderBy('name')->get(['id', 'name', 'category', 'price', 'stock', 'stock_unit', 'image_path']),
            'customers' => Customer::where('is_active', true)->orderBy('name')->get(['id', 'name', 'phone', 'address']),
            'draft' => $draft,
        ]);
    }

    public function catalog()
    {
        return response()->json(Product::orderBy('name')->get(['id', 'name', 'category', 'price', 'stock', 'stock_unit', 'image_path']))->header('Cache-Control', 'no-store');
    }

    public function checkout(Request $request, StaffOrderService $service)
    {
        $data = $request->validate([
            'request_key' => ['required', 'uuid'], 'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'version' => ['required_with:order_id', 'integer', 'min:1'],
            'action' => ['required', Rule::in(['checkout', 'hold', 'quote'])],
            'type' => ['required', Rule::in(['Walk-in', 'Pickup', 'Delivery'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'address' => ['nullable', 'string', 'max:2000'], 'notes' => ['nullable', 'string', 'max:2000'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(['Cash', 'Online', 'Cheque', 'Charge'])],
            'amount_tendered' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:255', Rule::requiredIf(fn () => $request->input('action') === 'checkout' && in_array($request->input('payment_method'), ['Online', 'Cheque']))],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.expected_price' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ]);
        $order = $service->save($data, $request->user('staff'));
        if ($request->expectsJson()) {
            return response()->json(['redirect' => route('staff.orders.show', $order), 'number' => $order->number]);
        }

        return redirect()->route('staff.orders.show', $order)->with('success', 'Order saved successfully.');
    }

    public function inventory(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:255', 'category' => 'nullable|string|max:255',
            'stock' => ['nullable', Rule::in(['available', 'low', 'out'])], 'sort' => ['nullable', Rule::in(['name', 'stock'])]]);
        $query = Product::query();
        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        match ($request->stock) {
            'available' => $query->where('stock', '>', 5), 'low' => $query->whereBetween('stock', [1, 5]),
            'out' => $query->where('stock', '<=', 0), default => null,
        };

        return view('staff.inventory', [
            'products' => $query->orderBy($request->input('sort') ?: 'name')->orderBy('id')->paginate(15)->withQueryString(),
            'categories' => Product::distinct()->orderBy('category')->pluck('category'),
            'metrics' => ['Total Products' => Product::count(), 'Available Products' => Product::where('stock', '>', 5)->count(),
                'Low Stock Products' => Product::whereBetween('stock', [1, 5])->count(), 'Out of Stock Products' => Product::where('stock', '<=', 0)->count()],
        ]);
    }

    public function orders(Request $request)
    {
        $this->validateFilters($request);
        $query = Order::query()->withCount('items');
        if ($request->filled('search')) {
            $query->where(function ($query) use ($request) {
                $query->where('number', 'like', '%'.$request->search.'%')->orWhere('customer_name', 'like', '%'.$request->search.'%')
                    ->orWhereHas('items', fn ($items) => $items->where('name', 'like', '%'.$request->search.'%'));
            });
        }
        foreach (['status', 'type', 'payment_status'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->input($field));
            }
        }
        $this->dates($query, $request);

        return view('staff.orders', ['orders' => $query->latest('id')->paginate(15)->withQueryString(),
            'metrics' => ['Total Orders' => Order::count(), 'Pending Orders' => Order::whereIn('status', ['Pending', 'Processing', 'Ready'])->count(),
                'Completed Orders' => Order::where('status', 'Completed')->count(), 'Saved Drafts' => Order::whereIn('status', ['Held', 'Quotation'])->count(),
                'Ongoing Delivery' => Order::where('status', 'Out for Delivery')->count()]]);
    }

    public function show(Order $order)
    {
        return view('staff.order', ['order' => $order->load(['items', 'payments'])]);
    }

    public function status(Request $request, Order $order, StaffOrderService $service)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['Processing', 'Ready', 'Out for Delivery', 'Completed', 'Cancelled'])],
            'version' => ['required', 'integer', 'min:1']]);
        $service->changeStatus($order, $data['status'], $data['version']);

        return back()->with('success', 'Order status updated.');
    }

    public function payment(Request $request, Order $order, StaffOrderService $service)
    {
        $data = $request->validate(['request_key' => ['required', 'uuid'],
            'method' => ['required', Rule::in(['Cash', 'Online', 'Cheque'])],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:99999999', 'decimal:0,2'],
            'reference' => ['nullable', 'string', 'max:255', 'required_if:method,Online,Cheque']]);
        $service->pay($order, $data, $request->user('staff'));

        return back()->with('success', 'Payment recorded.');
    }

    private function validateFilters(Request $request): void
    {
        $request->validate(['search' => 'nullable|string|max:255', 'status' => 'nullable|string|max:30',
            'type' => 'nullable|string|max:30', 'payment_status' => 'nullable|string|max:20',
            'method' => 'nullable|string|max:30', 'from' => 'nullable|date_format:Y-m-d',
            'to' => 'nullable|date_format:Y-m-d']);
    }

    private function dates($query, Request $request, string $column = 'created_at'): void
    {
        if ($request->filled('from')) {
            $query->whereDate($column, '>=', $request->input('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate($column, '<=', $request->input('to'));
        }
    }

    public function transactions(Request $request)
    {
        $this->validateFilters($request);
        $query = Sale::query()->leftJoin('orders', 'orders.id', '=', 'sales.order_id')
            ->select('sales.receipt_no', 'sales.order_id', 'orders.customer_name', 'orders.payment_method')
            ->selectRaw('MIN(sales.created_at) as sold_at, SUM(sales.total_amount) as total, SUM(sales.paid_amount) as paid, SUM(sales.qty) as quantity')
            ->groupBy('sales.receipt_no', 'sales.order_id', 'orders.customer_name', 'orders.payment_method');
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('sales.receipt_no', 'like', '%'.$request->search.'%')
                ->orWhere('orders.customer_name', 'like', '%'.$request->search.'%'));
        }
        if ($request->filled('method')) {
            $query->where('orders.payment_method', $request->method);
        }
        match ($request->payment_status) {
            'Paid' => $query->havingRaw('SUM(sales.paid_amount) >= SUM(sales.total_amount)'),
            'Partial' => $query->havingRaw('SUM(sales.paid_amount) > 0 AND SUM(sales.paid_amount) < SUM(sales.total_amount)'),
            'Unpaid' => $query->havingRaw('SUM(sales.paid_amount) = 0 AND SUM(sales.total_amount) > 0'), default => null,
        };
        $this->dates($query, $request, 'sales.created_at');

        return view('staff.transactions', [
            'receipts' => $query->orderByDesc('sold_at')->paginate(15)->withQueryString(),
            'metrics' => ['Transactions Today' => Sale::whereDate('created_at', today())->distinct()->count('receipt_no'),
                'Sales Today' => '₱'.number_format(Sale::whereDate('created_at', today())->sum('total_amount'), 2),
                'Outstanding Balance' => '₱'.number_format(Sale::sum(DB::raw('total_amount - paid_amount')), 2)],
        ]);
    }

    public function legacyReceipt(Request $request)
    {
        $request->validate(['receipt' => 'required|string|max:255']);
        $sales = Sale::whereNull('order_id')->where('receipt_no', $request->receipt)->orderBy('id')->get();
        abort_if($sales->isEmpty(), 404);

        return view('staff.receipt', compact('sales'));
    }

    public function customers(Request $request)
    {
        $request->validate(['search' => 'nullable|string|max:255', 'customer' => 'nullable|integer', 'sort' => ['nullable', Rule::in(['asc', 'desc'])]]);
        $query = Customer::query();
        if ($request->filled('search')) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')->orWhere('phone', 'like', '%'.$request->search.'%')
                ->orWhere('email', 'like', '%'.$request->search.'%')->orWhere('address', 'like', '%'.$request->search.'%'));
        }
        $customers = $query->orderBy('name', $request->input('sort') ?: 'asc')->paginate(15)->withQueryString();
        $selected = $request->filled('customer') ? Customer::findOrFail($request->integer('customer')) : null;
        $history = $selected ? Order::where('customer_id', $selected->id)->latest('id')->paginate(10, ['*'], 'history_page')->withQueryString() : null;

        return view('staff.customers', compact('customers', 'selected', 'history'));
    }

    public function saveCustomer(Request $request, ?Customer $customer = null)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('customers')->ignore($customer?->id)],
            'phone' => ['nullable', 'string', 'max:40'], 'address' => ['nullable', 'string', 'max:2000']]);
        $customer ??= new Customer;
        $customer->fill($data)->save();

        return redirect()->route('customers', ['customer' => $customer->id])->with('success', 'Customer saved.');
    }
}
