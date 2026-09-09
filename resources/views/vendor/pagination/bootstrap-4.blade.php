@if ($paginator->hasPages())
    <div class="catalog-pagination-wrap">
        <nav class="catalog-pagination" aria-label="Stranice kataloga">
            <ul class="pagination justify-content-center">
                @if ($paginator->onFirstPage())
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link"><i class="fa-regular fa-chevron-left" aria-hidden="true"></i><span class="d-none d-sm-inline">Prethodna</span></span>
                    </li>
                @else
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')"><i class="fa-regular fa-chevron-left" aria-hidden="true"></i><span class="d-none d-sm-inline">Prethodna</span></a>
                    </li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li class="page-item disabled d-none d-sm-block" aria-disabled="true"><span class="page-link">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li class="page-item active d-none d-sm-block" aria-current="page"><span class="page-link">{{ number_format($page, 0, ',', '.') }}</span></li>
                            @else
                                <li class="page-item d-none d-sm-block"><a class="page-link" href="{{ $url }}">{{ number_format($page, 0, ',', '.') }}</a></li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                <li class="page-item pagination-mobile-summary disabled d-sm-none" aria-current="page">
                    <span class="page-link">{{ number_format($paginator->currentPage(), 0, ',', '.') }} / {{ number_format($paginator->lastPage(), 0, ',', '.') }}</span>
                </li>

                @if ($paginator->hasMorePages())
                    <li class="page-item">
                        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')"><span class="d-none d-sm-inline">Sljedeća</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></a>
                    </li>
                @else
                    <li class="page-item disabled" aria-disabled="true">
                        <span class="page-link"><span class="d-none d-sm-inline">Sljedeća</span><i class="fa-regular fa-chevron-right" aria-hidden="true"></i></span>
                    </li>
                @endif
            </ul>
        </nav>

        <p class="catalog-pagination-summary">
            Prikazano <strong>{{ number_format($paginator->firstItem(), 0, ',', '.') }}–{{ number_format($paginator->lastItem(), 0, ',', '.') }}</strong>
            od <strong>{{ number_format($paginator->total(), 0, ',', '.') }}</strong> rezultata
        </p>
    </div>
@endif
