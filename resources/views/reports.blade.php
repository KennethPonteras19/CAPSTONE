<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports | TMTA POS</title>
    <link rel="stylesheet" href="{{ asset('css/reports.css') }}">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    @include('partials.admin-sidebar-assets')
</head>
<body class="admin-workspace">
@include('partials.admin-sidebar')
@php
    $history = $report['forecasting']['history'];
    $forecast = $report['forecasting']['forecast'];
    $summary = $report['summary'];
    $comparison = $report['comparison'];
@endphp
<main class="main">
    <div class="page-header">
        <div>
            <h1>Reports &amp; Forecasting</h1>
            <p class="subtitle">Detailed sales performance and future sales estimates</p>
        </div>
        <div class="right-section"> 
                <!-- DATE AND TIME -->
                <div id="datetime" class="datetime"></div>
        <button type="button" onclick="window.print()" class="print-btn"><i class="fa-solid fa-print" aria-hidden="true"></i> Print report</button>
    </div>
    <form class="filters report-filters" method="GET" action="{{ route('reports') }}">
        <div>
            <label for="history-period">Sales history</label>
            <select id="history-period" name="history">
                @foreach($historyPeriods as $value => $label)
                    <option value="{{ $value }}" @selected($history['period'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="forecast-period">Forecast period</label>
            <select id="forecast-period" name="forecast">
                @foreach($forecastPeriods as $value => $label)
                    <option value="{{ $value }}" @selected($forecast['period'] === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="filter-btn">Update report</button>
        <p class="report-note">Rolling periods anchored to today. History ends yesterday; future forecasts start tomorrow. Today is available separately. Month, quarter, and year span 1, 3, and 12 months.</p>
    </form>
    @if($errors->any())
        <p class="report-empty" role="alert">Choose one of the available history and forecast periods.</p>
    @endif
    <div id="reportContent">
        <div class="report-context">
            <div><strong>{{ $historyPeriods[$history['period']] }}</strong><span>{{ $history['range'] }}</span></div>
            <p>Generated {{ $report['generated_at'] }} · All amounts in PHP (₱)</p>
        </div>
        <div class="ui-metrics">
            <x-metric label="Total sales" :value="'₱'.number_format($history['total'], 2)" tone="success" icon="fa-peso-sign" hint="Booked sales in this period" />
            <x-metric label="Transactions" :value="number_format($summary['transactions'])" tone="info" icon="fa-receipt" hint="Orders / receipt groups" />
            <x-metric label="Units sold" :value="number_format($summary['units'])" tone="info" icon="fa-box" hint="Combined item quantities" />
            <x-metric label="Average transaction" :value="'₱'.number_format($summary['average_order'], 2)" tone="info" icon="fa-chart-line" hint="Sales divided by transactions" />
            <x-metric label="Paid toward these sales" :value="'₱'.number_format($summary['paid'], 2)" tone="success" icon="fa-circle-check" hint="Payments recorded as of now" />
            <x-metric label="Outstanding balance" :value="'₱'.number_format($summary['outstanding'], 2)" tone="warning" icon="fa-clock" hint="Still unpaid on these sales" />
            <x-metric label="Daily average" :value="'₱'.number_format($history['daily_average'], 2)" tone="info" icon="fa-chart-line" hint="Includes days with no sales" />
            <x-metric label="Days with sales" :value="$summary['active_days'].' / '.$summary['days']" tone="info" icon="fa-calendar-days" hint="Calendar days in the period" />
        </div>
        <section class="report-section comparison-section" aria-labelledby="comparison-title">
            <div>
                <h2 id="comparison-title">Compared with the previous period</h2>
                <p class="report-note">{{ $comparison['range'] }} · Previous sales: ₱{{ number_format($comparison['total'], 2) }}</p>
                <p class="comparison-value {{ $comparison['change'] < 0 ? 'decrease' : 'increase' }}">
                    {{ $comparison['change'] > 0 ? '+' : ($comparison['change'] < 0 ? '−' : '') }}₱{{ number_format(abs($comparison['change']), 2) }}
                    @if($comparison['percent'] !== null)
                        <span>({{ $comparison['percent'] > 0 ? '+' : '' }}{{ number_format($comparison['percent'], 1) }}%)</span>
                    @else
                        <span>Percentage unavailable: previous sales were zero.</span>
                    @endif
                </p>
                <p class="report-note">Compares the immediately preceding {{ $summary['days'] }} days. Historical periods contain complete days. When Today is selected, sales so far are compared with all of yesterday.</p>
            </div>
            <div class="best-day">
                <h3>Highest sales day</h3>
                @if($summary['best_day'])
                    <strong>{{ $summary['best_day']['date'] }} · ₱{{ number_format($summary['best_day']['total'], 2) }}</strong>
                    <p class="report-note">{{ $summary['best_day']['day'] }} · {{ $summary['best_day']['transactions'] }} transactions</p>
                @else
                    <p class="report-note">No sales recorded in this period.</p>
                @endif
            </div>
        </section>
        <div class="report-columns">
            <section class="report-section" aria-labelledby="actual-title">
                <h2 id="actual-title">Actual sales trend</h2>
                <p class="report-note">{{ $history['range'] }} · {{ $history['grouping'] }}. The Today view counts sales recorded so far.</p>
                @unless($history['has_sales'])<p class="report-empty">No sales recorded in this period.</p>@endunless
                <div class="report-chart"><canvas id="historyChart" role="img" aria-label="Actual sales for {{ $history['range'] }}"></canvas></div>
            </section>
            <section class="report-section forecast-section" aria-labelledby="forecast-title">
                <h2 id="forecast-title">Sales forecast · {{ $forecastPeriods[$forecast['period']] }}</h2>
                <p class="report-note">{{ $forecast['range'] }}</p>
                <div class="forecast-totals">
                    <div><span>Estimated total</span><strong>{{ $forecast['available'] ? '₱'.number_format($forecast['total'], 2) : '—' }}</strong></div>
                    <div><span>Daily average</span><strong>{{ $forecast['available'] ? '₱'.number_format($forecast['daily_average'], 2) : '—' }}</strong></div>
                </div>
                @if($forecast['available'])
                    <div class="report-chart"><canvas id="forecastChart" role="img" aria-label="Estimated daily sales for {{ $forecast['range'] }}"></canvas></div>
                @else
                    <div class="report-empty"><h3>Not enough sales history yet</h3><p>At least 14 completed days since the first sale are needed. Currently available: {{ $forecast['training_days'] }} days.</p></div>
                @endif
                <p class="report-note">Future periods start tomorrow. The Today estimate covers the full day, including sales already made.</p>
            </section>
        </div>
        <p id="chart-unavailable" class="report-empty" hidden>Charts could not load. All figures are available in the tables below.</p>
        <noscript><p class="report-empty">Enable JavaScript to see the charts. Report tables and period filters remain available.</p></noscript>
        <section class="report-section" aria-labelledby="daily-title">
            <h2 id="daily-title">Daily sales breakdown</h2>
            <p class="report-note">{{ $summary['days'] }} days · {{ $history['range'] }}. Payment figures reflect the current paid amounts on these sales, including payments made later.</p>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Daily sales breakdown table">
                <table>
                    <thead><tr><th scope="col">Date</th><th scope="col">Day</th><th scope="col" class="numeric">Transactions</th><th scope="col" class="numeric">Units</th><th scope="col" class="numeric">Sales (₱)</th><th scope="col" class="numeric">Paid (₱)</th><th scope="col" class="numeric">Balance (₱)</th><th scope="col" class="numeric">Avg. transaction (₱)</th></tr></thead>
                    <tbody>
                        @foreach($report['daily'] as $row)
                            <tr><th scope="row">{{ $row['date'] }}</th><td>{{ $row['day'] }}</td><td class="numeric">{{ number_format($row['transactions']) }}</td><td class="numeric">{{ number_format($row['units']) }}</td><td class="numeric">{{ number_format($row['total'], 2) }}</td><td class="numeric">{{ number_format($row['paid'], 2) }}</td><td class="numeric">{{ number_format($row['outstanding'], 2) }}</td><td class="numeric">{{ number_format($row['average_order'], 2) }}</td></tr>
                        @endforeach
                    </tbody>
                    <tfoot><tr><th scope="row" colspan="2">Period total / average</th><td class="numeric">{{ number_format($summary['transactions']) }}</td><td class="numeric">{{ number_format($summary['units']) }}</td><td class="numeric">{{ number_format($history['total'], 2) }}</td><td class="numeric">{{ number_format($summary['paid'], 2) }}</td><td class="numeric">{{ number_format($summary['outstanding'], 2) }}</td><td class="numeric">{{ number_format($summary['average_order'], 2) }}</td></tr></tfoot>
                </table>
            </div>
            <p class="report-note">Legacy transactions group matching receipt numbers by date and cashier. POS transactions use the order ID. Item quantities may use different stock units.</p>
        </section>
        <div class="report-columns">
            <section class="report-section forecast-section" aria-labelledby="forecast-daily-title">
                <h2 id="forecast-daily-title">Day-by-day forecast</h2>
                <p class="report-note">{{ $forecast['range'] }} · Estimates, not recorded sales</p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Daily forecast table">
                    <table>
                        <thead><tr><th scope="col">Date</th><th scope="col">Day</th><th scope="col" class="numeric">Estimate (₱)</th><th scope="col" class="numeric">Running total (₱)</th></tr></thead>
                        <tbody>
                            @foreach($report['forecast_daily'] as $row)
                                <tr><th scope="row">{{ $row['date'] }}</th><td>{{ $row['day'] }}</td><td class="numeric">{{ $row['estimate'] !== null ? number_format($row['estimate'], 2) : '—' }}</td><td class="numeric">{{ $row['cumulative'] !== null ? number_format($row['cumulative'], 2) : '—' }}</td></tr>
                            @endforeach
                        </tbody>
                        <tfoot><tr><th scope="row" colspan="2">Estimated period total</th><td class="numeric" colspan="2">{{ $forecast['available'] ? '₱'.number_format($forecast['total'], 2) : 'Unavailable' }}</td></tr></tfoot>
                    </table>
                </div>
            </section>
            <section class="report-section" aria-labelledby="basis-title">
                <h2 id="basis-title">How the forecast is calculated</h2>
                <p class="report-note">Each forecast day uses the average sales of the same weekday across up to 8 recent weeks. Zero-sale days are included. Today's partial sales appear only in the Today view and are excluded from the training average.</p>
                <p class="report-note"><strong>History used:</strong> {{ $forecast['training_range'] ?? 'No completed sales history' }}<br>{{ $forecast['training_days'] }} completed days · {{ $forecast['training_active_days'] }} days with positive sales</p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Weekday averages used for forecasting">
                    <table>
                        <thead><tr><th scope="col">Weekday</th><th scope="col" class="numeric">Days observed</th><th scope="col" class="numeric">Sales (₱)</th><th scope="col" class="numeric">Average (₱)</th></tr></thead>
                        <tbody>
                            @foreach($forecast['weekdays'] as $row)
                                <tr><th scope="row">{{ $row['day'] }}</th><td class="numeric">{{ $row['samples'] }}</td><td class="numeric">{{ number_format($row['total'], 2) }}</td><td class="numeric">{{ $row['average'] !== null ? number_format($row['average'], 2) : '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="report-note">The history filter changes the sales report; it does not change the forecast's recent training period. At least 14 completed days are required for predictions. Estimates do not account for future promotions, holidays, or stock availability.</p>
            </section>
        </div>
        <div class="report-columns">
            <section class="report-section" aria-labelledby="products-title">
                <h2 id="products-title">Top 10 products by sales</h2>
                <p class="report-note">{{ $history['range'] }} · Share of total period sales</p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Top selling products">
                    <table>
                        <thead><tr><th scope="col">Product</th><th scope="col" class="numeric">Units</th><th scope="col" class="numeric">Sales (₱)</th><th scope="col" class="numeric">Share</th></tr></thead>
                        <tbody>
                            @forelse($report['products'] as $row)
                                <tr><th scope="row">{{ $row['name'] }}</th><td class="numeric">{{ number_format($row['units']) }}</td><td class="numeric">{{ number_format($row['total'], 2) }}</td><td class="numeric">{{ number_format($row['share'], 1) }}%</td></tr>
                            @empty
                                <tr><td colspan="4">No product sales in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="report-note">Grouped by recorded product name. Sales reflect discounts and any delivery fee allocated to the sale.</p>
            </section>
            <section class="report-section" aria-labelledby="stock-title">
                <h2 id="stock-title">Current stock alerts</h2>
                <p class="report-note">Live inventory as of report generation. These are current stock levels, not predictions or historical stock.</p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Current low stock products">
                    <table>
                        <thead><tr><th scope="col">Product</th><th scope="col" class="numeric">Stock</th><th scope="col">Status</th></tr></thead>
                        <tbody>
                            @forelse($lowStock as $product)
                                <tr><th scope="row">{{ $product->name }}</th><td class="numeric">{{ $product->stock }} {{ $product->stock_unit }}</td><td>{{ $product->stock <= 0 ? 'Out of stock' : 'Low stock' }}</td></tr>
                            @empty
                                <tr><td colspan="3">All products are above the low-stock threshold of 5.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
        <p class="report-note report-footnote">Sales and forecasts include booked sales regardless of payment status. Cancelled orders, held orders, and quotations are excluded. Predictions use the same calculation as the admin dashboard and are estimates, not guaranteed revenue.</p>
    </div>
</main>
@include('partials.report-print-summary')
<script>
    document.querySelectorAll('.report-filters select').forEach(select => {
        select.addEventListener('change', () => select.form.requestSubmit());
    });
    const forecasting = {{ Illuminate\Support\Js::from($report['forecasting']) }};
    const peso = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const reportCharts = [];
    function renderChart(id, series, predicted) {
        const canvas = document.getElementById(id);
        if (!canvas) return;
        reportCharts.push(new Chart(canvas, {
            type: 'line',
            data: {
                labels: series.labels,
                datasets: [{
                    label: predicted ? 'Estimated sales (₱)' : 'Actual sales (₱)',
                    data: series.values,
                    borderColor: predicted ? '#7c3aed' : '#2185d0',
                    backgroundColor: predicted ? 'rgba(124, 58, 237, .08)' : 'rgba(33, 133, 208, .08)',
                    borderDash: predicted ? [6, 4] : [],
                    pointRadius: series.labels.length > 14 ? 2 : 3,
                    fill: true, tension: .15
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { tooltip: { callbacks: { label: context => context.dataset.label + ': ' + peso.format(context.parsed.y) } } },
                scales: {
                    x: { ticks: { maxTicksLimit: 5, maxRotation: 0 }, grid: { display: false } },
                    y: { beginAtZero: true, ticks: { callback: value => peso.format(value) } }
                }
            }
        }));
    }
    if (typeof Chart !== 'undefined') {
        renderChart('historyChart', forecasting.history, false);
        renderChart('forecastChart', forecasting.forecast, true);
    } else {
        document.getElementById('chart-unavailable').hidden = false;
    }
    function updateDateTime() {
    const now = new Date();
    const date = now.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'short',
        day: 'numeric'
    });
    const time = now.toLocaleTimeString();
    const element = document.getElementById('datetime');

    if (element) element.textContent = date + ' | ' + time;
}

setInterval(updateDateTime, 1000);
updateDateTime();
</script>
</body>
</html>
