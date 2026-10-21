<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Checkout - TMTA Construction Supply</title>

    @include('customer.partials.assets')
    <link rel="stylesheet" href="{{ asset('css/costumerkiosk_css/checkout.css') . '?v=' . filemtime(public_path('css/costumerkiosk_css/checkout.css')) }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
          crossorigin="">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
            crossorigin="" defer></script>
    <script src="{{ asset('js/customer-checkout-map.js') . '?v=' . filemtime(public_path('js/customer-checkout-map.js')) }}" defer></script>
</head>

<body>

    <!-- =========================
         SIDEBAR
    ========================== -->

    @include('customer.partials.sidebar')



    <!-- =========================
         MAIN CONTENT
    ========================== -->

    <main class="main-content">




        <!-- =========================
             CHECKOUT
        ========================== -->

        <section class="checkout-page">


            <div class="checkout-header">

                <div>

                    <h1>
                        Checkout
                    </h1>

                    <p>
                        Choose how you would like to receive your order.
                    </p>

                </div>


                <button
                    type="button"
                    class="back-cart-button"
                    @disabled(!\App\Support\ModuleAccess::allows('customer', 'cart')) onclick="goBackToCart()">

                    ← Back to Cart

                </button>

            </div>



            <div class="checkout-layout">


                <!-- =========================
                     LEFT SIDE
                ========================== -->

                <div class="checkout-form">


                    <!-- ORDER METHOD -->

                    <div class="checkout-card">

                        <h2>
                            Order Method
                        </h2>


                        <p class="schedule-description">

                            Select whether you want to pick up your order
                            or have it delivered.

                        </p>



                        <div class="fulfillment-options">


                            <!-- PICK UP -->

                            <div
                                class="fulfillment-option selected"
                                id="pickupOption"
                                onclick="changeFulfillment('pickup')">

                                <div class="fulfillment-icon">
                                    🛍
                                </div>


                                <div>

                                    <strong>
                                        Pick Up
                                    </strong>

                                    <span>
                                        Pick up your order at the store
                                    </span>

                                </div>

                            </div>



                            <!-- DELIVERY -->

                            <div
                                class="fulfillment-option"
                                id="deliveryOption"
                                onclick="changeFulfillment('delivery')">

                                <div class="fulfillment-icon">
                                    🚚
                                </div>


                                <div>

                                    <strong>
                                        Delivery
                                    </strong>

                                    <span>
                                        Have your order delivered
                                    </span>

                                </div>

                            </div>


                        </div>

                    </div>



                    <!-- =========================
                         DELIVERY LOCATION MAP
                    ========================== -->

                    <div class="checkout-card" id="checkoutDeliveryMapCard" style="display: none;">

                        <h2>Delivery Location &amp; Access</h2>

                        <div class="checkout-location-layout">
                            <div class="checkout-location-details">
                                <p class="schedule-description">
                                    Pin the delivery point on the map. The detected location will appear below.
                                </p>

                                <div class="checkout-map-controls">
                                    <p id="checkoutMapStatus" role="status" aria-live="polite">
                                        Click the map to place a delivery pin.
                                    </p>
                                </div>

                                <p class="checkout-pinned-location">
                                    <strong>Pinned location:</strong>
                                    <span id="checkoutPinnedLocation" aria-live="polite">No location pinned</span>
                                </p>

                                <input type="hidden" id="address">

                                <div class="checkout-delivery-fields">
                                    <div class="checkout-field">
                                        <label for="deliveryBlock">Building / Block (optional)</label>
                                        <input
                                            type="text"
                                            id="deliveryBlock"
                                            maxlength="255"
                                            placeholder="Building name, block, or subdivision">
                                    </div>

                                    <div class="checkout-field">
                                        <label for="deliveryFloor">Floor (optional)</label>
                                        <input
                                            type="text"
                                            id="deliveryFloor"
                                            maxlength="255"
                                            placeholder="e.g., 2nd floor">
                                    </div>

                                    <div class="checkout-field">
                                        <label for="deliveryUnit">Unit / Lot (optional)</label>
                                        <input
                                            type="text"
                                            id="deliveryUnit"
                                            maxlength="255"
                                            placeholder="e.g., Unit 201 or Lot 8">
                                    </div>

                                    <div class="checkout-field">
                                        <label for="deliveryDriverNotes">Landmarks / Delivery Instructions (optional)</label>
                                        <textarea
                                            id="deliveryDriverNotes"
                                            rows="3"
                                            maxlength="500"
                                            placeholder="Nearby landmarks, gate instructions, or directions for the driver"></textarea>
                                    </div>
                                </div>
                            </div>

                            <div class="checkout-map-frame">
                                <div
                                    id="checkoutDeliveryMap"
                                    role="application"
                                    aria-label="Map for choosing the delivery location"
                                    data-reverse-url="{{ config('services.delivery_map.reverse_url') }}">
                                </div>
                            </div>
                        </div>

                    </div>



                    <!-- =========================
                         PICKUP SCHEDULE
                    ========================== -->

                    <div
                        class="checkout-card"
                        id="pickupSchedule">

                        <h2>
                            Pickup Schedule
                        </h2>


                        <p class="schedule-description">

                            Select your preferred pickup date.

                        </p>



                        <div class="schedule-fields">


                            <!-- PICKUP DATE -->

                            <div class="checkout-field">

                                <label for="pickupDate">

                                    Pickup Date

                                </label>


                                <input
                                    type="date"
                                    id="pickupDate"
                                    required>

                            </div>



                        </div>

                    </div>



                    <!-- =========================
                         DELIVERY SCHEDULE
                    ========================== -->

                    <div
                        class="checkout-card"
                        id="deliverySchedule"
                        style="display: none;">

                        <h2>
                            Delivery Schedule
                        </h2>


                        <p class="schedule-description">

                            Select your preferred delivery date and time.

                        </p>



                        <div class="schedule-fields">


                            <!-- DELIVERY DATE -->

                            <div class="checkout-field">

                                <label for="deliveryDate">

                                    Delivery Date

                                </label>


                                <input
                                    type="date"
                                    id="deliveryDate">

                            </div>





                        </div>

                    </div>



                    <!-- =========================
                         CUSTOMER INFORMATION
                    ========================== -->

                    <div class="checkout-card">

                        <h2>
                            Customer Information
                        </h2>



                        <div class="customer-fields">


                            <div class="checkout-field">

                                <label for="customerName">

                                    Full Name

                                </label>


                                <input
                                    type="text"
                                    id="customerName" value="{{ $customer->name }}" maxlength="255"
                                    placeholder="Enter your full name"
                                    required>

                            </div>



                            <div class="checkout-field">

                                <label for="customerContact">

                                    Contact Number

                                </label>


                                <input
                                    type="tel"
                                    id="customerContact" value="{{ $customer->phone }}" maxlength="40"
                                    placeholder="Enter your contact number"
                                    required>

                            </div>


                        </div>

                    </div>


                </div>



                <!-- =========================
                     ORDER SUMMARY
                ========================== -->

                <aside class="checkout-summary">

                    <div class="checkout-card">

                        <h2>
                            Order Summary
                        </h2>


                        <div
                            class="checkout-items"
                            id="checkoutItems">
                        </div>



                        <div class="checkout-divider">
                        </div>



                        <div class="checkout-summary-line">

                            <span>
                                Subtotal
                            </span>

                            <strong id="checkoutSubtotal">
                                ₱0.00
                            </strong>

                        </div>



                        <div class="checkout-summary-line">

                            <span>
                                Delivery Fee
                            </span>

                            <strong id="deliveryFee">
                                ₱0.00
                            </strong>

                        </div>



                        <div class="checkout-divider">
                        </div>



                        <div class="checkout-total">

                            <span>
                                Total
                            </span>

                            <strong id="checkoutTotal">
                                ₱0.00
                            </strong>

                        </div>



                        <button
                            type="button"
                            class="place-order-button"
                            onclick="placeOrder()">

                            Place Order

                        </button>

                    </div>

                </aside>


            </div>

        </section>

    </main>



    @include('customer.partials.backend')
    <script>

        let cart = customerCart.items;
        let placingOrder = false;

        let fulfillmentMethod = "pickup";



        /* =========================================
           LOAD CART
        ========================================= */

        function loadCart() {
            displayCart();
        }



        /* =========================================
           DISPLAY CART
        ========================================= */

        function displayCart() {

            const container =
                document.getElementById(
                    "checkoutItems"
                );


            const subtotalElement =
                document.getElementById(
                    "checkoutSubtotal"
                );


            const deliveryFeeElement =
                document.getElementById(
                    "deliveryFee"
                );


            const totalElement =
                document.getElementById(
                    "checkoutTotal"
                );


            container.innerHTML = "";


            if (
                !cart ||
                cart.length === 0
            ) {

                container.innerHTML = `

                    <div class="empty-cart-message">

                        <p>
                            Your cart is empty.
                        </p>

                    </div>

                `;


                subtotalElement.textContent =
                    "₱0.00";


                deliveryFeeElement.textContent =
                    "₱0.00";


                totalElement.textContent =
                    "₱0.00";


                return;

            }



            let subtotal = 0;



            cart.forEach(function(item) {

                const name =
                    item.name || "Product";


                const quantity =
                    Number(item.quantity) || 1;


                const price =
                    Number(item.price) || 0;


                const itemTotal =
                    quantity * price;


                subtotal += itemTotal;



                const itemElement =
                    document.createElement("div");


                itemElement.className =
                    "checkout-item";


                itemElement.innerHTML = `

                    <div class="checkout-item-info">

                        <strong>
                            ${CustomerWorkspace.escape(name)}
                        </strong>

                        <span>
                            ${quantity} × ₱${price.toFixed(2)}
                        </span>

                    </div>


                    <strong>
                        ₱${itemTotal.toFixed(2)}
                    </strong>

                `;


                container.appendChild(
                    itemElement
                );

            });



            let deliveryFee = 0;



            if (
                fulfillmentMethod ===
                "delivery"
            ) {

                deliveryFee = {{ \Illuminate\Support\Js::from($deliveryFee) }};

            }



            const total =
                subtotal + deliveryFee;



            subtotalElement.textContent =
                "₱" +
                subtotal.toFixed(2);


            deliveryFeeElement.textContent =
                "₱" +
                deliveryFee.toFixed(2);


            totalElement.textContent =
                "₱" +
                total.toFixed(2);

        }



        /* =========================================
           CHANGE FULFILLMENT
        ========================================= */

        function changeFulfillment(method) {

            fulfillmentMethod =
                method;



            const pickupOption =
                document.getElementById(
                    "pickupOption"
                );


            const deliveryOption =
                document.getElementById(
                    "deliveryOption"
                );


            const pickupSchedule =
                document.getElementById(
                    "pickupSchedule"
                );


            const deliverySchedule =
                document.getElementById(
                    "deliverySchedule"
                );


            const deliveryMapCard =
                document.getElementById(
                    "checkoutDeliveryMapCard"
                );



            if (
                method === "pickup"
            ) {

                pickupOption.classList.add(
                    "selected"
                );


                deliveryOption.classList.remove(
                    "selected"
                );



                pickupSchedule.style.display =
                    "block";


                deliverySchedule.style.display =
                    "none";


                deliveryMapCard.style.display =
                    "none";

            }

            else {

                pickupOption.classList.remove(
                    "selected"
                );


                deliveryOption.classList.add(
                    "selected"
                );



                pickupSchedule.style.display =
                    "none";


                deliverySchedule.style.display =
                    "block";


                deliveryMapCard.style.display =
                    "block";

                window.initCheckoutDeliveryMap?.();

            }



            displayCart();

        }



        /* =========================================
           GET TOMORROW
        ========================================= */

        function getTomorrow() {
            return {{ \Illuminate\Support\Js::from($minimumDate) }};
        }



        /* =========================================
           SET MINIMUM DATES
        ========================================= */

        function setMinimumDates() {

            const tomorrow =
                getTomorrow();


            const pickupDate =
                document.getElementById(
                    "pickupDate"
                );


            const deliveryDate =
                document.getElementById(
                    "deliveryDate"
                );


            pickupDate.min =
                tomorrow;


            deliveryDate.min =
                tomorrow;

        }



        /* =========================================
           VALIDATE DATE
        ========================================= */

        function isValidDate(dateValue) {
            return Boolean(dateValue) && dateValue >= getTomorrow();
        }



        /* =========================================
           BACK TO CART
        ========================================= */

        function goBackToCart() {
            if (!placingOrder) window.location.href = "{{ route('customer.cart') }}";
        }



        /* =========================================
           PLACE ORDER
        ========================================= */

        async function placeOrder() {
            if (placingOrder) return;


            /* =====================================
               CART
            ====================================== */

            if (
                !cart ||
                cart.length === 0
            ) {

                alert(
                    "Your cart is empty."
                );

                return;

            }



            /*
             * CUSTOMER
             */

            const customerName =
                document.getElementById(
                    "customerName"
                ).value.trim();


            const customerContact =
                document.getElementById(
                    "customerContact"
                ).value.trim();



            if (!customerName) {

                alert(
                    "Please enter your full name."
                );


                document.getElementById(
                    "customerName"
                ).focus();


                return;

            }



            if (!customerContact) {

                alert(
                    "Please enter your contact number."
                );


                document.getElementById(
                    "customerContact"
                ).focus();


                return;

            }



            /* =====================================
               PICKUP
            ====================================== */

            if (
                fulfillmentMethod ===
                "pickup"
            ) {

                const pickupDate =
                    document.getElementById(
                        "pickupDate"
                    ).value;


                /*
                 * DATE
                 */

                if (!pickupDate) {

                    alert(
                        "Please select a pickup date."
                    );

                    return;

                }



                if (
                    !isValidDate(
                        pickupDate
                    )
                ) {

                    alert(
                        "Pickup must be scheduled for tomorrow or a later date."
                    );

                    return;

                }



            }



            /* =====================================
               DELIVERY
            ====================================== */

            if (
                fulfillmentMethod ===
                "delivery"
            ) {

                const deliveryDate =
                    document.getElementById(
                        "deliveryDate"
                    ).value;


                const address =
                    document.getElementById(
                        "address"
                    ).value.trim();



                /*
                 * DATE
                 */

                if (!deliveryDate) {

                    alert(
                        "Please select a delivery date."
                    );

                    return;

                }



                if (
                    !isValidDate(
                        deliveryDate
                    )
                ) {

                    alert(
                        "Delivery must be scheduled for tomorrow or a later date."
                    );

                    return;

                }



                /*
                 * ADDRESS
                 */

                if (!address) {

                    alert(
                        "Pin your delivery location on the map and wait for the address to be detected."
                    );

                    document.getElementById("checkoutDeliveryMap").scrollIntoView({
                        behavior: "smooth",
                        block: "center"
                    });

                    return;

                }

            }



            /* =====================================
               ORDER SUCCESS
            ====================================== */

            placingOrder = true;
            const button = document.querySelector('.place-order-button');
            button.disabled = true;
            const deliveryAddress = [
                document.getElementById('address').value.trim(),
                ...[
                    ['Building / Block', document.getElementById('deliveryBlock').value.trim()],
                    ['Floor', document.getElementById('deliveryFloor').value.trim()],
                    ['Unit / Lot', document.getElementById('deliveryUnit').value.trim()],
                    ['Landmarks / Delivery Instructions', document.getElementById('deliveryDriverNotes').value.trim()]
                ]
                    .filter(([, value]) => value)
                    .map(([label, value]) => `${label}: ${value}`)
            ].filter(Boolean).join(', ');

            try {
                const result = await CustomerWorkspace.request("{{ route('customer.checkout.store') }}", 'POST', {
                    request_key: customerCart.checkout_key,
                    version: customerCart.version,
                    fulfillment: fulfillmentMethod,
                    name: customerName,
                    phone: customerContact,
                    address: fulfillmentMethod === 'delivery' ? deliveryAddress : null,
                    date: document.getElementById(fulfillmentMethod + 'Date').value,
                    time: '08:00',
                    items: cart.map(item => ({product_id: item.id, quantity: item.quantity, expected_price: item.price}))
                });
                alert('Order placed successfully! Order number: ' + result.number);
                window.location.href = result.redirect;
            } catch (error) {
                alert(error.message);
                placingOrder = false;
                button.disabled = false;
            }
        }



        /* =========================================
           PAGE LOAD
        ========================================= */

        document.addEventListener(
            "DOMContentLoaded",
            function() {

                setMinimumDates();

                loadCart();

                changeFulfillment(
                    "pickup"
                );

            }
        );

    </script>

</body>
</html>
