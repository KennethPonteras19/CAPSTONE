<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/productmanagement.css') }}?v={{ filemtime(public_path('css/productmanagement.css')) }}">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @include('partials.admin-sidebar-assets')
</head>

<body class="admin-workspace">

@php
    $productCategories = ['Tools', 'Electrical', 'Hardware', 'Paint', 'Pipes'];
    $filterCategories = collect($productCategories)->merge($products->pluck('category'))->filter()->unique()->sort();
    $filterBrands = $products->map(fn ($product) => $product->brand ?: 'N/A')->unique()->sort();
@endphp

<!-- ==================================================
     SIDEBAR
================================================== -->
@include('partials.admin-sidebar')



<!-- ==================================================
     MAIN
================================================== -->
<div class="main">



   



    <!-- PAGE TITLE -->
    <h2>
        INVENTORY
    </h2>

    @if(session('success'))
        <p class="notice success" role="status">{{ session('success') }}</p>
    @endif


    <div class="product-toolbar">
        <div class="product-total"><span>Total Products</span><strong id="totalProducts">{{ $products->count() }}</strong></div>

    <!-- ADD PRODUCT -->
    <button type="button"
            class="add-btn"
            onclick="openModal()">

        + Add Product

    </button>
    </div>
     <!-- TOPBAR -->
    <div class="topbar">
        

        <input type="search"
               id="productSearch"
               aria-label="Search products by name, category, or brand"
               aria-controls="productTable"
               placeholder="Search name, category or brand">
<div class="right-section"> 
                <!-- DATE AND TIME -->
                <div id="datetime" class="datetime"></div>


       



            <div class="user">
                Admin
            </div>

        </div>

    </div>

    <div class="product-filters" role="group" aria-label="Filter products">
        <label for="categoryFilter">Category
            <select id="categoryFilter" aria-controls="productTable">
                <option value="">All categories</option>
                @foreach($filterCategories as $filterCategory)
                    <option value="{{ $filterCategory }}">{{ $filterCategory }}</option>
                @endforeach
            </select>
        </label>
        <label for="brandFilter">Brand
            <select id="brandFilter" aria-controls="productTable">
                <option value="">All brands</option>
                @foreach($filterBrands as $filterBrand)
                    <option value="{{ $filterBrand }}">{{ $filterBrand }}</option>
                @endforeach
            </select>
        </label>
        <label for="statusFilter">Stock Status
            <select id="statusFilter" aria-controls="productTable">
                <option value="">All statuses</option>
                <option value="Available">Available</option>
                <option value="Low Stock">Low Stock</option>
                <option value="Out of Stock">Out of Stock</option>
            </select>
        </label>
        <button type="button" id="clearProductFilters">Clear filters</button>
    </div>
    <p id="productResultsCount" class="product-results-count" role="status" aria-live="polite">Showing {{ $products->count() }} of {{ $products->count() }} products</p>


    <!-- ==================================================
         PRODUCT TABLE
    ================================================== -->
    <div class="table-container">

        <table id="productTable">

            <thead>

                <tr>

                    @foreach(['Name', 'Category', 'Brand', 'Stock', 'Price', 'Status'] as $column)
                        <th scope="col" aria-sort="none">
                            <button type="button" class="product-sort" data-column="{{ $loop->index }}"
                                    data-type="{{ in_array($column, ['Stock', 'Price']) ? 'number' : 'text' }}"
                                    title="Sort {{ $column }} ascending">
                                {{ $column }}
                                <span class="sort-arrows" aria-hidden="true"><span class="sort-up">▲</span><span class="sort-down">▼</span></span>
                            </button>
                        </th>
                    @endforeach

                    <th scope="col">
                        Action
                    </th>

                </tr>

            </thead>



            <tbody>

                @foreach($products as $product)

                    <tr data-product-row>

                        <!-- NAME -->
                        <td>
                            @if($product->image_url)
                                <img class="product-thumbnail" src="{{ $product->image_url }}" alt="{{ $product->name }}" loading="lazy">
                            @endif
                            {{ $product->name }}
                        </td>



                        <!-- CATEGORY -->
                        <td>
                            {{ $product->category }}
                        </td>



                        <!-- BRAND ADDED -->
                        <td>
                            {{ $product->brand ?: 'N/A' }}
                        </td>



                        <!-- STOCK -->
                        <td data-sort-value="{{ $product->stock }}">

                            {{ $product->stock }}

                            {{ $product->stock_unit ?? 'item' }}

                        </td>



                        <!-- PRICE -->
                        <td data-sort-value="{{ $product->price }}">

                            ₱{{ number_format($product->price, 2) }}

                        </td>



                        <!-- STATUS -->
                        <td>

                            @if($product->stock == 0)

                                <span class="danger">
                                    Out of Stock
                                </span>

                            @elseif($product->stock <= 5)

                                <span class="warning">
                                    Low Stock
                                </span>

                            @else

                                <span class="success">
                                    Available
                                </span>

                            @endif

                        </td>



                        <!-- ACTION -->
                        <td class="actions">

                            <button
                                type="button"
                                class="edit-btn"
                                data-product="{{ json_encode($product->only(['id', 'name', 'category', 'brand', 'stock', 'stock_unit', 'price', 'image_url'])) }}">

                                Edit

                            </button>



                            <form
                                action="{{ route('products.delete', $product->id) }}"
                                method="POST">

                                @csrf

                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="delete-btn"
                                    onclick="return confirm('Delete this product?')">

                                    Delete

                                </button>

                            </form>

                        </td>

                    </tr>

                @endforeach

                <tr id="noProductResults" @if($products->isNotEmpty()) hidden @endif>
                    <td colspan="7" class="product-empty-state">{{ $products->isEmpty() ? 'No products yet. Add a product to get started.' : 'No products match your search or filters.' }}</td>
                </tr>

            </tbody>

        </table>

    </div>

