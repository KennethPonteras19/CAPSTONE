
@extends('staff.layout')

@section('title', 'Point of Sale')
@section('subtitle', $draft ? 'Resuming '.$draft->number : 'Select products and create an order.')

@section('actions')
    @moduleRoute('staff', 'staff.receipts.index')<a class="button secondary" href="{{ route('staff.receipts.index') }}">Receipt Templates</a>@endmoduleRoute
    @moduleRoute('staff', 'orders')
        <a class="button secondary" href="{{ route('orders', ['status' => 'Held']) }}">Held Orders</a>
    @endmoduleRoute
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<link rel="stylesheet" href="{{ asset('css/staff/delivery-map.css') }}?v={{ filemtime(public_path('css/staff/delivery-map.css')) }}">
<link rel="stylesheet" href="{{ asset('css/staff/pos-workflow.css') }}?v={{ filemtime(public_path('css/staff/pos-workflow.css')) }}">
@endpush

@section('content')
@moduleRoute('staff', 'orders')<x-back-link :fallback="route('orders')" label="Back to Orders" />@endmoduleRoute
<div id="posError" class="notice error" role="alert" hidden></div>

<div class="pos-workspace"
     id="posApp"
     data-checkout="{{ route('pos.checkout') }}"
     data-catalog="{{ route('staff.catalog') }}"
     data-key="{{ (string) \Illuminate\Support\Str::uuid() }}">

    <nav class="pos-tabs" role="tablist" aria-label="Transaction steps">
        <button type="button" id="tab-order" role="tab" aria-selected="true" aria-controls="step-order" data-step="0"><span class="step-number">1</span> Order Details<span class="step-check" hidden aria-label="Completed">✓</span></button>
        <button type="button" id="tab-customer" role="tab" aria-selected="false" aria-controls="step-customer" tabindex="-1" data-step="1"><span class="step-number">2</span> Customer &amp; Delivery Details<span class="step-check" hidden aria-label="Completed">✓</span></button>
        <button type="button" id="tab-payment" role="tab" aria-selected="false" aria-controls="step-payment" tabindex="-1" data-step="2"><span class="step-number">3</span> Payment &amp; Checkout</button>
    </nav>
    <div class="pos-stage">
        <div class="pos-panels">
            <section id="step-order" class="pos-step" role="tabpanel" aria-labelledby="tab-order">
                <div class="pos-order-grid">
                    <section class="pos-catalog" aria-label="Product catalog">        <div class="workspace-toolbar">
            <input id="productSearch" placeholder="Search product…" aria-label="Search catalog">

            <select id="categoryFilter" aria-label="Category">
                <option value="">All categories</option>
                @foreach($products->pluck('category')->unique()->sort() as $category)
                    <option>{{ $category }}</option>
                @endforeach
            </select>

            <button type="button" class="secondary" id="refreshCatalog">Refresh</button>
        </div>

        <p class="sync-note" id="catalogStatus">Catalog refreshes every 30 seconds.</p>
        <div class="catalog-grid" id="productGrid" tabindex="0" aria-label="Products"></div>

        <noscript>
            <p class="notice error">Enable JavaScript to use Point of Sale.</p>
        </noscript>
