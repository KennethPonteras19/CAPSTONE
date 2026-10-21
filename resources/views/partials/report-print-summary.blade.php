<article class="print-summary" aria-label="Printable sales summary">
    <header class="summary-heading">
        <div><p class="summary-eyebrow">TMTA POS · Business reports</p><h1>Sales summary</h1><p>{{ $historyPeriods[$history['period']] }} · {{ $history['range'] }}</p></div>
        <div class="summary-prepared"><strong>Prepared by</strong><br>{{ auth('admin')->user()->name }}<br><span>{{ $report['generated_at'] }}</span></div>
    </header>
    <section>
        <h2>Overview</h2>
        <div class="summary-highlights">
            <div><span>Total booked sales</span><strong>₱{{ number_format($history['total'], 2) }}</strong></div>
            <div><span>Transactions</span><strong>{{ number_format($summary['transactions']) }}</strong></div>
            <div><span>Units sold</span><strong>{{ number_format($summary['units']) }}</strong></div>
        </div>
    </section>
    <section>
        <h2>Key figures</h2>
        <table>
            <thead><tr><th>Metric</th><th>Result</th></tr></thead>
            <tbody>
                <tr><th>Payments received</th><td>₱{{ number_format($summary['paid'], 2) }}</td></tr>
                <tr><th>Outstanding balance</th><td>₱{{ number_format($summary['outstanding'], 2) }}</td></tr>
                <tr><th>Average transaction</th><td>₱{{ number_format($summary['average_order'], 2) }}</td></tr>
                <tr><th>Days with sales</th><td>{{ $summary['active_days'] }} of {{ $summary['days'] }}</td></tr>
                <tr><th>Change from previous period</th><td>{{ $comparison['change'] >= 0 ? '+' : '−' }}₱{{ number_format(abs($comparison['change']), 2) }} @if($comparison['percent'] !== null)({{ $comparison['percent'] >= 0 ? '+' : '' }}{{ number_format($comparison['percent'], 1) }}%)@else (no percentage baseline)@endif</td></tr>
            </tbody>
        </table>
        <p class="summary-note">Comparison: {{ $comparison['range'] }} · ₱{{ number_format($comparison['total'], 2) }} in sales. @if($history['period'] === 'today')Today's figures are partial; yesterday is a complete day.@endif</p>
    </section>
    <section>
        <h2>Performance highlights</h2>
        <p><strong>Best sales day:</strong> {{ $summary['best_day'] ? $summary['best_day']['date'].' · ₱'.number_format($summary['best_day']['total'], 2) : 'No sales recorded in this period.' }}</p>
        <table>
            <thead><tr><th>Top products</th><th>Units</th><th>Sales</th><th>Share</th></tr></thead>
            <tbody>
                @forelse(array_slice($report['products'], 0, 3) as $product)
                    <tr><th>{{ $product['name'] }}</th><td>{{ number_format($product['units']) }}</td><td>₱{{ number_format($product['total'], 2) }}</td><td>{{ number_format($product['share'], 1) }}%</td></tr>
                @empty
                    <tr><td colspan="4">No product sales in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
    <section class="summary-outlook">
        <h2>Sales outlook</h2>
        <p>{{ $forecastPeriods[$forecast['period']] }} · {{ $forecast['range'] }}</p>
        @if($forecast['available'])
            <p><strong>Estimated sales: ₱{{ number_format($forecast['total'], 2) }}</strong> · Daily average: ₱{{ number_format($forecast['daily_average'], 2) }}</p>
        @else
            <p>Forecast unavailable: at least 14 completed days of sales history are required.</p>
        @endif
        <p class="summary-note">Estimates use recent weekday averages and are not guaranteed revenue. @if($forecast['period'] === 'today')Today's forecast estimates the full day.@endif</p>
        <p><strong>Current inventory:</strong> {{ $lowStock->where('stock', '<=', 0)->count() }} out of stock · {{ $lowStock->where('stock', '>', 0)->count() }} low stock.</p>
    </section>
    <footer>Booked sales include unpaid sales; cancelled orders, held orders, and quotations are excluded. Payments reflect current paid amounts. Inventory reflects stock at generation time.</footer>
</article>