</div>



<!-- ==================================================
     PRODUCT MODAL
================================================== -->
<div class="modal"
     id="productModal">

    <div class="modal-content">



        <h3 id="modalTitle">
            Add Product
        </h3>



        <form
            id="productForm"
            action="{{ route('products.store') }}"
            method="POST"
            enctype="multipart/form-data">

            @csrf

            @if($errors->any())
                <div class="notice danger" role="alert">
                    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="group product-image-editor">
                <label for="product_image">Product Image</label>
                <img id="productImagePreview" class="product-image-preview" alt="Product image preview" hidden>
                <input type="file" name="image" id="product_image" accept="image/jpeg,image/png,image/webp" data-max-size="{{ \App\Models\Product::MAX_IMAGE_SIZE_KB * 1024 }}" hidden>
                <button type="button" class="image-upload-btn" id="insertProductImage">Insert Image</button>
                <p id="productImageHelp">JPG, PNG or WebP, up to {{ \App\Models\Product::MAX_IMAGE_SIZE_KB / 1024 }} MB. Leave empty to keep the current image.</p>
                <p id="productImageError" class="notice danger" role="alert" hidden></p>
            </div>


            <!-- PRODUCT ID -->
            <input
                type="hidden"
                name="id"
                id="product_id">



            <!-- ==================================================
                 NAME + CATEGORY
            ================================================== -->
            <div class="row">



                <div class="group">

                    <label>
                        Product Name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="name"
                        required>

                </div>



                <div class="group">

                    <label>
                        Category
                    </label>

                    <select
                        name="category"
                        id="category"
                        required>

                        <option value="">
                            Select Category
                        </option>

                        @foreach($productCategories as $productCategory)
                            <option value="{{ $productCategory }}">{{ $productCategory }}</option>
                        @endforeach

                    </select>

                </div>

            </div>



            <!-- ==================================================
                 BRAND
            ================================================== -->
            <div class="row">

                <div class="group">

                    <label>
                        Brand
                    </label>

                    <input
                        type="text"
                        name="brand"
                        id="brand"
                        placeholder="Enter brand">

                </div>

            </div>



            <!-- ==================================================
                 STOCK
            ================================================== -->
            <div class="row">



                <div class="group">

                    <label>
                        Stock Quantity
                    </label>

                    <input
                        type="number"
                        name="stock"
                        id="stock"
                        min="0"
                        step="0.01"
                        placeholder="Example: 10"
                        required>

                </div>



                <div class="group">

                    <label>
                        Stock Unit
                    </label>

                    <select
                        name="stock_unit"
                        id="stock_unit"
                        required>

                        <option value="item">
                            Per Item
                        </option>

                        <option value="piece">
                            Piece
                        </option>

                        <option value="kg">
                            Kilogram (kg)
                        </option>

                        <option value="g">
                            Gram (g)
                        </option>

                        <option value="m3">
                            Cubic Meter (m³)
                        </option>

                        <option value="m">
                            Meter (m)
                        </option>

                        <option value="L">
                            Liter (L)
                        </option>

                        <option value="box">
                            Box
                        </option>

                        <option value="pack">
                            Pack
                        </option>

                    </select>

                </div>

            </div>



            <!-- ==================================================
                 PRICE
            ================================================== -->
            <div class="row">

                <div class="group">

                    <label>
                        Price
                    </label>

                    <input
                        type="number"
                        name="price"
                        id="price"
                        step="0.01"
                        min="0"
                        required>

                </div>

            </div>



            <!-- ==================================================
                 MODAL BUTTONS
            ================================================== -->
            <div class="modal-actions">

                <button
                    type="button"
                    onclick="closeModal()">

                    Cancel

                </button>



                <button
                    type="submit"
                    class="save-btn">

                    Save Product

                </button>

            </div>



        </form>

    </div>