</section>
                    <section class="panel pos-cart"><h2>Current Order</h2><div id="cartItems" class="pos-scroll" tabindex="0" aria-label="Cart items"></div><button type="button" class="danger" id="clearCart">Clear Cart</button></section>
                </div>
                <footer class="pos-step-actions"><button type="button" class="primary" data-step="1">Continue to Customer Details</button></footer>
            </section>
            <section id="step-customer" class="pos-step" role="tabpanel" aria-labelledby="tab-customer" hidden>
                <div class="panel pos-scroll" tabindex="0" aria-label="Customer and delivery information"><h2>Customer &amp; Delivery Details</h2>        <div class="form-grid">

            <label for="customerId">Customer
                <select id="customerId">
                    <option value="">Walk-in customer</option>
                    @foreach($customers as $customer)
                        <option value="{{ $customer->id }}" @selected($draft?->customer_id === $customer->id)>{{ $customer->name }}</option>
                    @endforeach
                </select>
            </label>

            <label>Customer name<input id="customerName" value="Walk-in customer" readonly></label>
            <label>Contact number<input id="customerPhone" value="" readonly placeholder="No saved contact number"></label>
            <p class="sync-note full">Name and contact number use the selected customer's saved details.</p>
            <label class="full" for="orderType">Order Type
                <select id="orderType" name="type" required>
                    <option value="Walk-in" @selected(($draft?->type ?? 'Walk-in') === 'Walk-in')>Walk-in</option>
                    <option value="Pickup" @selected(($draft?->type ?? '') === 'Pickup')>Pickup</option>
                    <option value="Delivery" @selected(($draft?->type ?? '') === 'Delivery')>Delivery</option>
                </select>
            </label>

            <!-- DELIVERY LOCATION -->
            <div class="full delivery-location" id="deliveryDetails" hidden>
        <label for="deliveryFee">Delivery Fee
            <input
                id="deliveryFee"
                type="number"
                min="0"
                max="1000000"
                step="0.01"
                value="{{ $draft?->delivery_fee ?? 0 }}"
                readonly
            >
        </label>
            <div class="full" id="deliveryMapHome"><div class="delivery-location" id="deliveryMapPanel">
                <label for="deliveryAddress">Delivery Address (required for Delivery)</label>

                <textarea
                    id="deliveryAddress"
                    maxlength="2000"
                    rows="3"
                    placeholder="Type the complete delivery address"
                    aria-describedby="deliveryAddressHelp"
                >{{ $draft?->address }}</textarea>

                <p id="deliveryAddressHelp" class="delivery-map-help">
                    Type freely, or select a map location. Add a house number, unit, or landmark.
                </p><button type="button" class="secondary" id="expandDeliveryMap" aria-haspopup="dialog" aria-controls="deliveryMapDialog">Expand map</button>
                <details id="deliveryMapTools" class="delivery-map-tools">
                <summary id="deliveryMapLabel" class="delivery-map-label">Search or pin a delivery location</summary>

                <p class="delivery-map-help" id="deliveryMapHelp">
                    Search for an address or click the map to place a pin.
                </p>

                <label for="deliveryLocationSearch">Search Location</label>

                <div class="delivery-search-row">
                    <input
                        type="search"
                        id="deliveryLocationSearch"
                        maxlength="200"
                        placeholder="Search town, street, or landmark..."
                        autocomplete="off"
                        aria-describedby="deliverySearchHelp"
                    >

                    <button type="button" class="secondary" id="searchDeliveryLocation">
                        Search
                    </button>
                </div>

                <p id="deliverySearchHelp" class="delivery-map-help">
                    Choose a search result to move the pin to that location.
                </p>

                <div
                    id="deliverySearchResults"
                    class="delivery-search-results"
                    role="group"
                    aria-label="Location search results"
                    hidden
                ></div>

                <div class="delivery-map-frame">
                    <div
                        id="deliveryMap"
                        aria-labelledby="deliveryMapLabel"
                        aria-describedby="deliveryMapHelp"
                        data-reverse-url="{{ config('services.delivery_map.reverse_url') }}"
                        data-zone-url="{{ route('delivery-zone.lookup') }}"
                    ></div>

                    <div id="deliveryMapOverlay" class="delivery-map-overlay">
                        Select Delivery to pin a location.
                    </div>
                </div>

                <div class="delivery-map-actions">
                    <button type="button" class="secondary" id="locateDelivery">
                        Use my location
                    </button>

                    <button type="button" class="secondary" id="pinDeliveryCenter">
                        Pin map center
                    </button>
                </div>

                </details>
                <p id="deliveryMapStatus" class="delivery-map-help" role="status" aria-live="polite"></p>

                <div id="deliveryZoneInfo" class="delivery-zone-info" aria-live="polite">
                    <p>
                        <strong>Delivery Zone:</strong>
                        <span id="deliveryZoneName">Not selected</span>
                    </p>

                    <p>
                        <strong>Zone Fee:</strong>
                        <span id="deliveryZoneFee">Not calculated</span>
                    </p>
                </div>


            </div>
            </div>
            </div>
        </div>

