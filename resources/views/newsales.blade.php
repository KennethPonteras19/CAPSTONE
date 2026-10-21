<!DOCTYPE html>
<html>
<head>
    <title>New Sales</title>
    <link rel="stylesheet" href="{{ asset('css/newsales.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- TOPBAR -->
<div class="topbar">
    <h2>TMTA POS - Cashier</h2>

    <div class="right-section">
        <div id="datetime" class="datetime"></div>
        <div class="user">
            {{ Auth::guard('staff')->user()->name ?? 'Staff' }}
        </div>
    </div>
</div>

<div class="container">

    <!-- LEFT: PRODUCTS -->
    <div class="products">

        <div class="search-bar">
            <input type="text" placeholder="Search product...">
        </div>

        <div class="product-grid">

    @foreach($products as $product)
        <div class="product"
             onclick="addToCart('{{ $product->name }}', {{ $product->price }})">

            <p>{{ $product->name }}</p>
            <span>₱{{ number_format($product->price, 2) }}</span>
        </div>
    @endforeach

</div>
    </div>

    <!-- RIGHT: CART -->
    <div class="cart">

        <h3>Cart</h3>

        <div id="cart-items"></div>

        <div class="total">
            <h2>Total: ₱<span id="total">0</span></h2>
        </div>

        <!-- ✅ BACKEND FORM -->
        <form id="checkout-form" method="POST" action="{{ route('pos.checkout') }}">
            @csrf
            <input type="hidden" name="cart" id="cart-data">

            <button type="button" class="pay-btn" onclick="checkout()">Checkout</button>
        </form>

    </div>

</div>

<!-- SUCCESS MESSAGE -->
@if(session('success'))
<script>
    alert("{{ session('success') }}");
</script>
@endif

<!-- SCRIPT -->
<script>
let cart = [];
let total = 0;

function addToCart(name, price) {
    cart.push({ name, price });
    total += price;
    renderCart();
}

function renderCart() {
    let container = document.getElementById('cart-items');
    container.innerHTML = '';

    cart.forEach(item => {
        container.innerHTML += `
            <div class="cart-item">
                <span>${item.name}</span>
                <span>₱${item.price}</span>
            </div>
        `;
    });

    document.getElementById('total').innerText = total;
}

function checkout() {
    if (cart.length === 0) {
        alert("Cart is empty!");
        return;
    }

    // ✅ send cart to backend
    document.getElementById('cart-data').value = JSON.stringify(cart);

    // submit form
    document.getElementById('checkout-form').submit();

    // reset frontend
    cart = [];
    total = 0;
    renderCart();
}

/* REALTIME CLOCK */
function updateDateTime() {
    const now = new Date();
    document.getElementById('datetime').innerHTML =
        now.toLocaleDateString() + " | " + now.toLocaleTimeString();
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>

</body>
</html>