</div>



<!-- ==================================================
     JAVASCRIPT
================================================== -->
<script>

const productTable = document.getElementById('productTable');
const productSortButtons = productTable.querySelectorAll('.product-sort');
const productCollator = new Intl.Collator(undefined, {numeric: true, sensitivity: 'base'});
const productSearch = document.getElementById('productSearch');
const categoryFilter = document.getElementById('categoryFilter');
const brandFilter = document.getElementById('brandFilter');
const statusFilter = document.getElementById('statusFilter');
const noProductResults = document.getElementById('noProductResults');
const productRows = Array.from(productTable.querySelectorAll('[data-product-row]')).map(row => ({
    row,
    search: [0, 1, 2].map(index => row.cells[index].textContent.trim()).join(' ').toLocaleLowerCase(),
    category: row.cells[1].textContent.trim(),
    brand: row.cells[2].textContent.trim(),
    status: row.cells[5].textContent.trim(),
}));

function filterProducts() {
    const terms = productSearch.value.trim().toLocaleLowerCase().split(/\s+/).filter(Boolean);
    let visible = 0;
    productRows.forEach(product => {
        const matches = terms.every(term => product.search.includes(term))
            && (!categoryFilter.value || product.category === categoryFilter.value)
            && (!brandFilter.value || product.brand === brandFilter.value)
            && (!statusFilter.value || product.status === statusFilter.value);
        product.row.hidden = !matches;
        if (matches) visible++;
    });
    noProductResults.hidden = visible > 0;
    document.getElementById('productResultsCount').textContent = `Showing ${visible} of ${productRows.length} products`;
}

productSearch.addEventListener('input', filterProducts);
[categoryFilter, brandFilter, statusFilter].forEach(select => select.addEventListener('change', filterProducts));
document.getElementById('clearProductFilters').addEventListener('click', () => {
    productSearch.value = '';
    [categoryFilter, brandFilter, statusFilter].forEach(select => { select.value = ''; });
    filterProducts();
});
filterProducts();

productSortButtons.forEach(button => {
    button.addEventListener('click', () => {
        const header = button.closest('th');
        const direction = header.getAttribute('aria-sort') === 'ascending' ? 'descending' : 'ascending';
        const column = Number(button.dataset.column);
        const body = productTable.tBodies[0];
        const rows = Array.from(body.querySelectorAll('[data-product-row]'));

        rows.sort((left, right) => {
            const leftCell = left.cells[column];
            const rightCell = right.cells[column];
            const comparison = button.dataset.type === 'number'
                ? Number(leftCell.dataset.sortValue) - Number(rightCell.dataset.sortValue)
                : productCollator.compare(leftCell.textContent.trim(), rightCell.textContent.trim());
            return direction === 'ascending' ? comparison : -comparison;
        });
        rows.forEach(row => body.insertBefore(row, noProductResults));

        productSortButtons.forEach(sortButton => {
            sortButton.closest('th').setAttribute('aria-sort', 'none');
            sortButton.title = `Sort ${sortButton.textContent.trim().split(/\s/)[0]} ascending`;
        });
        header.setAttribute('aria-sort', direction);
        button.title = `Sort ${button.textContent.trim().split(/\s/)[0]} ${direction === 'ascending' ? 'descending' : 'ascending'}`;
    });
});

