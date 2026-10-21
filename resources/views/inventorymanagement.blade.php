
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @include('partials.admin-sidebar-assets')

    <style>
        .consumable-section-header {
            display:flex; justify-content:space-between; align-items:center;
            margin:35px 0 15px; gap:15px;
        }
        .consumable-section-header h2 { margin:0; }
        .consumable-section-header p { margin:5px 0 0; color:#777; }
        .consumable-manage-btn, .add-consumable-btn, .consumable-save-btn {
            background:#218838; color:white; border:0; padding:11px 16px;
            border-radius:7px; cursor:pointer;
        }
        .consumable-manage-btn:hover, .add-consumable-btn:hover,
        .consumable-save-btn:hover { background:#1b7030; }
        .consumable-table-container {
            background:white; border-radius:10px; overflow-x:auto; margin-bottom:30px;
        }
        .consumable-table { width:100%; border-collapse:collapse; }
        .consumable-table th { background:#f5f5f5; padding:14px; text-align:left; }
        .consumable-table td { padding:14px; border-top:1px solid #eee; }
        .consumable-status-active, .consumable-status-inactive {
            padding:5px 10px; border-radius:20px; font-size:12px;
        }
        .consumable-status-active { background:#d4edda; color:#155724; }
        .consumable-status-inactive { background:#f8d7da; color:#721c24; }
        .consumable-stock-ok { color:#218838; font-weight:bold; }
        .consumable-stock-low { color:#e67e22; font-weight:bold; }
        .consumable-stock-out { color:#dc3545; font-weight:bold; }

        .consumable-modal, .consumable-form-modal {
            display:none; position:fixed; inset:0; background:rgba(0,0,0,.55);
            justify-content:center; align-items:center; z-index:10000;
            padding:15px; box-sizing:border-box;
        }
        .consumable-form-modal { z-index:11000; }
        .consumable-modal-content, .consumable-form-content {
            background:white; max-width:95%; max-height:90vh; overflow-y:auto;
            padding:25px; border-radius:12px; box-sizing:border-box;
        }
        .consumable-modal-content { width:1000px; }
        .consumable-form-content { width:600px; }
        .consumable-modal-header {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:20px;
        }
        .consumable-modal-header h2 { margin:0; }
        .consumable-close-btn {
            background:transparent; border:0; font-size:24px; cursor:pointer; color:#555;
        }
        .consumable-modal-top {
            display:flex; justify-content:space-between; align-items:center;
            margin-bottom:15px; gap:10px;
        }
        .consumable-search { flex:1; }
        .consumable-search input {
            width:100%; box-sizing:border-box; padding:10px 12px;
            border:1px solid #ccc; border-radius:7px;
        }
        .add-consumable-btn { white-space:nowrap; }
        .consumable-actions { display:flex; gap:5px; flex-wrap:wrap; }
        .consumable-actions button {
            border:0; color:white; padding:6px 9px; border-radius:5px;
            cursor:pointer; font-size:12px;
        }
        .consumable-edit-btn { background:#007bff; }
        .consumable-toggle-btn { background:#f39c12; }
        .consumable-delete-btn { background:#dc3545; }
        .consumable-form-content h3 { margin-top:0; margin-bottom:20px; }
        .consumable-form-group { margin-bottom:15px; }
        .consumable-form-group label { display:block; margin-bottom:6px; font-weight:bold; }
        .consumable-form-group input, .consumable-form-group select {
            width:100%; box-sizing:border-box; padding:11px;
            border:1px solid #ccc; border-radius:7px; font-size:14px;
        }
        .consumable-form-group input:focus, .consumable-form-group select:focus {
            outline:none; border-color:#007bff;
        }
        .consumable-form-row {
            display:grid; grid-template-columns:1fr 1fr; gap:15px;
        }
        .consumable-form-actions {
            display:flex; justify-content:flex-end; gap:10px; margin-top:20px;
        }
        .consumable-cancel-btn {
            background:#777; color:white; border:0; padding:10px 18px;
            border-radius:7px; cursor:pointer;
        }
        .receive-help-text { display:block; margin-top:7px; color:#666; font-size:12px; }
        #receiveProductId:disabled, #receiveQuantity:disabled {
            background:#f2f2f2; cursor:not-allowed;
        }
        .alert-success, .alert-error {
            padding:12px 16px; margin:15px 0; border-radius:7px;
        }
        .alert-success { background:#d4edda; color:#155724; }
        .alert-error { background:#f8d7da; color:#721c24; }
        @media(max-width:700px) {
            .consumable-form-row { grid-template-columns:1fr; }
            .consumable-modal-top, .consumable-section-header {
                flex-direction:column; align-items:stretch;
            }
        }
    </style>
</head>

<body class="admin-workspace">
@include('partials.admin-sidebar')

<div class="main">
    <div class="topbar">
        <form method="GET" action="{{ route('inventory.management') }}">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Search Product">
        </form>
        <div class="right-section">
            <div id="datetime" class="datetime"></div>
            <div class="user">Admin</div>
        </div>
    </div>

    <h1>Inventory Management</h1>

    @if(session('success'))
        <div class="alert-success">
            <i class="fa-solid fa-circle-check"></i> {{ session('success') }}
        </div>
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

    <button type="button" class="receive-main-btn" onclick="openReceiveModal()">
        <i class="fa-solid fa-plus"></i> Receive Stock
    </button>

    <!-- REGULAR PRODUCTS -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php
                        if ($product->stock <= 0) {
                            $status = 'Out of Stock';
                            $class = 'status-low';
                        } elseif ($product->stock <= 5) {
                            $status = 'Low Stock';
                            $class = 'status-low';
                        } else {
                            $status = 'In Stock';
                            $class = 'status-ok';
                        }
                    @endphp
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->category ?? 'N/A' }}</td>
                        <td><strong>{{ $product->stock }} {{ $product->stock_unit ?? '' }}</strong></td>
                        <td class="{{ $class }}">{{ $status }}</td>
                        <td>{{ $product->updated_at ? $product->updated_at->format('M d, Y h:i A') : 'N/A' }}</td>
                        <td>
                            <button
                                type="button"
                                class="receive-btn"
                                onclick='openReceiveModal({{ $product->id }}, @json($product->name))'
                            >
                                <i class="fa-solid fa-box"></i> Receive
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- CONSUMABLES SUMMARY -->
    <div class="consumable-section-header">
        <div><h2><i class="fa-solid fa-cubes"></i> Consumables</h2></div>
        <button type="button" class="consumable-manage-btn" onclick="openConsumableModal()">
            <i class="fa-solid fa-boxes-stacked"></i> Manage Consumables
        </button>
    </div>

    <div class="consumable-table-container">
        <table class="consumable-table">
            <thead>
                <tr>
                    <th>Consumable</th><th>Category</th><th>Stock</th>
                    <th>Unit</th><th>Status</th><th>Last Updated</th>
                </tr>
            </thead>
            <tbody>
                @forelse($consumables as $consumable)
                    @php
                        if ($consumable->stock <= 0) {
                            $consumableStatus = 'Out of Stock';
                            $consumableClass = 'consumable-stock-out';
                        } elseif ($consumable->stock <= $consumable->minimum_stock) {
                            $consumableStatus = 'Low Stock';
                            $consumableClass = 'consumable-stock-low';
                        } else {
                            $consumableStatus = 'In Stock';
                            $consumableClass = 'consumable-stock-ok';
                        }
                    @endphp
                    <tr>
                        <td><strong>{{ $consumable->name }}</strong></td>
                        <td>{{ $consumable->category ?: '-' }}</td>
                        <td><strong class="{{ $consumableClass }}">{{ number_format((float)$consumable->stock, 3) }}</strong></td>
                        <td>{{ $consumable->unit }}</td>
                        <td>
                            @if($consumable->is_active)
                                <span class="consumable-status-active">Active</span>
                            @else
                                <span class="consumable-status-inactive">Inactive</span>
                            @endif
                        </td>
                        <td>{{ $consumable->updated_at ? $consumable->updated_at->format('M d, Y h:i A') : 'N/A' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" style="text-align:center;padding:30px;">
                            No consumables added yet.
                            <br>
                            <button type="button" class="add-consumable-btn" style="margin-top:10px;"
                                onclick="openConsumableModal(); openAddConsumableForm();">
                                <i class="fa-solid fa-plus"></i> Add Consumable
                            </button>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- STOCK-IN HISTORY -->
    <h2 class="history-title"><i class="fa-solid fa-clock-rotate-left"></i> Stock-In History</h2>
    <div class="table-container">
        <table>
            <thead>
                <tr><th>Date</th><th>Product</th><th>Quantity</th><th>Supplier</th><th>PO No.</th></tr>
            </thead>
            <tbody>
                @forelse($stockIns as $stockIn)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($stockIn->received_at)->format('M d, Y h:i A') }}</td>
                        <td>{{ $stockIn->product->name ?? 'N/A' }}</td>
                        <td class="quantity-plus">+{{ $stockIn->quantity }}</td>
                        <td>{{ $stockIn->supplier ?? 'N/A' }}</td>
                        <td>{{ $stockIn->po_number ?? 'N/A' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" style="text-align:center;">No stock-in history yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

<!-- RECEIVE STOCK MODAL -->
<div id="receiveModal" class="modal-overlay" style="display:none;">
    <div class="modal-box">
        <div class="modal-header">
            <h2><i class="fa-solid fa-box"></i> Receive Stock</h2>
            <button type="button" class="close-btn" onclick="closeReceiveModal()">&times;</button>
        </div>

        <form action="{{ route('inventory.updateStock') }}" method="POST" id="receiveStockForm">
            @csrf
            <div class="form-group">
                <label for="receivePurchaseOrderId">Purchase Order</label>
                <select name="purchase_order_id" id="receivePurchaseOrderId" required>
                    <option value="">Select Purchase Order</option>
                    @foreach($purchaseOrders as $purchaseOrder)
                        <option value="{{ $purchaseOrder->id }}">
                            {{ $purchaseOrder->po_number }} - {{ $purchaseOrder->supplier }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label for="receiveProductId">Product</label>
                <select name="product_id" id="receiveProductId" required disabled>
                    <option value="">Select Purchase Order First</option>
                </select>
                <small id="receiveProductInfo" class="receive-help-text">
                    Select a purchase order to view its products.
                </small>
            </div>

            <div class="form-group">
                <label for="receiveQuantity">Quantity Received</label>
                <input type="number" name="quantity" id="receiveQuantity"
                    min="1" step="1" required disabled placeholder="Select a product first">
                <small id="receiveQuantityInfo" class="receive-help-text"></small>
            </div>

            <div class="modal-actions">
                <button type="button" class="cancel-btn" onclick="closeReceiveModal()">Cancel</button>
                <button type="submit" class="submit-btn">
                    <i class="fa-solid fa-box"></i> Receive Stock
                </button>
            </div>
        </form>
    </div>
</div>

<!-- CONSUMABLE MANAGEMENT MODAL -->
<div id="consumableModal" class="consumable-modal">
    <div class="consumable-modal-content">
        <div class="consumable-modal-header">
            <h2><i class="fa-solid fa-cubes"></i> Consumables Management</h2>
            <button type="button" class="consumable-close-btn" onclick="closeConsumableModal()">&times;</button>
        </div>

        <div class="consumable-modal-top">
            <div class="consumable-search">
                <input type="text" id="consumableSearch" placeholder="Search consumable..." oninput="filterConsumables()">
            </div>
            <button type="button" class="add-consumable-btn" onclick="openAddConsumableForm()">
                <i class="fa-solid fa-plus"></i> Add Consumable
            </button>
        </div>

        <div style="overflow-x:auto;">
            <table class="consumable-table" id="consumableManagementTable">
                <thead>
                    <tr>
                        <th>Name</th><th>Category</th><th>Stock</th><th>Unit</th>
                        <th>Minimum</th><th>Price</th><th>Status</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($consumables as $consumable)
                        <tr>
                            <td>{{ $consumable->name }}</td>
                            <td>{{ $consumable->category ?: '-' }}</td>
                            <td>{{ number_format((float)$consumable->stock, 3) }}</td>
                            <td>{{ $consumable->unit }}</td>
                            <td>{{ number_format((float)$consumable->minimum_stock, 3) }}</td>
                            <td>₱{{ number_format((float)$consumable->price, 2) }}</td>
                            <td>
                                @if($consumable->is_active)
                                    <span class="consumable-status-active">Active</span>
                                @else
                                    <span class="consumable-status-inactive">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="consumable-actions">
                                    <button type="button" class="consumable-edit-btn" title="Edit"
                                        onclick='openEditConsumableForm(@json($consumable))'>
                                        <i class="fa-solid fa-pen"></i>
                                    </button>

                                    <form action="{{ route('consumables.toggle-status', $consumable->id) }}"
                                        method="POST" style="display:inline;">
                                        @csrf
                                        @method('PATCH')
                                        <button type="submit" class="consumable-toggle-btn"
                                            title="{{ $consumable->is_active ? 'Deactivate' : 'Activate' }}"
                                            onclick="return confirm('{{ $consumable->is_active ? 'Deactivate this consumable?' : 'Activate this consumable?' }}')">
                                            @if($consumable->is_active)
                                                <i class="fa-solid fa-ban"></i>
                                            @else
                                                <i class="fa-solid fa-check"></i>
                                            @endif
                                        </button>
                                    </form>

                                    <form action="{{ route('consumables.delete', $consumable->id) }}"
                                        method="POST" style="display:inline;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="consumable-delete-btn" title="Delete"
                                            onclick="return confirm('Permanently delete this consumable?')">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" style="text-align:center;padding:30px;">No consumables found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD / EDIT CONSUMABLE FORM MODAL -->
<div id="consumableFormModal" class="consumable-form-modal">
    <div class="consumable-form-content">
        <h3 id="consumableFormTitle"><i class="fa-solid fa-plus"></i> Add Consumable</h3>

        <form id="consumableForm" action="{{ route('consumables.store') }}" method="POST">
            @csrf
            <div id="consumableMethodContainer"></div>

            <div class="consumable-form-group">
                <label for="consumable_name">Consumable Name</label>
                <input type="text" name="name" id="consumable_name" placeholder="Example: Sand" required>
            </div>
            <div class="consumable-form-group">
                <label for="consumable_category">Category</label>
                <input type="text" name="category" id="consumable_category" placeholder="Example: Construction Material">
            </div>

            <div class="consumable-form-row">
                <div class="consumable-form-group">
                    <label for="consumable_unit">Unit</label>
                    <select name="unit" id="consumable_unit" required>
                        <option value="pcs">Pieces (pcs)</option>
                        <option value="kg">Kilograms (kg)</option>
                        <option value="g">Grams (g)</option>
                        <option value="m3">Cubic Meter (m³)</option>
                        <option value="ft3">Cubic Feet (ft³)</option>
                        <option value="liter">Liters (L)</option>
                        <option value="meter">Meters (m)</option>
                        <option value="bag">Bags</option>
                        <option value="box">Boxes</option>
                        <option value="roll">Rolls</option>
                    </select>
                </div>
                <div class="consumable-form-group">
                    <label for="consumable_stock">Current Stock</label>
                    <input type="number" name="stock" id="consumable_stock" min="0" step="0.001" value="0" required>
                </div>
            </div>

            <div class="consumable-form-row">
                <div class="consumable-form-group">
                    <label for="consumable_minimum_stock">Minimum Stock</label>
                    <input type="number" name="minimum_stock" id="consumable_minimum_stock" min="0" step="0.001" value="0" required>
                </div>
                <div class="consumable-form-group">
                    <label for="consumable_price">Price</label>
                    <input type="number" name="price" id="consumable_price" min="0" step="0.01" value="0" required>
                </div>
            </div>

            <div class="consumable-form-actions">
                <button type="button" class="consumable-cancel-btn" onclick="closeConsumableForm()">Cancel</button>
                <button type="submit" class="consumable-save-btn">
                    <i class="fa-solid fa-save"></i> <span id="consumableSaveText">Add Consumable</span>
                </button>
            </div>
        </form>
    </div>
</div>

@php
    $receiveOrderData = $purchaseOrders->mapWithKeys(function ($purchaseOrder) {
        return [
            $purchaseOrder->id => $purchaseOrder->items->map(function ($item) {
                $ordered = (int) $item->quantity;
                $received = (int) ($item->received_quantity ?? 0);

                return [
                    'product_id' => (int) $item->product_id,
                    'name' => $item->product->name ?? 'Unknown Product',
                    'ordered' => $ordered,
                    'received' => $received,
                    'remaining' => max(0, $ordered - $received),
                    'stock' => (int) ($item->product->stock ?? 0),
                    'unit' => $item->product->stock_unit ?? '',
                ];
            })->values()
        ];
    });
@endphp

<script>
const purchaseOrderItems = {{ \Illuminate\Support\Js::from($receiveOrderData) }};
let preferredProductId = '';

function openReceiveModal(productId = '', productName = '') {
    const poSelect = document.getElementById('receivePurchaseOrderId');
    preferredProductId = productId ? String(productId) : '';
    poSelect.value = '';
    resetReceiveProductOptions();
    document.getElementById('receiveModal').style.display = 'flex';
}

function resetReceiveProductOptions() {
    const productSelect = document.getElementById('receiveProductId');
    const quantityInput = document.getElementById('receiveQuantity');

    productSelect.innerHTML = '<option value="">Select Purchase Order First</option>';
    productSelect.disabled = true;

    quantityInput.value = '';
    quantityInput.max = '';
    quantityInput.disabled = true;
    quantityInput.setCustomValidity('');

    document.getElementById('receiveProductInfo').textContent =
        'Select a purchase order to view its products.';
    document.getElementById('receiveQuantityInfo').textContent = '';
}

function loadProductsForPurchaseOrder() {
    const poId = document.getElementById('receivePurchaseOrderId').value;
    const productSelect = document.getElementById('receiveProductId');
    const quantityInput = document.getElementById('receiveQuantity');
    const productInfo = document.getElementById('receiveProductInfo');
    const quantityInfo = document.getElementById('receiveQuantityInfo');

    productSelect.innerHTML = '<option value="">Select Product</option>';
    quantityInput.value = '';
    quantityInput.max = '';
    quantityInput.disabled = true;
    quantityInput.setCustomValidity('');
    quantityInfo.textContent = '';

    if (!poId) {
        resetReceiveProductOptions();
        return;
    }

    const items = purchaseOrderItems[poId] || [];
    const remainingItems = items.filter(item => Number(item.remaining) > 0);

    if (remainingItems.length === 0) {
        productSelect.innerHTML = '<option value="">No Remaining Products</option>';
        productSelect.disabled = true;
        productInfo.textContent = 'All products in this purchase order have been received.';
        return;
    }

    productSelect.disabled = false;

    remainingItems.forEach(item => {
        const option = document.createElement('option');
        option.value = String(item.product_id);
        option.textContent =
            `${item.name} — Remaining: ${item.remaining} | Ordered: ${item.ordered} | Received: ${item.received} | Stock: ${item.stock} ${item.unit}`;

        option.dataset.remaining = String(item.remaining);
        option.dataset.ordered = String(item.ordered);
        option.dataset.received = String(item.received);
        option.dataset.stock = String(item.stock);
        option.dataset.unit = item.unit || '';

        productSelect.appendChild(option);
    });

    productInfo.textContent = 'Only products belonging to the selected purchase order are shown.';

    if (preferredProductId) {
        const match = Array.from(productSelect.options).find(
            option => option.value === preferredProductId
        );

        if (match) {
            productSelect.value = preferredProductId;
            updateReceiveQuantityLimit();
        }
        preferredProductId = '';
    }
}

function updateReceiveQuantityLimit() {
    const productSelect = document.getElementById('receiveProductId');
    const quantityInput = document.getElementById('receiveQuantity');
    const productInfo = document.getElementById('receiveProductInfo');
    const quantityInfo = document.getElementById('receiveQuantityInfo');
    const option = productSelect.options[productSelect.selectedIndex];

    quantityInput.value = '';
    quantityInput.setCustomValidity('');

    if (!option || !option.value) {
        quantityInput.disabled = true;
        quantityInput.max = '';
        quantityInfo.textContent = '';
        return;
    }

    const remaining = Number(option.dataset.remaining || 0);
    const ordered = Number(option.dataset.ordered || 0);
    const received = Number(option.dataset.received || 0);
    const stock = Number(option.dataset.stock || 0);
    const unit = option.dataset.unit || '';

    quantityInput.disabled = false;
    quantityInput.min = '1';
    quantityInput.max = String(remaining);
    quantityInput.step = '1';
    quantityInput.placeholder = `Enter 1 to ${remaining}`;

    productInfo.textContent =
        `Ordered: ${ordered} | Already received: ${received} | Remaining: ${remaining} | Current stock: ${stock} ${unit}`;
    quantityInfo.textContent = `You can receive a maximum of ${remaining} more.`;
}

function closeReceiveModal() {
    document.getElementById('receiveModal').style.display = 'none';
}

document.getElementById('receivePurchaseOrderId')
    .addEventListener('change', loadProductsForPurchaseOrder);

document.getElementById('receiveProductId')
    .addEventListener('change', updateReceiveQuantityLimit);

document.getElementById('receiveQuantity')
    .addEventListener('input', function () {
        const max = Number(this.max);
        const value = Number(this.value);
        this.setCustomValidity(max > 0 && value > max
            ? `You can receive a maximum of ${max}.`
            : '');
    });

document.getElementById('receiveStockForm')
    .addEventListener('submit', function (event) {
        const poId = document.getElementById('receivePurchaseOrderId').value;
        const productId = document.getElementById('receiveProductId').value;
        const quantityInput = document.getElementById('receiveQuantity');
        const quantity = Number(quantityInput.value);
        const max = Number(quantityInput.max);

        if (!poId || !productId || quantity < 1 || quantity > max) {
            event.preventDefault();
            alert('Select a purchase order and one of its products. The quantity must not exceed the remaining quantity.');
        }
    });

/* CONSUMABLE MANAGEMENT */

function openConsumableModal() {
    document.getElementById('consumableModal').style.display = 'flex';
}

function closeConsumableModal() {
    document.getElementById('consumableModal').style.display = 'none';
}

function openAddConsumableForm() {
    const modal = document.getElementById('consumableFormModal');
    const form = document.getElementById('consumableForm');

    form.action = @json(route('consumables.store'));
    document.getElementById('consumableMethodContainer').innerHTML = '';
    document.getElementById('consumableFormTitle').innerHTML =
        '<i class="fa-solid fa-plus"></i> Add Consumable';
    document.getElementById('consumableSaveText').innerText = 'Add Consumable';

    document.getElementById('consumable_name').value = '';
    document.getElementById('consumable_category').value = '';
    document.getElementById('consumable_unit').value = 'pcs';
    document.getElementById('consumable_stock').value = '0';
    document.getElementById('consumable_minimum_stock').value = '0';
    document.getElementById('consumable_price').value = '0';

    modal.style.display = 'flex';
}

function openEditConsumableForm(consumable) {
    const modal = document.getElementById('consumableFormModal');
    const form = document.getElementById('consumableForm');

    form.action = @json(url('/inventory-management/consumables')) + '/' +
        encodeURIComponent(consumable.id);

    document.getElementById('consumableMethodContainer').innerHTML =
        '<input type="hidden" name="_method" value="PUT">';

    document.getElementById('consumableFormTitle').innerHTML =
        '<i class="fa-solid fa-pen"></i> Edit Consumable';
    document.getElementById('consumableSaveText').innerText = 'Save Changes';

    document.getElementById('consumable_name').value = consumable.name ?? '';
    document.getElementById('consumable_category').value = consumable.category ?? '';
    document.getElementById('consumable_unit').value = consumable.unit ?? 'pcs';
    document.getElementById('consumable_stock').value = consumable.stock ?? '0';
    document.getElementById('consumable_minimum_stock').value = consumable.minimum_stock ?? '0';
    document.getElementById('consumable_price').value = consumable.price ?? '0';

    modal.style.display = 'flex';
}

function closeConsumableForm() {
    document.getElementById('consumableFormModal').style.display = 'none';
}

function filterConsumables() {
    const filter = document.getElementById('consumableSearch').value.toLowerCase();
    const rows = document.querySelectorAll('#consumableManagementTable tbody tr');

    rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
    });
}

window.addEventListener('click', function (event) {
    const receiveModal = document.getElementById('receiveModal');
    const consumableModal = document.getElementById('consumableModal');
    const consumableFormModal = document.getElementById('consumableFormModal');

    if (event.target === receiveModal) closeReceiveModal();
    if (event.target === consumableModal) closeConsumableModal();
    if (event.target === consumableFormModal) closeConsumableForm();
});

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
</script>

</body>
</html>