</div>
                <footer class="pos-step-actions"><button type="button" class="secondary" data-step="0">Back to Order Details</button><button type="button" class="primary" data-step="2">Continue to Payment</button></footer>
            </section>
            <section id="step-payment" class="pos-step" role="tabpanel" aria-labelledby="tab-payment" hidden>
                <div class="panel pos-scroll" tabindex="0" aria-label="Payment information"><h2>Payment &amp; Checkout</h2>
                    <h3>Selected products</h3><div id="checkoutItems"></div>        <div class="form-grid">
            <label for="paymentMethod">Payment Method
                <select id="paymentMethod">
                    <option>Cash</option>
                    <option>Online</option>
                    <option>Cheque</option>
                    <option value="Charge">Charge (Pay Later)</option>
                </select>
            </label>

            <label for="amountTendered">Amount Received
                <input id="amountTendered" type="number" min="0" max="99999999" step="0.01" value="0">
            </label>

            <label class="full" for="paymentReference">Payment Reference
                <input id="paymentReference" maxlength="255" placeholder="Required for Online / Cheque">
            </label>

            <label class="full" for="orderNotes">Notes
                <textarea id="orderNotes" maxlength="2000">{{ $draft?->notes }}</textarea>
            </label>
        </div>

        <div class="summary-list">
            <div><span>Change</span><strong id="changeDue">₱0.00</strong></div>
            <div><span>Remaining Balance</span><strong id="balanceDue">₱0.00</strong></div>
        </div>

        <p class="sync-note">
            Use Charge for an unpaid order. Partial payments require a customer.
            Held orders and quotations do not reserve stock.
        </p>


                </div>
                <footer class="pos-step-actions"><button type="button" class="secondary" data-step="1">Back to Customer Details</button><button type="button" class="primary" data-save="checkout">Complete Transaction</button></footer>
            </section>
        </div>
        <aside class="panel pos-summary" tabindex="0" aria-label="Order summary">
            <h2>Order Summary</h2>
            <p id="summaryCustomer">Walk-in customer</p><p class="sync-note" id="summaryOrderType">Walk-in</p>
            <details class="summary-products"><summary>Selected products</summary><div id="summaryItems" tabindex="0" aria-label="Selected products"></div></details>
                    <div class="summary-list">
            <div><span>Items</span><strong id="itemCount">0</strong></div>
            <div><span>Subtotal</span><strong id="subtotal">₱0.00</strong></div>
            <div><span>Discount</span><strong id="discountTotal">₱0.00</strong></div>
        </div>

<div class="summary-list"><div><span>Delivery fee</span><strong id="summaryDeliveryFee">₱0.00</strong></div></div>

        <div class="summary-list">
            <div class="grand-total">
                <span>Total</span>
                <strong id="grandTotal">₱0.00</strong>
            </div>
        </div>


            <p class="sync-note" id="summaryPayment"></p>
            <p id="orderReview" class="sync-note" aria-live="polite"></p>
                    <div class="checkout-actions">
            <button type="button" class="secondary" data-save="hold">Hold Order</button>
            <button type="button" class="secondary" data-save="quote">Save Quotation</button>
            @moduleRoute('staff', 'orders')<a class="ui-back" href="{{ route('orders') }}">Return to Orders</a>@endmoduleRoute
        </div>

        </aside>
    </div>
    <dialog id="deliveryMapDialog" class="delivery-map-dialog" aria-labelledby="expandedMapTitle">
        <header class="delivery-dialog-header"><h2 id="expandedMapTitle">Delivery Location</h2><button type="button" class="secondary" id="closeDeliveryMap">Close map</button></header>
        <div id="expandedMapContent"></div>
    </dialog>
</div>
@endsection

@push('scripts')
<script>
    window.staffPosData = {
        products: {{ \Illuminate\Support\Js::from($products) }},
        customers: {{ \Illuminate\Support\Js::from($customers) }},
        draft: {{ \Illuminate\Support\Js::from($draft) }}
    };
</script>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
        defer></script>

<script src="{{ asset('js/staff-delivery-map.js') }}?v={{ filemtime(public_path('js/staff-delivery-map.js')) }}" defer></script>
<script src="{{ asset('js/staff-pos.js') }}?v={{ filemtime(public_path('js/staff-pos.js')) }}" defer></script>
@endpush