let previewUrl = null;
const imageInput = document.getElementById('product_image');
const imagePreview = document.getElementById('productImagePreview');
const imageError = document.getElementById('productImageError');

function validateProductImage() {
    const file = imageInput.files[0];
    const maxSize = Number(imageInput.dataset.maxSize);
    const tooLarge = file && file.size > maxSize;
    imageError.hidden = !tooLarge;
    imageError.textContent = tooLarge ? `Choose an image up to ${maxSize / 1024 / 1024} MB.` : '';
    return !tooLarge;
}

document.getElementById('productForm').addEventListener('submit', event => {
    if (!validateProductImage()) event.preventDefault();
});
document.getElementById('productForm').addEventListener('reset', () => {
    imageError.hidden = true;
    imageError.textContent = '';
});

function setProductImagePreview(url) {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = null;
    imagePreview.hidden = !url;
    if (url) imagePreview.src = url;
    else imagePreview.removeAttribute('src');
}

document.getElementById('insertProductImage').addEventListener('click', () => imageInput.click());
imageInput.addEventListener('change', () => {
    if (!validateProductImage()) {
        setProductImagePreview(null);
        return;
    }
    const file = imageInput.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    setProductImagePreview(url);
    previewUrl = url;
});

document.querySelectorAll('.edit-btn[data-product]').forEach(button => {
    button.addEventListener('click', () => editProduct(JSON.parse(button.dataset.product)));
});

function openModal()
{
    const modal =
        document.getElementById("productModal");

    const form =
        document.getElementById("productForm");

    const title =
        document.getElementById("modalTitle");



    form.reset();
    setProductImagePreview(null);

    document.getElementById("product_id").value = "";

    document.getElementById("brand").value = "";

    document.getElementById("stock_unit").value = "item";

    title.innerText = "Add Product";

    modal.style.display = "flex";
}



function closeModal()
{
    document.getElementById("productModal").style.display = "none";
}



function editProduct(product)
{
    const {id = '', name = '', category = '', brand, stock = '', stock_unit: stockUnit, price = '', image_url: imageUrl} = product;
    document.getElementById('productForm').reset();
    setProductImagePreview(imageUrl);
    [['category', category], ['stock_unit', stockUnit ?? 'item']].forEach(([field, value]) => {
        const select = document.getElementById(field);
        if (![...select.options].some(option => option.value === value)) {
            select.add(new Option(value, value));
        }
    });
    const modal =
        document.getElementById("productModal");



    document.getElementById("modalTitle").innerText =
        "Edit Product";



    document.getElementById("product_id").value =
        id;



    document.getElementById("name").value =
        name;



    document.getElementById("category").value =
        category;



    document.getElementById("brand").value =
        brand ?? '';



    document.getElementById("stock").value =
        stock;



    document.getElementById("stock_unit").value =
        stockUnit ?? 'item';



    document.getElementById("price").value =
        price;



    modal.style.display = "flex";
}



window.onclick = function(event)
{
    const modal =
        document.getElementById("productModal");



    if (event.target === modal)
    {
        closeModal();
    }
};



function updateDateTime()
{
    const now = new Date();



    const date =
        now.toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });



    const time =
        now.toLocaleTimeString();



    document.getElementById("datetime").innerHTML =
        date + " | " + time;
}



setInterval(updateDateTime, 1000);

updateDateTime();

@if($errors->any())
const previousProduct = {{ \Illuminate\Support\Js::from(old()) }};
previousProduct.image_url = {{ \Illuminate\Support\Js::from($products->firstWhere('id', old('id'))?->image_url) }};
editProduct(previousProduct);
document.getElementById('modalTitle').innerText = previousProduct.id ? 'Edit Product' : 'Add Product';
@endif
</script>



</body>
</html>
