<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order - TMTA Construction Supply</title>
    @include('customer.partials.assets')
</head>

<body>

<div class="app">

    <!-- SIDEBAR -->
    @include('customer.partials.sidebar')

    <!-- MAIN CONTENT -->
    <main class="main-content order-content">

        <header class="customer-page-heading">
            <h1>Order Products</h1>
            <p>Find your supplies, choose a quantity, and add them to your cart.</p>
        </header>

        <!-- SEARCH -->
        <div class="order-top">

            <div class="order-search">

                <input
                    type="text"
                    id="searchInput"
                    aria-label="Search products"
                    placeholder="Search materials"
                >

                <span class="order-search-icon">
                    ⌕
                </span>

            </div>

        </div>

        <!-- CATEGORIES -->
        <div class="order-categories">

            <button
                class="order-category"
                data-category="All">
                All
            </button>

            <button
                class="order-category"
                data-category="Electrical">
                Electrical
            </button>

            <button
                class="order-category"
                data-category="Tools">
                Tools
            </button>

            <button
                class="order-category"
                data-category="Hardware">
                Hardware
            </button>

            <button
                class="order-category"
                data-category="Others">
                Others
            </button>

        </div>

        <!-- PRODUCT COUNT -->
        <div class="product-heading">

            <h2>Available Products</h2>

            <span id="productCount"></span>

        </div>

        <!-- PRODUCTS -->
        <section
            class="order-products"
            id="productContainer">
        </section>

        <!-- NO PRODUCTS -->
        <div
            class="no-products"
            id="noProducts">

            No products found.

        </div>

    </main>

    <!-- =====================================
         ORDER MODAL
    ====================================== -->

    <div
        class="order-modal-overlay"
        id="orderModal">

        <div class="order-modal" role="dialog" aria-modal="true" aria-labelledby="modalProductName" tabindex="-1">

            <!-- CLOSE -->
            <button
                class="modal-close"
                type="button"
                aria-label="Close product details"
                onclick="closeOrderModal()">

                ×

            </button>

            <!-- PRODUCT INFORMATION -->
            <div class="modal-product">

                <div class="modal-product-image">

                    <div class="product-triangle"></div>

                    <div class="product-gear">
                        ✹
                    </div>

                    <div class="product-square"></div>

                </div>

                <div class="modal-product-info">

                    <h2 id="modalProductName">
                        Product 1
                    </h2>

                    <p id="modalProductPrice">
                        ₱0.00
                    </p>

                </div>

            </div>

            <!-- QUANTITY -->
            <div class="quantity-section">

                <label for="quantity">
                    Quantity
                </label>

                <div class="quantity-controls">

                    <button
                        class="quantity-button"
                        type="button"
                        aria-label="Decrease quantity"
                        onclick="decreaseQuantity()">

                        −

                    </button>

                    <input id="quantity" type="number" inputmode="numeric"
                           min="1" step="1" value="1" required
                           aria-describedby="quantityHint quantityError">

                    <button
                        class="quantity-button"
                        type="button"
                        aria-label="Increase quantity"
                        onclick="increaseQuantity()">

                        +

                    </button>

                </div>

                <p id="quantityHint" class="quantity-hint"></p>
                <p id="quantityError" class="quantity-error" role="status"></p>

            </div>

            <!-- SUBTOTAL -->
            <div class="subtotal-section">

                <span>
                    Subtotal
                </span>

                <strong id="modalSubtotal">
                    ₱0.00
                </strong>

            </div>

            <!-- ACTIONS -->
            <p id="cartError" class="quantity-error" role="alert"></p>
            <div class="modal-actions">

                <button
                    class="cancel-button"
                    onclick="closeOrderModal()">

                    Cancel

                </button>

                <button
                    class="cart-button"
                    id="addToCartButton"
                    type="button"
                    onclick="addSelectedToCart()">

                    Add to Cart

                </button>

            </div>

        </div>

    </div>

    <!-- =====================================
         CART BAR
    ====================================== -->

    <div
        class="cart-bar"
        id="cartBar">

        <div class="cart-total">

            <span>
                Total
            </span>

            <strong id="cartTotal">
                ₱0.00
            </strong>

        </div>

        <div class="cart-actions">

            <button
                class="view-cart-button"
                @disabled(!\App\Support\ModuleAccess::allows('customer', 'cart')) onclick="viewCart()">

                View Cart

            </button>

            <button
                class="checkout-button"
                @disabled(!\App\Support\ModuleAccess::allows('customer', 'checkout')) onclick="proceedToCheckout()">

                Proceed To Checkout →

            </button>

        </div>

    </div>

