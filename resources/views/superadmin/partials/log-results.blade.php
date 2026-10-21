<div class="table-wrap">
    <table class="table" id="logsTable">
        <thead>
            <tr><th>Timestamp</th><th>Event</th><th>Remarks</th></tr>
        </thead>
        <tbody>
            @forelse($logs as $log)
                @php
                    $severity = $log['severity'];
                    $badgeClass = in_array($severity, ['error', 'critical', 'alert', 'emergency'], true)
                        ? 'danger' : ($severity === 'warning' ? 'warning' : 'info');
                @endphp
                <tr>
                    <td>{{ $log['timestamp'] }}</td>
                    <td class="log-event">{{ $log['event'] }}</td>
                    <td><span class="badge {{ $badgeClass }}">{{ ucfirst($severity) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="3">No logs match the current filters, or no activity has been recorded yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="log-pagination">
    <p class="note" id="logsNote">Showing {{ $logs->firstItem() ?? 0 }}–{{ $logs->lastItem() ?? 0 }} of {{ $logs->total() }} records. Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}.</p>
    @if($logs->hasPages())
        <nav aria-label="System log pages" class="log-page-links">
            @if($logs->onFirstPage())
                <span class="log-page disabled" aria-disabled="true">Previous</span>
            @else
                <a class="log-page" href="{{ $logs->previousPageUrl() }}" rel="prev">Previous</a>
            @endif
            @php($previousPage = 0)
            @foreach(range(1, $logs->lastPage()) as $page)
                @if($page === 1 || $page === $logs->lastPage() || abs($page - $logs->currentPage()) <= 2)
                    @if($previousPage && $page - $previousPage > 1)
                        <span aria-hidden="true">…</span>
                    @endif
                    @if($page === $logs->currentPage())
                        <span class="log-page current" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="log-page" href="{{ $logs->url($page) }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                    @endif
                    @php($previousPage = $page)
                @endif
            @endforeach
            @if($logs->hasMorePages())
                <a class="log-page" href="{{ $logs->nextPageUrl() }}" rel="next">Next</a>
            @else
                <span class="log-page disabled" aria-disabled="true">Next</span>
            @endif
        </nav>
    @endif
</div>
