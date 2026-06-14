@if($paginator->hasPages())
<nav class="flex items-center gap-1">
    {{-- Anterior --}}
    @if($paginator->onFirstPage())
        <span class="px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-300">&laquo;</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}" class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-500">&laquo;</a>
    @endif

    {{-- Páginas --}}
    @foreach($elements as $element)
        @if(is_string($element))
            <span class="px-2 py-1.5 text-xs text-gray-400">{{ $element }}</span>
        @endif
        @if(is_array($element))
            @foreach($element as $page => $url)
                @if($page == $paginator->currentPage())
                    <span class="px-2.5 py-1.5 text-xs border border-orange-500 bg-orange-500 text-white rounded-lg">{{ $page }}</span>
                @else
                    <a href="{{ $url }}" class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-600">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    {{-- Siguiente --}}
    @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="px-2.5 py-1.5 text-xs border border-gray-300 rounded-lg hover:bg-gray-50 text-gray-500">&raquo;</a>
    @else
        <span class="px-2.5 py-1.5 text-xs border border-gray-200 rounded-lg text-gray-300">&raquo;</span>
    @endif
</nav>
@endif