</div>

@include('customer.partials.backend')
<script>

/* =====================================
   DATABASE PRODUCTS
===================================== */

const products = {{ \Illuminate\Support\Js::from($products) }};


/* =====================================
   ELEMENTS
===================================== */

const productContainer =
    document.getElementById("productContainer");

const productCount =
    document.getElementById("productCount");

const noProducts =
    document.getElementById("noProducts");

const searchInput =
    document.getElementById("searchInput");

const categoryButtons =
    document.querySelectorAll(".order-category");


/* =====================================
   GET CATEGORY FROM HOME PAGE
===================================== */

const urlParams =
    new URLSearchParams(window.location.search);

let currentCategory =
    urlParams.get("category") || "All";


const validCategories = [
    "All",
    "Electrical",
    "Tools",
    "Hardware",
    "Others"
];


if (!validCategories.includes(currentCategory)) {
    currentCategory = "All";
}


/* =====================================
   SET ACTIVE CATEGORY
===================================== */

function updateCategoryButtons() {

    categoryButtons.forEach(button => {

        if (
            button.dataset.category ===
            currentCategory
        ) {

            button.classList.add("selected");

        } else {

            button.classList.remove("selected");

        }

    });

}


/* =====================================
   DISPLAY PRODUCTS
===================================== */

function displayProducts() {

    const searchText =
        searchInput.value
            .toLowerCase()
            .trim();


    const filteredProducts =
        products.filter(product => {

            const categoryMatch =
                currentCategory === "All" ||
                (currentCategory === "Others" ? !["Electrical", "Tools", "Hardware"].includes(product.category) : product.category === currentCategory);


            const searchMatch =
                product.name
                    .toLowerCase()
                    .includes(searchText);


            return categoryMatch && searchMatch;

        });


    productContainer.innerHTML = "";


    /* PRODUCT COUNT */

    productCount.textContent =
        filteredProducts.length +
        (
            filteredProducts.length === 1
                ? " product"
                : " products"
        );


    /* NO PRODUCTS */

    if (filteredProducts.length === 0) {

        noProducts.style.display = "block";

        return;

    }


    noProducts.style.display = "none";


    /* CREATE PRODUCT CARDS */

    filteredProducts.forEach(product => {

        const card =
            document.createElement("div");

        card.className =
            "product-card";


        card.innerHTML = `

            <div class="product-image">

                <div class="product-triangle"></div>

                <div class="product-gear">
                    ✹
                </div>

                <div class="product-square"></div>

            </div>


            <div class="product-info">

                <span class="product-category">
                    ${CustomerWorkspace.escape(product.category)}
                </span>

                <h3>
                    ${CustomerWorkspace.escape(product.name)}
                </h3>

                <p class="product-price">
                    ₱${product.price.toFixed(2)}
                    / ${CustomerWorkspace.escape(product.unit)}
                </p>

                <p class="product-stock">
                    ${product.stock} in stock
                </p>

                <button
                    class="add-product"
                    onclick="addToOrder(${product.id})" ${product.stock <= 0 ? "disabled" : ""}>

                    Add to Order

                </button>

            </div>

        `;


        CustomerWorkspace.setProductImage(card.querySelector('.product-image'), product);
        productContainer.appendChild(card);

    });

}


