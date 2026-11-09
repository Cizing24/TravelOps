@if($paginator->hasPages())
    <nav class="row" aria-label="分頁">
        @if($paginator->onFirstPage()) <span class="muted">上一頁</span>
        @else <a class="button secondary" href="{{ $paginator->previousPageUrl() }}" rel="prev">上一頁</a> @endif
        <span>第 {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }} 頁</span>
        @if($paginator->hasMorePages()) <a class="button secondary" href="{{ $paginator->nextPageUrl() }}" rel="next">下一頁</a>
        @else <span class="muted">下一頁</span> @endif
    </nav>
@endif
