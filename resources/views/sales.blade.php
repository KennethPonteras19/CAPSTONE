<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Sales Management | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    @include('partials.admin-sidebar-assets')
</head>

<body class="admin-workspace">

@include('partials.admin-sidebar')

<main class="main">

    <header class="topbar">
        <div>
            <h2>Sales Management</h2>
            <p>Monitor transactions, payments, and outstanding balances.</p>
        </div>

        <div class="right-section">
            <div id="datetime" class="datetime"></div>
            <div class="user">Admin</div>
        </div>
    </header>

    @if(session('success'))
        <div class="success-box">{{ session('success') }}</div>
    @endif

    @if(session('error'))
        <div class="error-box">{{ session('error') }}</div>
    @endif

    @if($errors->any())
        <div class="error-box">
            <strong>Please check the following:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section class="header-row">
        <div>
            
        </div>

        <button class="add-btn" type="button" onclick="openSaleModal()">
            <i class="fa-solid fa-plus"></i>
            Add Sale
        </button>
    </section>

    {{-- SALES SUMMARY --}}
    <section class="ui-metrics">

        <x-metric label="Orders Today" :value="number_format($todayOrders)" tone="info" icon="fa-receipt" hint="Distinct receipts created today" />

        <x-metric label="Sales Revenue Today" :value="'₱'.number_format($todayRevenue, 2)" tone="success" icon="fa-peso-sign" hint="Total recorded sales value today" />

        <x-metric label="Payments Collected Today" :value="'₱'.number_format($paymentsCollected, 2)" tone="success" icon="fa-circle-check" hint="Payments recorded against today&#039;s sales" />

        <x-metric label="Outstanding Balance Today" :value="'₱'.number_format($outstandingBalance, 2)" tone="warning" icon="fa-clock" hint="Remaining balance on today&#039;s sales" />

    </section>

    <section class="ui-metrics">
        <x-metric label="Average Order Value Today" :value="'₱'.number_format($averageOrder, 2)" icon="fa-chart-line" />

        <x-metric label="Top Product by Quantity" :value="$topProduct ?: 'No sales yet'" icon="fa-box" />

        <x-metric label="Top Staff by Recorded Sales" :value="$topStaff ?: 'No sales yet'" icon="fa-user" />
    </section>

    {{-- TRANSACTIONS --}}
    <section class="table-container">
        <div class="table-heading">
            <div>
                <h2>Sales Transactions</h2>
                @moduleRoute('admin', 'sales.receipts.index')<a href="{{ route('sales.receipts.index') }}">Receipt Templates</a>@endmoduleRoute
                <p>Click a receipt to see its transaction details.</p>
            </div>

            <form method="GET" action="{{ route('sales') }}" class="sales-search">
                <input
                    type="text"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search receipt, product, or staff">

                <button type="submit" aria-label="Search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                </button>
            </form>
        </div>

        @php
            $groupedSales = $sales->groupBy('receipt_no');
        @endphp

        <div class="sales-table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Receipt</th>
                        <th>Date</th>
                        <th>Staff</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Payment</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($groupedSales as $receipt => $items)
                        @php
                            $first = $items->first();

                            $total = round(
                                $items->sum('total_amount'),
                                2
                            );

                            $paid = round(
                                $items->sum('paid_amount'),
                                2
                            );

                            $balance = max(
                                0,
                                round($total - $paid, 2)
                            );

                            $date = $first->created_at;
                            $staff = $first->staff_name;
                            $status = $first->status;

                            $payment = $balance <= 0
                                ? 'Completed'
                                : ($paid > 0 ? 'Partial' : 'Unpaid');

                            $itemList = $items->map(function ($item) {
                                return $item->item_name . ' x' . $item->qty;
                            })->implode(', ');

                            $receiptDetails = [
                                'receipt' => (string) $receipt,
                                'order_id' => $items->first()->order_id,
                                'date' => $date
                                    ? $date->format('M d, Y h:i A')
                                    : 'N/A',
                                'staff' => $staff,
                                'items' => $itemList,
                                'total' => $total,
                                'paid' => $paid,
                                'balance' => $balance,
                                'status' => $status,
                                'payment' => $payment,
                            ];
                        @endphp

                        <tr class="sale-row"
                            onclick='openReceiptModal(@json($receiptDetails))'>
                            <td>#{{ $receipt }}</td>

                            <td>
                                {{ $date ? $date->format('M d, Y h:i A') : 'N/A' }}
                            </td>

                            <td>{{ $staff }}</td>
                            <td>{{ $itemList }}</td>
                            <td>₱{{ number_format($total, 2) }}</td>
                            <td>₱{{ number_format($paid, 2) }}</td>
                            <td>₱{{ number_format($balance, 2) }}</td>

                            <td>
                                <span class="status-pill">
                                    {{ $status ?: 'Completed' }}
                                </span>
                            </td>

                            <td>
                                <span class="payment-pill {{ strtolower($payment) }}">
                                    {{ $payment }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="empty-state">
                                No sales records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

</main>

{{-- RECEIPT DETAILS MODAL --}}
<div id="receiptModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Receipt Details</h2>
            <button type="button" class="close-btn"
                    onclick="closeReceiptModal()">&times;</button>
        </div>

        <div class="receipt-details">
            <p><strong>Receipt:</strong> <span id="m-receipt"></span></p>
            <p><strong>Date:</strong> <span id="m-date"></span></p>
            <p><strong>Staff:</strong> <span id="m-staff"></span></p>
            <p><strong>Items:</strong> <span id="m-items"></span></p>
            <p><strong>Total:</strong> ₱<span id="m-total"></span></p>
            <p><strong>Paid:</strong> ₱<span id="m-paid"></span></p>
            <p><strong>Balance:</strong> ₱<span id="m-balance"></span></p>
            <p><strong>Order Status:</strong> <span id="m-status"></span></p>
            <p><strong>Payment Status:</strong> <span id="m-payment"></span></p>
        </div>

        <div class="modal-actions">
            <button
    type="button"
    id="receivePaymentButton"
    class="add-btn"
    onclick="openPaymentModal()"
    style="display: none;">

    <i class="fa-solid fa-money-bill-wave"></i>
    <span id="receivePaymentButtonText">Receive Payment</span>
</button>
            <button type="button" class="cancel-btn" onclick="closeReceiptModal()">Close</button>
            @moduleRoute('admin', 'sales.receipts.print')<a id="receiptTemplateLink" target="_blank" rel="noopener" hidden>Print Receipt</a>@endmoduleRoute
        </div>
    </div>
</div>

{{-- RECEIVE PAYMENT MODAL --}}
<div id="paymentModal" class="modal" style="display:none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Receive Payment</h2>
            <button type="button" class="close-btn" onclick="closePaymentModal()">&times;</button>
        </div>

        <p>Receipt: <strong id="paymentReceiptLabel"></strong></p>
        <p>Total: ₱<strong id="paymentTotalLabel">0.00</strong></p>
        <p>Already Paid: ₱<strong id="paymentPaidLabel">0.00</strong></p>
        <p>Remaining Balance: ₱<strong id="paymentBalanceLabel">0.00</strong></p>

        <form method="POST" id="receivePaymentForm" action="">
            @csrf
            <label for="receive_payment_amount">Amount Received (₱)</label>
            <input
                type="number"
                id="receive_payment_amount"
                name="amount"
                min="0.01"
                step="0.01"
                required>
            <div class="modal-actions">
                <button type="submit" class="add-btn">Save Payment</button>
                <button type="button" class="cancel-btn" onclick="closePaymentModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

{{-- ADD SALE MODAL --}}
<div id="saleModal" class="modal" style="display:none;">
    <div class="modal-content add-sale-modal">

        <div class="modal-header">
            <h2>Record a Sale</h2>
            <button type="button" class="close-btn"
                    onclick="closeSaleModal()">&times;</button>
        </div>

        <p class="form-description">
            Use this form to record a direct sale made by the admin.
            Cashier transactions can continue through the staff POS.
        </p>

        <form method="POST" action="{{ route('sales.storeMultiple') }}" id="addSaleForm">
            @csrf

            <label for="productCount">Number of Products</label>
            <select id="productCount">
                <option value="1">1 product</option>
                <option value="2">2 products</option>
                <option value="3">3 products</option>
                <option value="4">4 products</option>
                <option value="5">5 products</option>
            </select>

            <div id="saleProductRows">
                <div class="sale-product-row">
                    <label>Product</label>
                    <select name="product_id[]" class="product-select" required>
                        <option value="">Select product</option>
                        @foreach($products as $product)
                            <option value="{{ $product->id }}"
                                    data-price="{{ $product->price }}"
                                    data-stock="{{ $product->stock }}">
                                {{ $product->name }}
                                — ₱{{ number_format($product->price, 2) }}
                                (Stock: {{ $product->stock }})
                            </option>
                        @endforeach
                    </select>

                    <label>Quantity</label>
                    <input type="number" name="qty[]" class="qty-input"
                           min="1" value="1" required>
                </div>
            </div>

            <div class="sale-total-preview">
                <span>Estimated Total</span>
                <strong id="estimatedTotal">₱0.00</strong>
            </div>

            <label for="payment_status">Payment Status</label>
            <select name="payment_status" id="payment_status" required>
                <option value="Paid">Paid in Full</option>
                <option value="Partial">Partial Payment</option>
                <option value="Unpaid">Unpaid</option>
            </select>

            <div id="paidAmountWrapper" style="display:none;">
                <label for="paid_amount">Amount Received (₱)</label>
                <input type="number" name="paid_amount" id="paid_amount"
                       min="0.01" step="0.01" placeholder="Enter amount received">
                <small>Enter the actual amount received from the customer.</small>
            </div>

            <div class="modal-actions">
                <button type="submit" class="add-btn">Save Sale</button>
                <button type="button" class="cancel-btn"
                        onclick="closeSaleModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
    function updateDateTime() {
        const element = document.getElementById('datetime');

        if (element) {
            element.textContent = new Date().toLocaleString('en-PH', {
                dateStyle: 'medium',
                timeStyle: 'medium'
            });
        }
    }

    updateDateTime();
    setInterval(updateDateTime, 1000);

    function openSaleModal() {
        document.getElementById('saleModal').style.display = 'flex';
        updateSaleTotal();
    }

    function closeSaleModal() {
        document.getElementById('saleModal').style.display = 'none';
    }

    function closeReceiptModal() {
        document.getElementById('receiptModal').style.display = 'none';
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    let currentReceiptData = null;

    function openReceiptModal(data) {
        currentReceiptData = data;
        const templateLink = document.getElementById('receiptTemplateLink');
        if (templateLink) {
            templateLink.hidden = false;
            templateLink.href = data.order_id
                ? @json(route('sales.receipts.print', ['order' => '__ORDER__'])).replace('__ORDER__', encodeURIComponent(data.order_id))
                : @json(route('sales.receipts.legacy')) + '?receipt=' + encodeURIComponent(data.receipt);
        }

        document.getElementById('m-receipt').textContent = '#' + data.receipt;
        document.getElementById('m-date').textContent = data.date;
        document.getElementById('m-staff').textContent = data.staff || 'N/A';
        document.getElementById('m-items').textContent = data.items;
        document.getElementById('m-total').textContent = formatMoney(data.total);
        document.getElementById('m-paid').textContent = formatMoney(data.paid);
        document.getElementById('m-balance').textContent = formatMoney(data.balance);
        document.getElementById('m-status').textContent = data.status || 'Completed';
        document.getElementById('m-payment').textContent = data.payment;

        const paymentButton = document.getElementById('receivePaymentButton');
        const paymentButtonText = document.getElementById('receivePaymentButtonText');
        const balance = Number(data.balance || 0);
        const paid = Number(data.paid || 0);

        if (balance > 0) {
            paymentButton.style.display = 'inline-flex';
            paymentButtonText.textContent = paid > 0
                ? 'Edit Payment '
                : 'Receive Payment';
        } else {
            paymentButton.style.display = 'none';
        }

        document.getElementById('receiptModal').style.display = 'flex';
    }

    function openPaymentModal() {
        if (!currentReceiptData) return;

        const receipt = String(currentReceiptData.receipt);
        const urlTemplate = @json(route('sales.receivePayment', ['receipt' => '__RECEIPT__']));
        document.getElementById('receivePaymentForm').action =
            urlTemplate.replace('__RECEIPT__', encodeURIComponent(receipt));

        document.getElementById('paymentReceiptLabel').textContent = '#' + receipt;
        document.getElementById('paymentTotalLabel').textContent = formatMoney(currentReceiptData.total);
        document.getElementById('paymentPaidLabel').textContent = formatMoney(currentReceiptData.paid);
        document.getElementById('paymentBalanceLabel').textContent = formatMoney(currentReceiptData.balance);

        const amountInput = document.getElementById('receive_payment_amount');
        amountInput.value = '';
        amountInput.max = Number(currentReceiptData.balance || 0).toFixed(2);

        document.getElementById('paymentModal').style.display = 'flex';
    }

    function closePaymentModal() {
        document.getElementById('paymentModal').style.display = 'none';
    }

    // Build the requested number of product rows.
    const productCount = document.getElementById('productCount');
    const productRows = document.getElementById('saleProductRows');

    const productOptions = {{ Illuminate\Support\Js::from(
    $products->map(function ($product) {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'price' => (float) $product->price,
            'stock' => (int) $product->stock,
        ];
    })->values()
) }};

    function buildProductRow() {
        const row = document.createElement('div');
        row.className = 'sale-product-row';

        const labelProduct = document.createElement('label');
        labelProduct.textContent = 'Product';

        const select = document.createElement('select');
        select.name = 'product_id[]';
        select.className = 'product-select';
        select.required = true;

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = 'Select product';
        select.appendChild(placeholder);

        productOptions.forEach(function (product) {
            const option = document.createElement('option');
            option.value = product.id;
            option.dataset.price = product.price;
            option.dataset.stock = product.stock;
            option.textContent =
                product.name + ' — ₱' + formatMoney(product.price) +
                ' (Stock: ' + product.stock + ')';

            select.appendChild(option);
        });

        const labelQty = document.createElement('label');
        labelQty.textContent = 'Quantity';

        const qty = document.createElement('input');
        qty.type = 'number';
        qty.name = 'qty[]';
        qty.className = 'qty-input';
        qty.min = '1';
        qty.value = '1';
        qty.required = true;

        row.append(labelProduct, select, labelQty, qty);
        return row;
    }

    productCount.addEventListener('change', function () {
        const count = Number(this.value);
        productRows.innerHTML = '';

        for (let i = 0; i < count; i++) {
            productRows.appendChild(buildProductRow());
        }

        updateSaleTotal();
    });

    function updateSaleTotal() {
        let total = 0;
        const selectedProducts = new Set();

        productRows.querySelectorAll('.sale-product-row').forEach(function (row) {
            const select = row.querySelector('.product-select');
            const qtyInput = row.querySelector('.qty-input');
            const option = select.selectedOptions[0];

            if (select.value && option) {
                if (selectedProducts.has(select.value)) {
                    select.setCustomValidity('Select each product only once.');
                } else {
                    select.setCustomValidity('');
                    selectedProducts.add(select.value);
                }

                const price = Number(option.dataset.price || 0);
                const stock = Number(option.dataset.stock || 0);
                const qty = Number(qtyInput.value || 0);

                qtyInput.max = String(stock);

                if (qty > stock) {
                    qtyInput.setCustomValidity('Quantity exceeds available stock.');
                } else {
                    qtyInput.setCustomValidity('');
                }

                total += price * qty;
            } else {
                select.setCustomValidity('');
                qtyInput.setCustomValidity('');
            }
        });

        document.getElementById('estimatedTotal').textContent =
            '₱' + formatMoney(total);

        updatePaymentFields(total);
    }

    productRows.addEventListener('change', updateSaleTotal);
    productRows.addEventListener('input', updateSaleTotal);

    const paymentStatus = document.getElementById('payment_status');
    const paidAmount = document.getElementById('paid_amount');
    const paidAmountWrapper = document.getElementById('paidAmountWrapper');

    function updatePaymentFields(total) {
        if (paymentStatus.value === 'Partial') {
            paidAmountWrapper.style.display = 'block';
            paidAmount.required = true;
            paidAmount.readOnly = false;
            paidAmount.max = total > 0 ? (total - 0.01).toFixed(2) : '';
        } else {
            paidAmountWrapper.style.display = 'none';
            paidAmount.required = false;
            paidAmount.readOnly = true;
            paidAmount.value = '';
            paidAmount.setCustomValidity('');
        }
    }

    paymentStatus.addEventListener('change', updateSaleTotal);

    paidAmount.addEventListener('input', function () {
        const total = Array.from(
            productRows.querySelectorAll('.sale-product-row')
        ).reduce(function (sum, row) {
            const select = row.querySelector('.product-select');
            const qty = Number(row.querySelector('.qty-input').value || 0);
            const option = select.selectedOptions[0];

            return sum + (
                select.value && option
                    ? Number(option.dataset.price || 0) * qty
                    : 0
            );
        }, 0);

        const paid = Number(this.value || 0);

        this.setCustomValidity(
            paid <= 0 || paid >= total
                ? 'Partial payment must be greater than zero and less than the total.'
                : ''
        );
    });

    document.getElementById('receive_payment_amount').addEventListener('input', function () {
        const balance = Number(currentReceiptData?.balance || 0);
        const amount = Number(this.value || 0);
        this.setCustomValidity(
            amount <= 0 || amount > balance
                ? 'Payment must be greater than zero and cannot exceed the remaining balance.'
                : ''
        );
    });

    document.getElementById('receivePaymentForm').addEventListener('submit', function (event) {
        const amountInput = document.getElementById('receive_payment_amount');
        amountInput.dispatchEvent(new Event('input', { bubbles: true }));
        if (!amountInput.checkValidity()) {
            event.preventDefault();
            amountInput.reportValidity();
        }
    });

    document.getElementById('addSaleForm').addEventListener('submit', function (event) {
        updateSaleTotal();

        if (!this.checkValidity()) {
            event.preventDefault();
            this.reportValidity();
        }
    });

    window.addEventListener('click', function (event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = 'none';
        }
    });

    // Reopen the Add Sale form when validation fails.
    @if($errors->any())
        document.getElementById('saleModal').style.display = 'flex';
    @endif
</script>

</body>
</html>

```
