<!DOCTYPE html>
<html>
<head>
    <title>Staff Transactions | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/sales.css') }}">
    <link rel="stylesheet" href="{{ asset('css/transactionstaff.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<!-- SIDEBAR -->
<div class="sidebar">
    <div class="logo-section">
        <a href="{{ route('dashboardstaff') }}" style="text-decoration:none; color:white;">
            <img src="{{ asset('images/logo.png') }}" class="logo">
            <h2 class="system-name">TMTA POS</h2>
        </a>
    </div>

    <ul>
        <li>
            <a href="{{ route('dashboardstaff') }}"><i class="fa-solid fa-gauge"></i> Dashboard</a>
        </li>

        <li>
            <a href="{{ route('newsales') }}"><i class="fa-solid fa-cash-register"></i> New Sale</a>
        </li>

        <li class="active">
            <a href="{{ route('transactions') }}"><i class="fa-solid fa-receipt"></i> Transactions</a>
        </li>

        <li>
            <a href="{{ route('staff.inventory') }}"><i class="fa-solid fa-box"></i> Inventory</a>
        </li>
    </ul>

    <div class="logout-section">
        <form action="{{ route('logout') }}" method="POST">
            @csrf
            <button class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i> Logout
            </button>
        </form>
    </div>
</div>

<!-- MAIN -->
<div class="main">

    <!-- TOPBAR -->
    <div class="topbar">
        <input type="text" placeholder="Search Receipt / Product">

        <div class="right-section">
            <div id="datetime" class="datetime"></div>
            <div class="user">{{ Auth::guard('staff')->user()->name ?? 'Staff' }}</div>
        </div>
    </div>

    <h2>Transactions</h2>

    <!-- TABLE -->
    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Receipt</th>
                    <th>Date/Time</th>
                    <th>Items</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>

            @forelse($transactions as $transaction)

            @php
                $items = \App\Models\Sale::where('receipt_no', $transaction->receipt_no)->get();

                $total = $items->sum('total_amount');

                $date = $items->first()->created_at;

                $itemList = $items->map(function($item){
                    return $item->item_name . ' x' . $item->qty;
                })->implode(', ');

                $status = $items->first()->payment_status;
            @endphp

            <tr>
                <td>#{{ $transaction->receipt_no }}</td>

                <td>
                    {{ \Carbon\Carbon::parse($date)->format('M d, Y | h:i A') }}
                </td>

                <td>{{ $itemList }}</td>

                <td>₱{{ number_format($total, 2) }}</td>

                <td class="{{ $status == 'Paid' ? 'status-ok' : 'status-pending' }}">
                    {{ $status }}
                </td>

                <td>
                    <button onclick="printReceipt(
                        '#{{ $transaction->receipt_no }}',
                        '{{ \Carbon\Carbon::parse($date)->format('M d, Y | h:i A') }}',
                        '{{ $itemList }}',
                        {{ $total }}
                    )">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                </td>
            </tr>

            @empty
            <tr>
                <td colspan="6">No transactions found</td>
            </tr>
            @endforelse

            </tbody>
        </table>
    </div>

</div>

<!-- RECEIPT TEMPLATE -->
<div id="receipt" style="display:none;">
    <div class="receipt-container">
        <h2>TMTA POS</h2>
        <p id="r-date"></p>
        <hr>

        <p><strong>Receipt:</strong> <span id="r-id"></span></p>
        <p><strong>Items:</strong> <span id="r-items"></span></p>

        <hr>
        <h3>Total: ₱<span id="r-total"></span></h3>

        <p>Thank you!</p>
    </div>
</div>

<!-- SCRIPT -->
<script>
function printReceipt(id, date, items, total) {

    document.getElementById('r-id').innerText = id;
    document.getElementById('r-date').innerText = date;
    document.getElementById('r-items').innerText = items;
    document.getElementById('r-total').innerText = total;

    let printContent = document.getElementById('receipt').innerHTML;
    let originalContent = document.body.innerHTML;

    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;

    location.reload();
}

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
