<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Dashboard | TMTA POS</title>

    <link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <!-- CHART JS -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            margin-bottom: 25px;
        }
        .topbar .left {
            text-align: left;
            margin-right: auto;
        }
        .topbar .left h2 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
        }
        .topbar .left .subtitle {
            margin: 2px 0 0 0;
            color: #6c757d;
            font-size: 14px;
        }
        .topbar .right-section {
            display: flex;
            align-items: center;
            gap: 15px;
        }
    </style>
    @include('partials.admin-sidebar-assets')
</head>

<body class="admin-workspace">

    <!-- SIDEBAR -->
    @include('partials.admin-sidebar')

    <!-- MAIN -->
    <div class="main">

        <!-- TOPBAR -->
        <div class="topbar"> 
            <div class="left"> 
                <h2>Dashboard</h2> 
                <p class="subtitle">Overview</p> 
            </div> 

            <div class="right-section"> 
                <!-- DATE AND TIME -->
                <div id="datetime" class="datetime"></div>

                <!-- NOTIFICATION WRAPPER -->
                <div class="notification-wrapper">
                    <button class="notification-btn" id="notificationBtn">
                        <i class="fa-solid fa-bell"></i>
                        @if($notificationCount > 0)
                            <span class="notification-badge" id="notificationBadge">
                                {{ $notificationCount > 99 ? '99+' : $notificationCount }}
                            </span>
                        @endif
                    </button>

                    <!-- NOTIFICATION DROPDOWN -->
                    <div class="notification-dropdown" id="notificationDropdown">
                        <div class="notification-header">
                            <div>
                                <h3>Notifications</h3>
                                <span id="notificationSubtitle">
                                    {{ $notificationCount }} alert{{ $notificationCount != 1 ? 's' : '' }}
                                </span>
                            </div>
                            <button id="markAllBtn" onclick="markNotificationsRead()">Mark all as read</button>
                        </div>

                        <div class="notification-list" id="notificationList">
                            <!-- OUT OF STOCK -->
                            @foreach($outOfStockProducts as $product)
                                <div class="notification-item danger">
                                    <div class="notification-icon">
                                        <i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i>
                                    </div>
                                    <div class="notification-content">
                                        <strong>Out of Stock</strong>
                                        <p>{{ $product->name }} is out of stock.</p>
                                        <small><i class="fa-regular fa-clock"></i> Inventory Alert</small>
                                    </div>
                                </div>
                            @endforeach

                            <!-- LOW STOCK -->
                            @foreach($lowStockProducts as $product)
                                <div class="notification-item warning">
                                    <div class="notification-icon">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                    </div>
                                    <div class="notification-content">
                                        <strong>Low Stock</strong>
                                        <p>{{ $product->name }} only has <b>{{ $product->stock }}</b> item(s) left.</p>
                                        <small><i class="fa-regular fa-clock"></i> Inventory Alert</small>
                                    </div>
                                </div>
                            @endforeach

                            <!-- RECENT SALES -->
                            @foreach($recentSales->take(3) as $sale)
                                <div class="notification-item success">
                                    <div class="notification-icon">
                                        <i class="fa-solid fa-receipt"></i>
                                    </div>
                                    <div class="notification-content">
                                        <strong>New Transaction</strong>
                                        <p>Transaction #{{ $sale->id }} worth ₱{{ number_format($sale->total_amount, 2) }}</p>
                                        <small><i class="fa-regular fa-clock"></i> {{ $sale->created_at->diffForHumans() }}</small>
                                    </div>
                                </div>
                            @endforeach

                            @if($notificationCount == 0 && $recentSales->isEmpty())
                                <div class="no-notifications">
                                    <i class="fa-regular fa-bell-slash"></i>
                                    <h4>No notifications</h4>
                                    <p>You're all caught up!</p>
                                </div>
                            @endif
                        </div>

                        <div class="notification-footer">
                            <a href="{{ route('inventory.management') }}">
                                View Inventory <i class="fa-solid fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- ADMIN -->
                <div class="user">
                    <i class="fa-solid fa-user"></i>
                    Admin
                </div>
            </div> 
        </div>

        <!-- CARDS -->
        <div class="cards">
            <div class="card blue">
                <div class="card-top">
                    <span>Today Sales</span>
                    <i class="fa-solid fa-peso-sign"></i>
                </div>
                <h2 class="count" data-target="{{ $todaySales }}">0</h2>
            </div>

            <div class="card green">
                <div class="card-top">
                    <span>Transactions</span>
                    <i class="fa-solid fa-receipt"></i>
                </div>
                <h2 class="count" data-target="{{ $transactions }}">0</h2>
            </div>

            <div class="card orange">
                <div class="card-top">
                    <span>Products</span>
                    <i class="fa-solid fa-box"></i>
                </div>
                <h2 class="count" data-target="{{ $products }}">0</h2>
            </div>

            <div class="card red">
                <div class="card-top">
                    <span>Low Stock</span>
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h2 class="count" data-target="{{ $lowStockCount }}">0</h2>
            </div>
        </div>

        <!-- GRID -->
        <div class="dashboard-grid">
            <!-- CHART -->
            <section class="card large forecasting" id="forecasting" aria-labelledby="forecasting-title">
                <div class="card-header forecasting-header">
                    <div>
                        <h3 id="forecasting-title">Sales Forecasting</h3>
                        <p class="forecast-note">Review past sales and estimate future sales in Philippine pesos.</p>
                    </div>
                    <span class="forecast-badge">Sales outlook</span>
                </div>
                <form method="GET" action="{{ route('dashboard') }}#forecasting" class="forecast-filters">
                    <div>
                        <label for="history-period">Sales history</label>
                        <select id="history-period" name="history">
                            @foreach($historyPeriods as $value => $label)
                                <option value="{{ $value }}" @selected($forecasting['history']['period'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="forecast-period">Predict future sales</label>
                        <select id="forecast-period" name="forecast">
                            @foreach($forecastPeriods as $value => $label)
                                <option value="{{ $value }}" @selected($forecasting['forecast']['period'] === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit">Update view</button>
                </form>
                @if($errors->has('history') || $errors->has('forecast'))
                    <p role="alert" class="forecast-empty">Choose one of the available history and forecast periods.</p>
                @endif
                <div class="forecast-panels">
                    <div class="forecast-panel">
                        <h4>Actual sales · {{ $historyPeriods[$forecasting['history']['period']] }}</h4>
                        <p class="forecast-note">{{ $forecasting['history']['range'] }}</p>
                        <div class="forecast-metrics">
                            <div><span>Total sales</span><strong>₱{{ number_format($forecasting['history']['total'], 2) }}</strong></div>
                            <div><span>Daily average</span><strong>₱{{ number_format($forecasting['history']['daily_average'], 2) }}</strong></div>
                        </div>
                        @unless($forecasting['history']['has_sales'])
                            <p class="forecast-empty">No sales recorded in this period.</p>
                        @endunless
                        <div class="forecast-chart"><canvas id="salesChart" role="img" aria-label="Historical sales chart for {{ $forecasting['history']['range'] }}"></canvas></div>
                        <p class="forecast-note">{{ $forecasting['history']['grouping'] }}. Historical periods end yesterday. Select Today for sales recorded so far. Month, quarter, and year look back 1, 3, and 12 months.</p>
                    </div>
                    <div class="forecast-panel forecast-panel-future">
                        <h4>Predicted sales · {{ $forecastPeriods[$forecasting['forecast']['period']] }}</h4>
                        <p class="forecast-note">{{ $forecasting['forecast']['range'] }}</p>
                        <div class="forecast-metrics">
                            <div><span>Estimated total</span><strong>{{ $forecasting['forecast']['available'] ? '₱'.number_format($forecasting['forecast']['total'], 2) : '—' }}</strong></div>
                            <div><span>Estimated daily average</span><strong>{{ $forecasting['forecast']['available'] ? '₱'.number_format($forecasting['forecast']['daily_average'], 2) : '—' }}</strong></div>
                        </div>
                        @if($forecasting['forecast']['available'])
                            <div class="forecast-chart"><canvas id="forecastChart" role="img" aria-label="Estimated daily sales chart for {{ $forecasting['forecast']['range'] }}"></canvas></div>
                        @else
                            <div class="forecast-unavailable"><i class="fa-solid fa-chart-line" aria-hidden="true"></i><h4>Not enough sales history yet</h4><p>Predictions need at least 14 completed days since the first recorded sale. Currently available: {{ $forecasting['forecast']['training_days'] }} days.</p></div>
                        @endif
                        <p class="forecast-note">Future periods start tomorrow. Select Today for a full-day estimate, including sales already made.</p>
                    </div>
                </div>
                <p class="forecast-method">Estimates use the average sales for each weekday across up to 8 recent weeks, including days with no sales. Today's partial sales appear only in the Today view and are excluded from the training average. Cancelled orders, held orders, and quotations are excluded; unpaid booked sales are included.
                    @if($forecasting['forecast']['training_range'])
                        Based on {{ $forecasting['forecast']['training_days'] }} days: {{ $forecasting['forecast']['training_range'] }}.
                    @endif
                    Estimates are not guaranteed and do not account for future promotions or holidays.
                </p>
                <p id="chart-unavailable" class="forecast-empty" hidden>Charts could not load. Sales totals and estimates are shown above.</p>
                <noscript><p class="forecast-note">Enable JavaScript to view the charts. Use Update view to change periods.</p></noscript>
            </section>

    <!-- STOCK ALERTS -->
    <div class="card large">
        <h3>Stock Alerts</h3>

            <ul class="alerts">
                @forelse($stockAlertItems as $item)
                    <li>
                        <span class="stock-alert-icon" aria-hidden="true">
                            @if($item->stock <= 0)
                                <i class="fa-solid fa-circle-exclamation"></i>
                            @elseif($item->stock <= 5)
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            @else
                                <i class="fa-solid fa-circle-check"></i>
                            @endif
                        </span>

                        <span class="stock-alert-name">
                            {{ $item->name }}
                        </span>

                        @if($item->stock <= 0)
                            <b class="stock-status out-of-stock">Out of Stock</b>
                        @elseif($item->stock <= 5)
                            <b class="stock-status low-stock">Low Stock</b>
                        @else
                            <b class="stock-status in-stock">In Stock</b>
                        @endif
                    </li>
                @empty
                    <li>
                        <span class="stock-alert-icon" aria-hidden="true">
                            <i class="fa-solid fa-circle-check"></i>
                        </span>
                        <span>All products have sufficient stock.</span>
                        <b class="stock-status in-stock">All Good</b>
                    </li>
                @endforelse
            </ul>

    </div>

    <!-- RECENT ORDERS -->
    <div class="card large">
        <h3>Recent Orders</h3>

        <div class="table-responsive" role="region" aria-label="Recent orders" tabindex="0">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Date</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($recentSales as $sale)
                    <tr>
                        <td>#{{ $sale->id }}</td>
                        <td>{{ $sale->created_at->format('M d') }}</td>
                        <td>₱{{ number_format($sale->total_amount, 2) }}</td>
                        <td class="status-ok">
                            {{ $sale->status ?? 'Completed' }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" style="text-align:center;">
                            No recent orders found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
        </div>
    </div>

</div>
</div>

    <script>
        // DIGITAL CLOCK
        function updateDateTime() {
            const datetimeEl = document.getElementById('datetime');
            if (datetimeEl) {
                const now = new Date();
                datetimeEl.innerHTML = now.toLocaleDateString() + " | " + now.toLocaleTimeString();
            }
        }
        setInterval(updateDateTime, 1000);
        updateDateTime();

        // ANIMATED COUNTERS
        document.querySelectorAll('.count').forEach(counter => {
            let update = () => {
                let target = +counter.getAttribute('data-target');
                let current = +counter.innerText;
                let inc = target / 100;

                if (current < target) {
                    counter.innerText = Math.ceil(current + inc);
                    setTimeout(update, 20);
                } else {
                    counter.innerText = target;
                }
            };
            update();
        });

        // CHART INTEGRATION
        document.addEventListener("DOMContentLoaded", function () {
            const forecasting = {{ Illuminate\Support\Js::from($forecasting) }};
            document.querySelectorAll('.forecast-filters select').forEach(select => {
                select.addEventListener('change', () => select.form.requestSubmit());
            });
            const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
            function salesChart(id, series, predicted) {
                const canvas = document.getElementById(id);
                if (!canvas) return;
                new Chart(canvas, {
                    type: 'line',
                    data: {
                        labels: series.labels,
                        datasets: [{
                            label: predicted ? 'Estimated sales (₱)' : 'Actual sales (₱)',
                            data: series.values,
                            borderColor: predicted ? '#7c3aed' : '#2185d0',
                            backgroundColor: predicted ? 'rgba(124, 58, 237, 0.08)' : 'rgba(33, 133, 208, 0.08)',
                            borderDash: predicted ? [6, 4] : [],
                            pointRadius: series.labels.length > 14 ? 2 : 3,
                            fill: true,
                            tension: 0.15
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: true },
                            tooltip: { callbacks: { label: context => context.dataset.label + ': ' + peso.format(context.parsed.y) } }
                        },
                        scales: {
                            x: { ticks: { maxTicksLimit: 6, maxRotation: 0 }, grid: { display: false } },
                            y: {
                                beginAtZero: true,
                                ticks: { callback: value => peso.format(value) }
                            }
                        }
                    }
                });
            }
            if (typeof Chart !== 'undefined') {
                salesChart('salesChart', forecasting.history, false);
                salesChart('forecastChart', forecasting.forecast, true);
            } else {
                document.getElementById('chart-unavailable').hidden = false;
            }

            // DYNAMIC DEDUPLICATION AND SMART LOCALSTORAGE CHECK
            // Generates a signature based on current notification state
            const currentNotifState = "{{ $notificationCount }}_" + "{{ $outOfStockProducts->pluck('id')->join('-') }}_" + "{{ $lowStockProducts->pluck('id')->join('-') }}_" + "{{ $recentSales->first()?->id ?? 0 }}";
            const lastClearedState = localStorage.getItem('notifications_cleared_state');

            if (lastClearedState === currentNotifState) {
                renderNotificationsCleared();
            }
        });

        // NOTIFICATION TOGGLE
        const notificationBtn = document.getElementById('notificationBtn');
        const notificationDropdown = document.getElementById('notificationDropdown');

        if (notificationBtn && notificationDropdown) {
            notificationBtn.addEventListener('click', function(event) {
                event.stopPropagation();
                notificationDropdown.classList.toggle('show');
            });

            document.addEventListener('click', function(event) {
                if (!notificationDropdown.contains(event.target) && !notificationBtn.contains(event.target)) {
                    notificationDropdown.classList.remove('show');
                }
            });
        }

        // MARK ALL AS READ (Clears notifications until new stock/sale change happens)
        function markNotificationsRead() {
            const currentNotifState = "{{ $notificationCount }}_" + "{{ $outOfStockProducts->pluck('id')->join('-') }}_" + "{{ $lowStockProducts->pluck('id')->join('-') }}_" + "{{ $recentSales->first()?->id ?? 0 }}";
            localStorage.setItem('notifications_cleared_state', currentNotifState);
            renderNotificationsCleared();
        }

        function renderNotificationsCleared() {
            const list = document.getElementById('notificationList');
            if (list) {
                list.innerHTML = `
                    <div class="no-notifications">
                        <i class="fa-regular fa-bell-slash"></i>
                        <h4>No notifications</h4>
                        <p>You're all caught up!</p>
                    </div>
                `;
            }

            const badge = document.getElementById('notificationBadge');
            if (badge) badge.style.display = 'none';

            const button = document.getElementById('markAllBtn');
            if (button) button.innerText = 'All caught up';

            const subtitle = document.getElementById('notificationSubtitle');
            if (subtitle) subtitle.innerText = '0 alerts';
        }
    </script>
</body>
</html>
