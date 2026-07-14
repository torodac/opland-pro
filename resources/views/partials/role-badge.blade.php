{{--
    Badge informativo visible solo para admins (global o de proyecto), explicando
    una restricción de rol que existe en esta pantalla y que ellos no sufren.
    Variables: $project (Project), $texto (string)
--}}
@if(auth()->user()?->isAdmin() || auth()->user()?->isProjectAdmin($project))
<div class="mb-4 flex items-start gap-2 px-3 py-2 bg-blue-50 border border-blue-200 text-blue-700 text-xs rounded-lg">
    <svg class="w-4 h-4 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
        <circle cx="12" cy="12" r="9"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 16v-4M12 8h.01"/>
    </svg>
    <div><span class="font-semibold">Restricción de rol en esta pantalla:</span> {{ $texto }}</div>
</div>
@endif
