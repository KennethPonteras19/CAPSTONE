<!DOCTYPE html>

<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cart - TMTA Construction Supply</title>
    @include('customer.partials.assets')
</head>

<body>

<div class="app">

<!-- SIDEBAR -->

@include('customer.partials.sidebar')

<!-- MAIN CONTENT -->

<main class="main-content cart-content">

<!-- CART PAGE -->
<section class="cart-page">

    <div class="cart-page-header">

        <div>
            <h1>Shopping Cart</h1>
            <p id="cartItemCount">
                0 items
            </p>
        </div>

        <button
            class="continue-shopping"
            @disabled(!\App\Support\ModuleAccess::allows('customer', 'order')) onclick="continueShopping()">

            ← Continue Shopping

        </button>

    </div>


    <!-- CART CONTENT -->
    <div class="cart-layout">

        <!-- ITEMS -->
        <div class="cart-items-section">

            <div
                class="cart-items"
                id="cartItems">
            </div>

            <div
                class="empty-cart"
                id="emptyCart">

                <div class="empty-cart-icon">
                    🛒
                </div>

                <h2>Your cart is empty</h2>

                <p>
                    Add some products to your cart to continue.
                </p>

                <button
                    @disabled(!\App\Support\ModuleAccess::allows('customer', 'order')) onclick="continueShopping()">

                    Browse Products

                </button>

            </div>

        </div>


        <!-- SUMMARY -->
        <aside class="cart-summary">

            <h2>Order Summary</h2>

            <div class="summary-row">

                <span>Items</span>

                <span id="summaryItems">
                    0
                </span>

            </div>


            <div class="summary-row">

                <span>Subtotal</span>

                <span id="summarySubtotal">
                    ₱0.00
                </span>

            </div>


            <div class="summary-divider"></div>


            <div class="summary-total">

                <span>Total</span>

                <strong id="summaryTotal">
                    ₱0.00
                </strong>

            </div>


            <button
                class="cart-checkout-button"
                id="checkoutButton"
                @disabled(!\App\Support\ModuleAccess::allows('customer', 'checkout')) onclick="proceedToCheckout()">

                Proceed To Checkout →

            </button>

        </aside>

    </div>

</section>
</main>

</div>

@include('customer.partials.backend')
<script>

/* =====================================
   LOAD SAVED CART
===================================== */

let cart = customerCart.items;
let savingCart = false;

/* =====================================
   ELEMENTS
===================================== */

const cartItems =
    document.getElementById("cartItems");

const emptyCart =
    document.getElementById("emptyCart");

const cartItemCount =
    document.getElementById("cartItemCount");

const summaryItems =
    document.getElementById("summaryItems");

const summarySubtotal =
    document.getElementById("summarySubtotal");

const summaryTotal =
    document.getElementById("summaryTotal");

const checkoutButton =
    document.getElementById("checkoutButton");


/* =====================================
   DISPLAY CART
===================================== */

function displayCart() {

    cartItems.innerHTML = "";

    if (cart.length === 0) {

        cartItems.style.display = "none";
        emptyCart.style.display = "flex";

        cartItemCount.textContent =
            "0 items";

        summaryItems.textContent =
            "0";

        summarySubtotal.textContent =
            "₱0.00";

        summaryTotal.textContent =
            "₱0.00";

        checkoutButton.disabled = true;

        return;
    }


    cartItems.style.display = "flex";
    emptyCart.style.display = "none";

    checkoutButton.disabled = {{ \Illuminate\Support\Js::from(!\App\Support\ModuleAccess::allows('customer', 'checkout')) }};


    let total = 0;
    let itemCount = 0;


    cart.forEach((item, index) => {

        const subtotal =
            item.price * item.quantity;

        total += subtotal;

        itemCount += item.quantity;


        const cartItem =
            document.createElement("div");

        cartItem.className =
            "cart-item";


        cartItem.innerHTML = `

            <div class="cart-item-image">

                <div class="cart-triangle"></div>

                <div class="cart-gear">
                    ✹
                </div>

                <div class="cart-square"></div>

            </div>


            <div class="cart-item-info">

                <span class="cart-item-category">
                    ${CustomerWorkspace.escape(item.category)}
                </span>

                <h3>
                    ${CustomerWorkspace.escape(item.name)}
                </h3>

                <p>
                    ₱${item.price.toFixed(2)}
                    / ${CustomerWorkspace.escape(item.unit)}
                </p>

                <span class="cart-stock">
                    ${item.stock} in stock
                </span>

            </div>


            <div class="cart-item-quantity">

                <button
                    onclick="decreaseCartQuantity(${index})">

                    −

                </button>

                <span>
                    ${item.quantity}
                </span>

                <button
                    onclick="increaseCartQuantity(${index})">

                    +

                </button>

            </div>


            <div class="cart-item-subtotal">

                <strong>
                    ₱${subtotal.toFixed(2)}
                </strong>

                <button
                    class="remove-cart-item"
                    onclick="removeFromCart(${index})">

                    Remove

                </button>

            </div>

        `;


        CustomerWorkspace.setProductImage(cartItem.querySelector('.cart-item-image'), item);
        cartItems.appendChild(cartItem);

    });


    cartItemCount.textContent =
        itemCount +
        (itemCount === 1 ? " item" : " items");


    summaryItems.textContent =
        itemCount;


    summarySubtotal.textContent =
        "₱" + total.toFixed(2);


    summaryTotal.textContent =
        "₱" + total.toFixed(2);

}


/* =====================================
   INCREASE QUANTITY
===================================== */

function increaseCartQuantity(index) {
    if (cart[index].quantity < cart[index].stock) saveQuantity(index, cart[index].quantity + 1);
}


/* =====================================
   DECREASE QUANTITY
===================================== */

function decreaseCartQuantity(index) {
    saveQuantity(index, cart[index].quantity - 1);
}


/* =====================================
   REMOVE ITEM
===================================== */

function removeFromCart(index) {
    saveQuantity(index, 0);
}

async function saveQuantity(index, quantity) {
    if (savingCart) return;
    const items = cart.map((item, position) => ({product_id: item.id, quantity: position === index ? quantity : item.quantity}))
        .filter(item => item.quantity > 0);
    savingCart = true;
    try {
        customerCart = await CustomerWorkspace.request("{{ route('customer.cart.update') }}", 'PUT', {
            version: customerCart.version, items
        });
        cart = customerCart.items;
        displayCart();
    } catch (error) {
        alert(error.message);
    } finally {
        savingCart = false;
    }
}


/* =====================================
   CONTINUE SHOPPING
===================================== */

function continueShopping() {

    window.location.href =
        "{{ route('customer.order') }}";

}


/* =====================================
   CHECKOUT
===================================== */

function proceedToCheckout() {
    if (cart.length && !savingCart) window.location.href = "{{ route('customer.checkout') }}";
}


/* =====================================
   INITIAL LOAD
===================================== */

displayCart();

</script>

</body>
</html>

