
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Orders | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @include('partials.admin-sidebar-assets')

    <style>
        .page-header,.po-view-header,.product-header {
            display:flex;justify-content:space-between;align-items:center;gap:12px;
        }
        .page-header { margin-bottom:25px; }
        .page-header h2,.product-header h3 { margin:0; }
        .page-header p { color:#777;margin-top:5px; }
        .add-btn,.search-btn,.view-btn,.ordered-btn,.cancel-btn-small,
        .add-product-btn,.remove-product-btn,.save-btn,.cancel-modal-btn {
            border:0;border-radius:7px;padding:10px 14px;color:white;cursor:pointer;
        }
        .add-btn,.save-btn { background:#218838; }
        .search-btn { background:#333; }
        .view-btn { background:#007bff; }
        .ordered-btn { background:#17a2b8; }
        .cancel-btn-small,.remove-product-btn { background:#dc3545; }
        .add-product-btn { background:#6c757d; }
        .cancel-modal-btn { background:#777; }
        .search-container { margin-bottom:20px; }
        .search-container form { display:flex;gap:10px;flex-wrap:wrap; }
        .search-container input,.form-group input,.form-group select,
        .product-row input,.product-row select {
            box-sizing:border-box;padding:11px;border:1px solid #ccc;border-radius:7px;
        }
        .search-po { width:280px; }
        .stats-container { display:grid;grid-template-columns:repeat(4,1fr);gap:15px;margin-bottom:25px; }
        .stat-card { background:white;border-radius:10px;padding:20px;box-shadow:0 2px 8px #00000014; }
        .stat-number { font-size:30px;font-weight:bold;margin-bottom:5px; }
        .stat-label { color:#555;font-size:14px; }
        .table-container { background:white;border-radius:10px;overflow-x:auto; }
        table { width:100%;border-collapse:collapse; }
        th { background:#f5f5f5;padding:14px;text-align:left;white-space:nowrap; }
        td { padding:13px;border-top:1px solid #eee; }
        .status { display:inline-block;padding:6px 10px;border-radius:20px;font-size:13px; }
        .status-pending { background:#fff3cd;color:#856404; }
        .status-ordered { background:#cce5ff;color:#004085; }
        .status-received { background:#d4edda;color:#155724; }
        .status-partial { background:#e2d9f3;color:#563d7c; }
        .status-cancelled { background:#f8d7da;color:#721c24; }
        .action-buttons { display:flex;gap:6px;flex-wrap:wrap; }
        .action-buttons form { margin:0; }
        .modal {
            display:none;position:fixed;inset:0;background:#0009;
            justify-content:center;align-items:center;padding:18px;z-index:9999;
        }
        .modal-content {
            background:white;width:900px;max-width:100%;max-height:90vh;
            overflow-y:auto;padding:25px;border-radius:12px;box-sizing:border-box;
        }
        .modal-content h2 { margin-top:0; }
        .form-group { margin-bottom:17px; }
        .form-group label { display:block;font-weight:bold;margin-bottom:7px; }
        .form-group input,.form-group select { width:100%;font-size:15px; }
        .product-section { background:#f8f9fa;border:1px solid #ddd;border-radius:10px;padding:15px; }
        .product-header { margin-bottom:12px; }
        .product-row { display:grid;grid-template-columns:2fr 1fr 1fr 40px;gap:10px;margin-bottom:10px;align-items:center; }
        .product-row select,.product-row input { width:100%;min-width:0; }
        .remove-product-btn { width:35px;height:35px;padding:0; }
        .modal-actions { display:flex;justify-content:flex-end;gap:10px;margin-top:25px; }
        .alert-success,.alert-error { padding:12px 15px;border-radius:7px;margin-bottom:20px; }
        .alert-success { background:#d4edda;color:#155724; }
        .alert-error { background:#f8d7da;color:#721c24; }
        .alert-error ul { margin:0;padding-left:20px; }

        /* Purchase Order View Modal */
        .po-view-modal { z-index:11000; }
        .po-view-content { width:1000px; }
        .po-view-header { margin-bottom:20px; }
        .po-view-header h2 { margin:0; }
        .po-view-close { border:0;background:transparent;font-size:28px;cursor:pointer; }
        .po-view-details { display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin:20px 0; }
        .po-view-detail { background:#f5f7fa;padding:14px;border-radius:8px;overflow-wrap:anywhere; }
        .po-view-detail small { display:block;color:#666;margin-bottom:6px; }
        .po-view-table { margin-top:12px; }
        .po-view-footer { display:flex;justify-content:flex-end;margin-top:20px; }
        #poViewLoading { padding:20px 0; }
        @media(max-width:900px) {
            .stats-container { grid-template-columns:repeat(2,1fr); }
            .product-row { grid-template-columns:1fr; }
        }
        @media(max-width:600px) {
            .po-view-details { grid-template-columns:1fr; }
            .modal-content { padding:16px; }
            .page-header { align-items:flex-start; }
        }
    </style>
</head>

<body class="admin-workspace">
@include('partials.admin-sidebar')

<div class="main">
    <div class="topbar">
        <div></div>
        <div class="right-section">
            <div id="datetime" class="datetime"></div>
            <div class="user">Admin</div>
        </div>
    </div>

    @if(session('success'))
        <div class="alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert-error">{{ session('error') }}</div>
    @endif
    @if($errors->any())
        <div class="alert-error">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="page-header">
        <div>
            <h2>Purchase Orders</h2>
            <p>Create and manage purchase orders.</p>
        </div>
        <button type="button" class="add-btn" onclick="openPurchaseOrderModal()">
            <i class="fa-solid fa-plus"></i> New Purchase Order
        </button>
    </div>

    <div class="ui-metrics">
        <x-metric label="Pending Orders" :value="$pendingCount" tone="warning" icon="fa-clock" />
        <x-metric label="Ordered" :value="$orderedCount" tone="info" icon="fa-truck" />
        <x-metric label="Received" :value="$receivedCount" tone="success" icon="fa-circle-check" />
        <x-metric label="Total" :value="$totalCount" tone="info" icon="fa-boxes-stacked" />
    </div>

    <div class="search-container">
        <form method="GET" action="{{ route('purchase.order') }}">
            <input type="text" name="search" class="search-po"
                   value="{{ request('search') }}" placeholder="Search PO No.">
            <input type="date" name="date_from" value="{{ request('date_from') }}">
            <input type="date" name="date_to" value="{{ request('date_to') }}">
            <button type="submit" class="search-btn">
                <i class="fa-solid fa-search"></i> Search
            </button>
        </form>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>PO No.</th>
                    <th>Supplier</th>
                    <th>Total Amount</th>
                    <th>Status</th>
                    <th>Order Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($purchaseOrders as $purchaseOrder)
                    <tr>
                        <td><strong>{{ $purchaseOrder->po_number }}</strong></td>
                        <td>{{ $purchaseOrder->supplier }}</td>
                        <td>₱{{ number_format((float)$purchaseOrder->total_amount, 2) }}</td>
                        <td>
                            @php
                                $statusClass = match($purchaseOrder->status) {
                                    'Pending' => 'status-pending',
                                    'Ordered' => 'status-ordered',
                                    'Partially Received' => 'status-partial',
                                    'Received' => 'status-received',
                                    'Cancelled' => 'status-cancelled',
                                    default => ''
                                };
                            @endphp
                            <span class="status {{ $statusClass }}">
                                {{ $purchaseOrder->status }}
                            </span>
                        </td>
                        <td>
                            {{ $purchaseOrder->order_date
                                ? \Carbon\Carbon::parse($purchaseOrder->order_date)->format('M d, Y')
                                : 'N/A' }}
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button type="button" class="view-btn"
                                        onclick="openPurchaseOrderView({{ $purchaseOrder->id }})">
                                    <i class="fa-solid fa-eye"></i> View
                                </button>

                                @if($purchaseOrder->status === 'Pending')
                                    <form action="{{ route('purchase.order.ordered', $purchaseOrder->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="ordered-btn"
                                                onclick="return confirm('Mark this purchase order as Ordered?')">
                                            <i class="fa-solid fa-check"></i> Order
                                        </button>
                                    </form>
                                @endif

                                @if(!in_array($purchaseOrder->status, ['Received', 'Cancelled']))
                                    <form action="{{ route('purchase.order.cancel', $purchaseOrder->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="cancel-btn-small"
                                                onclick="return confirm('Cancel this purchase order?')">
                                            <i class="fa-solid fa-xmark"></i> Cancel
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;padding:30px;">No purchase orders found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- New Purchase Order Modal -->
<div id="purchaseOrderModal" class="modal">
    <div class="modal-content">
        <h2>New Purchase Order</h2>
        <form action="{{ route('purchase.order.store') }}" method="POST">
            @csrf

            <div class="form-group">
                <label for="supplier">Supplier</label>
                <select name="supplier" id="supplier" required>
                    <option value="">Select supplier</option>
                    @forelse($suppliers as $supplier)
                        <option value="{{ $supplier->name }}">{{ $supplier->name }}</option>
                    @empty
                        <option value="" disabled>No active suppliers available</option>
                    @endforelse
                </select>
            </div>

            <div class="form-group">
                <label for="order_date">Order Date</label>
                <input type="date" name="order_date" id="order_date"
                       value="{{ date('Y-m-d') }}" required>
            </div>

            <div class="product-section">
                <div class="product-header">
                    <h3>Products</h3>
                    <button type="button" class="add-product-btn" onclick="addProductRow()">
                        <i class="fa-solid fa-plus"></i> Add Product
                    </button>
                </div>
                <div id="productRows">
                    <div class="product-row">
                        <select name="products[0][product_id]" required>
                            <option value="">Select product</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }}</option>
                            @endforeach
                        </select>
                        <input type="number" name="products[0][quantity]" placeholder="Qty" min="1" required>
                        <input type="number" name="products[0][unit_price]" placeholder="Unit price" min="0" step="0.01" required>
                        <button type="button" class="remove-product-btn" onclick="removeProductRow(this)" title="Remove product">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="cancel-modal-btn" onclick="closePurchaseOrderModal()">Cancel</button>
                <button type="submit" class="save-btn">
                    <i class="fa-solid fa-check"></i> Create Purchase Order
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Purchase Order Modal -->
<div id="purchaseOrderViewModal" class="modal po-view-modal">
    <div class="modal-content po-view-content">
        <div class="po-view-header">
            <h2><i class="fa-solid fa-file-invoice"></i> Purchase Order Details</h2>
            <button type="button" class="po-view-close" onclick="closePurchaseOrderView()">&times;</button>
        </div>

        <p id="poViewLoading">Loading purchase order...</p>
        <div id="poViewError" class="alert-error" style="display:none;"></div>

        <div id="poViewDetails" style="display:none;">
            <div class="po-view-details">
                <div class="po-view-detail"><small>PO Number</small><strong id="poViewNumber"></strong></div>
                <div class="po-view-detail"><small>Supplier</small><strong id="poViewSupplier"></strong></div>
                <div class="po-view-detail"><small>Status</small><strong id="poViewStatus"></strong></div>
                <div class="po-view-detail"><small>Order Date</small><strong id="poViewDate"></strong></div>
                <div class="po-view-detail"><small>Total Amount</small><strong id="poViewTotal"></strong></div>
            </div>

            <h3>Products in this Order</h3>
            <div style="overflow-x:auto;">
                <table class="po-view-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Ordered</th>
                            <th>Received</th>
                            <th>Remaining</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody id="poViewItems"></tbody>
                </table>
            </div>
        </div>

        <div class="po-view-footer">
            <button type="button" class="cancel-modal-btn" onclick="closePurchaseOrderView()">Close</button>
        </div>
    </div>
</div>

<script>
function openPurchaseOrderModal() {
    document.getElementById('purchaseOrderModal').style.display = 'flex';
}

function closePurchaseOrderModal() {
    document.getElementById('purchaseOrderModal').style.display = 'none';
}

let productIndex = 1;

function addProductRow() {
    const container = document.getElementById('productRows');
    const row = document.createElement('div');
    row.className = 'product-row';

    const select = document.createElement('select');
    select.name = `products[${productIndex}][product_id]`;
    select.required = true;

    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = 'Select product';
    select.appendChild(placeholder);

    const productOptions = @json($products->map(fn($product) => [
        'id' => $product->id,
        'name' => $product->name
    ])->values());

    productOptions.forEach(product => {
        const option = document.createElement('option');
        option.value = product.id;
        option.textContent = product.name;
        select.appendChild(option);
    });

    const quantity = document.createElement('input');
    quantity.type = 'number';
    quantity.name = `products[${productIndex}][quantity]`;
    quantity.placeholder = 'Qty';
    quantity.min = '1';
    quantity.required = true;

    const price = document.createElement('input');
    price.type = 'number';
    price.name = `products[${productIndex}][unit_price]`;
    price.placeholder = 'Unit price';
    price.min = '0';
    price.step = '0.01';
    price.required = true;

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'remove-product-btn';
    remove.title = 'Remove product';
    remove.innerHTML = '<i class="fa-solid fa-xmark"></i>';
    remove.addEventListener('click', () => removeProductRow(remove));

    row.append(select, quantity, price, remove);
    container.appendChild(row);
    productIndex++;
}

function removeProductRow(button) {
    const rows = document.querySelectorAll('#productRows .product-row');
    if (rows.length <= 1) {
        alert('At least one product is required.');
        return;
    }
    button.closest('.product-row').remove();
}

async function openPurchaseOrderView(id) {
    const modal = document.getElementById('purchaseOrderViewModal');
    const loading = document.getElementById('poViewLoading');
    const details = document.getElementById('poViewDetails');
    const errorBox = document.getElementById('poViewError');
    const itemsBody = document.getElementById('poViewItems');

    modal.style.display = 'flex';
    loading.style.display = 'block';
    loading.textContent = 'Loading purchase order...';
    details.style.display = 'none';
    errorBox.style.display = 'none';
    itemsBody.replaceChildren();

    try {
        const response = await fetch(
            @json(url('/purchase-orders')) + '/' + encodeURIComponent(id) + '/details',
            {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }
        );

        if (!response.ok) {
            throw new Error('Request failed: ' + response.status);
        }

        const po = await response.json();

        document.getElementById('poViewNumber').textContent = po.po_number ?? 'N/A';
        document.getElementById('poViewSupplier').textContent = po.supplier ?? 'N/A';
        document.getElementById('poViewStatus').textContent = po.status ?? 'N/A';
        document.getElementById('poViewDate').textContent = po.order_date ?? 'N/A';
        document.getElementById('poViewTotal').textContent = formatPeso(po.total_amount);

        if (!Array.isArray(po.items) || po.items.length === 0) {
            const row = document.createElement('tr');
            const cell = document.createElement('td');
            cell.colSpan = 6;
            cell.textContent = 'No products found for this purchase order.';
            row.appendChild(cell);
            itemsBody.appendChild(row);
        } else {
            po.items.forEach(item => {
                const row = document.createElement('tr');
                [
                    item.product ?? 'Unknown Product',
                    item.ordered ?? 0,
                    item.received ?? 0,
                    item.remaining ?? 0,
                    formatPeso(item.unit_price),
                    formatPeso(item.subtotal)
                ].forEach(value => {
                    const cell = document.createElement('td');
                    cell.textContent = String(value);
                    row.appendChild(cell);
                });
                itemsBody.appendChild(row);
            });
        }

        loading.style.display = 'none';
        details.style.display = 'block';
    } catch (error) {
        console.error('Purchase Order View error:', error);
        loading.style.display = 'none';
        errorBox.textContent =
            'Hindi ma-load ang purchase order. Tiyaking naidagdag ang /purchase-orders/{id}/details route at details() controller method.';
        errorBox.style.display = 'block';
    }
}

function formatPeso(value) {
    return '₱' + Number(value ?? 0).toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function closePurchaseOrderView() {
    document.getElementById('purchaseOrderViewModal').style.display = 'none';
}

window.addEventListener('click', function(event) {
    if (event.target === document.getElementById('purchaseOrderModal')) {
        closePurchaseOrderModal();
    }
    if (event.target === document.getElementById('purchaseOrderViewModal')) {
        closePurchaseOrderView();
    }
});

 // DIGITAL CLOCK
        function updateDateTime() {
    const now = new Date();
    const date = now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
    const time = now.toLocaleTimeString();
    const element = document.getElementById('datetime');

    if (element) element.textContent = date + ' | ' + time;
}

setInterval(updateDateTime, 1000);
updateDateTime();

setInterval(updateDateTime, 1000);
updateDateTime();
</script>
</body>
</html>