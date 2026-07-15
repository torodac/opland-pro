@if($campo->help_text)
    <span class="app-tooltip">
        <svg class="w-3.5 h-3.5 text-gray-400 hover:text-gray-600 cursor-help" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="app-tooltip-box">{{ $campo->help_text }}</span>
    </span>
@endif
