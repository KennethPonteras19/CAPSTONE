<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | TMTA POS</title>
    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/inventory.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    @include('partials.admin-sidebar-assets')
</head>
<body class="admin-workspace">

@include('partials.admin-sidebar')
<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <form method="GET">
    <input type="text"
           name="search"
           value="{{ request('search') }}"
           placeholder="Search Product">
</form>

        <div class="right-section">
            <div id="datetime" class="datetime"></div>
            <div class="user">Admin</div>
        </div>
    </div>

    <h2>Inventory</h2>

    <!-- TABLE -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                </tr>
            </thead>

            <tbody>

@forelse($products as $product)

@php
    if ($product->stock == 0) {
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
    <td>{{ $product->stock }}</td>

    <td class="{{ $class }}">
        {{ $status }}
    </td>

    <td>{{ $product->updated_at->format('M d, Y') }}</td>
</tr>

@empty

<tr>
    <td colspan="5">No products found.</td>
</tr>

@endforelse

</tbody>
        </table>
    </div>

</div>

<!-- SCRIPT -->
<script>
// REALTIME CLOCK
function updateDateTime() {
    const now = new Date();
    const date = now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
    const time = now.toLocaleTimeString();

    document.getElementById('datetime').innerHTML = date + " | " + time;
}
setInterval(updateDateTime, 1000);
updateDateTime();
</script>

</body>
</html>
