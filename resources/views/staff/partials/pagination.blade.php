@if($paginator->hasPages())
<nav class="workspace-pagination no-print" aria-label="Pagination">
    @if($paginator->previousPageUrl())<a class="button secondary" href="{{ $paginator->previousPageUrl() }}">Previous</a>@endif
    <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} · {{ $paginator->total() }} records</span>
    @if($paginator->nextPageUrl())<a class="button secondary" href="{{ $paginator->nextPageUrl() }}">Next</a>@endif
</nav>
@endif