/* =====================================
   CATEGORY BUTTONS
===================================== */

categoryButtons.forEach(button => {

    button.addEventListener("click", () => {

        currentCategory =
            button.dataset.category;


        const newUrl =
            currentCategory === "All"
                ? "{{ route('customer.order') }}"
                : "{{ route('customer.order') }}?category=" +
                  encodeURIComponent(
                      currentCategory
                  );


        window.history.pushState(
            {},
            "",
            newUrl
        );


        updateCategoryButtons();

        displayProducts();

    });

});


/* =====================================
   SEARCH
===================================== */

searchInput.addEventListener(
    "input",
    displayProducts
);


/* =====================================
   ORDER MODAL
===================================== */

const orderModal =
    document.getElementById("orderModal");

const modalProductName =
    document.getElementById("modalProductName");

const modalProductPrice =
    document.getElementById("modalProductPrice");

const modalSubtotal =
    document.getElementById("modalSubtotal");

const quantityDisplay =
    document.getElementById("quantity");


let selectedProduct = null;

let selectedQuantity = 1;
let modalTrigger = null;
const addToCartButton = document.getElementById('addToCartButton');
const quantityError = document.getElementById('quantityError');

function availableQuantity() {
    if (!selectedProduct) return 0;
    const inCart = cart.find(item => item.id === selectedProduct.id)?.quantity || 0;
    return Math.max(0, selectedProduct.stock - inCart);
}

function validateQuantity(report = false) {
    const quantity = Number(quantityDisplay.value);
    const maximum = availableQuantity();
    const valid = quantityDisplay.value.trim() !== '' && Number.isInteger(quantity) && quantity >= 1 && quantity <= maximum;
    selectedQuantity = valid ? quantity : 0;
    const message = valid ? '' : `Enter a whole number between 1 and ${maximum}.`;
    quantityDisplay.setCustomValidity(message);
    quantityDisplay.setAttribute('aria-invalid', String(!valid));
    quantityError.textContent = message;
    addToCartButton.disabled = savingCart || !valid;
    updateSubtotal();
    if (report && !valid) quantityDisplay.reportValidity();
    return valid;
}

quantityDisplay.addEventListener('input', () => validateQuantity());
quantityDisplay.addEventListener('change', () => validateQuantity());


/* =====================================
   OPEN ORDER MODAL
===================================== */

function addToOrder(productId) {

    selectedProduct =
        products.find(
            product =>
                product.id === productId
        );


    if (!selectedProduct || selectedProduct.stock <= 0) {
        return;
    }

    const maximum = availableQuantity();
    if (maximum < 1) {
        alert('All available stock for this product is already in your cart.');
        return;
    }
    modalTrigger = document.activeElement;
    document.getElementById('cartError').textContent = '';
    quantityDisplay.max = maximum;
    document.getElementById('quantityHint').textContent = `${maximum} available to add. Type a quantity or use the − and + buttons.`;

    selectedQuantity = 1;


    modalProductName.textContent =
        selectedProduct.name;
    CustomerWorkspace.setProductImage(document.querySelector('.modal-product-image'), selectedProduct);


    modalProductPrice.textContent =
        "₱" +
        selectedProduct.price.toFixed(2);


    quantityDisplay.value =
        selectedQuantity;

    validateQuantity();


    orderModal.classList.add("show");
    document.body.classList.add('customer-modal-open');
    orderModal.querySelector('[role="dialog"]').focus();

}


/* =====================================
   UPDATE SUBTOTAL
===================================== */

function updateSubtotal() {

    if (!selectedProduct || selectedProduct.stock <= 0) {
        return;
    }


    const subtotal =
        selectedProduct.price *
        selectedQuantity;


    modalSubtotal.textContent =
        selectedQuantity > 0 ? "₱" + subtotal.toFixed(2) : '—';

}


/* =====================================
   INCREASE QUANTITY
===================================== */

