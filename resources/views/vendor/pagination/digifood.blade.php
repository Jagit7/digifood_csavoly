@if ($paginator->hasPages())

<nav>
    <ul class="pagination pagination-xs pagination-gutter pagination-warning">

        {{-- Előző --}}
        @if ($paginator->onFirstPage())
            <li class="page-item page-indicator disabled">
                <span class="page-link">
                    <i class="la la-angle-left"></i>
                </span>
            </li>
        @else
            <li class="page-item page-indicator">
                <a class="page-link" href="{{ $paginator->previousPageUrl() }}">
                    <i class="la la-angle-left"></i>
                </a>
            </li>
        @endif

        {{-- Oldalszámok --}}
        @foreach ($elements as $element)

            @if (is_string($element))
                <li class="page-item disabled">
                    <span class="page-link">{{ $element }}</span>
                </li>
            @endif

            @if (is_array($element))
                @foreach ($element as $page => $url)

                    @if ($page == $paginator->currentPage())
                        <li class="page-item active">
                            <span class="page-link">{{ $page }}</span>
                        </li>
                    @else
                        <li class="page-item">
                            <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                        </li>
                    @endif

                @endforeach
            @endif

        @endforeach

        {{-- Következő --}}
        @if ($paginator->hasMorePages())
            <li class="page-item page-indicator">
                <a class="page-link" href="{{ $paginator->nextPageUrl() }}">
                    <i class="la la-angle-right"></i>
                </a>
            </li>
        @else
            <li class="page-item page-indicator disabled">
                <span class="page-link">
                    <i class="la la-angle-right"></i>
                </span>
            </li>
        @endif

    </ul>
</nav>

@endif