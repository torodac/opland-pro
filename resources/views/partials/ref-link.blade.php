{{--
    Icono "abrir en nueva pestaña" junto a la etiqueta de un campo desplegable (FK).
    Enlaza a la ficha del registro referenciado, no al listado.
    Variables: $campo (TableField), $valor (mixed), $project (Project)
--}}
@if($campo->type === 'desplegable' && $valor && $campo->getRefTable())
    <a href="{{ route('ficha', [$project->slug, $campo->getRefTable(), $valor]) }}"
       target="_blank" rel="noopener"
       title="Abrir en una nueva pestaña"
       class="text-gray-300 hover:text-orange-500 transition-colors">
        <svg class="w-3 h-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
            <polyline points="15 3 21 3 21 9"/>
            <line x1="10" y1="14" x2="21" y2="3"/>
        </svg>
    </a>
@endif