function increaseQuantity() {
    if (!selectedProduct || savingCart) return;
    const current = Number(quantityDisplay.value);
    quantityDisplay.value = Math.min(availableQuantity(), Number.isInteger(current) && current >= 1 ? current + 1 : 1);
    validateQuantity();
}


/* =====================================
   DECREASE QUANTITY
===================================== */

function decreaseQuantity() {
    if (!selectedProduct || savingCart) return;
    const current = Number(quantityDisplay.value);
    quantityDisplay.value = Math.max(1, Math.min(availableQuantity(), Number.isInteger(current) ? current - 1 : 1));
    validateQuantity();
}


/* =====================================
   CLOSE MODAL
===================================== */

function closeOrderModal() {

    orderModal.classList.remove("show");
    document.body.classList.remove('customer-modal-open');
    modalTrigger?.focus();

}

orderModal.addEventListener('click', event => {
    if (event.target === orderModal) closeOrderModal();
});

document.addEventListener('keydown', event => {
    if (!orderModal.classList.contains('show')) return;
    if (event.key === 'Escape') closeOrderModal();
    if (event.key !== 'Tab') return;
    const controls = [...orderModal.querySelectorAll('button:not(:disabled), input:not(:disabled)')];
    const first = controls[0];
    const last = controls[controls.length - 1];
    if (event.shiftKey && (document.activeElement === first || document.activeElement.getAttribute('role') === 'dialog')) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
});

/* =====================================
   CART
===================================== */

let cart = customerCart.items;
let savingCart = false;
const cartBar = document.getElementById('cartBar');
const cartTotal = document.getElementById('cartTotal');


/* =====================================
   ADD SELECTED PRODUCT TO CART
===================================== */

async function addSelectedToCart() {
    if (!selectedProduct || savingCart) return;
    if (!validateQuantity(true)) return;
    document.getElementById('cartError').textContent = '';
    const items = cart.map(item => ({product_id: item.id, quantity: item.quantity}));
    const existing = items.find(item => item.product_id === selectedProduct.id);
    if (existing) existing.quantity += selectedQuantity;
    else items.push({product_id: selectedProduct.id, quantity: selectedQuantity});
    savingCart = true;
    addToCartButton.disabled = true;
    addToCartButton.textContent = 'Adding…';
    try {
        customerCart = await CustomerWorkspace.request("{{ route('customer.order.cart.update') }}", 'PUT', {
            version: customerCart.version, items
        });
        cart = customerCart.items;
        updateCart();
        closeOrderModal();
    } catch (error) {
        document.getElementById('cartError').textContent = error.message;
        quantityDisplay.focus();
    } finally {
        savingCart = false;
        addToCartButton.textContent = 'Add to Cart';
        if (orderModal.classList.contains('show')) validateQuantity();
    }
}


/* =====================================
   UPDATE CART TOTAL
===================================== */

function updateCart() {

    let total = 0;


    cart.forEach(item => {

        total +=
            item.price *
            item.quantity;

    });


    cartTotal.textContent =
        "₱" +
        total.toFixed(2);


    if (cart.length > 0) {

        cartBar.classList.add("show");

    } else {

        cartBar.classList.remove("show");

    }

}


/* =====================================
   VIEW CART
===================================== */

function viewCart() {
    if (cart.length && !savingCart) window.location.href = "{{ route('customer.cart') }}";
}


/* =====================================
   PROCEED TO CHECKOUT
===================================== */

function proceedToCheckout() {
    if (cart.length && !savingCart) window.location.href = "{{ route('customer.checkout') }}";
}


/* =====================================
   HANDLE BROWSER BACK/FORWARD CACHE
===================================== */

window.addEventListener(
    "pageshow",
    function(event) {

        if (event.persisted) {

            window.location.reload();

        }

    }
);


/* =====================================
   INITIAL LOAD
===================================== */

updateCategoryButtons();

displayProducts();
updateCart();
</script>

</body>
</html>
