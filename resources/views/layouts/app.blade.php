<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-800">

{{--
    x-data en el body controla el estado del sidebar:
    - sidebarOpen: true = visible, false = oculto
    - En móvil empieza cerrado, en desktop empieza abierto
    - El sidebar se superpone en móvil (overlay), empuja el contenido en desktop
--}}
<div class="flex h-screen overflow-hidden"
     x-data="{ sidebarOpen: window.innerWidth >= 768 }"
     @resize.window="sidebarOpen = window.innerWidth >= 768">

    {{-- OVERLAY para móvil (fondo oscuro al abrir sidebar) --}}
    <div x-show="sidebarOpen"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/40 z-20 md:hidden"></div>

    {{-- SIDEBAR --}}
    <aside class="fixed md:relative inset-y-0 left-0 z-30 flex flex-col bg-white border-r border-gray-200 transition-all duration-200 ease-in-out shrink-0"
           :class="sidebarOpen ? 'w-56' : 'w-0 md:w-14 overflow-hidden'">

        {{-- Logo / nombre del proyecto --}}
        <div class="h-14 flex items-center border-b border-gray-200 overflow-hidden"
             :class="sidebarOpen ? 'px-4 gap-3' : 'justify-center px-0'">
            @if(isset($project) && $project->logo)
                <img src="{{ asset($project->logo) }}" class="h-7 w-auto shrink-0" alt="{{ $project->name }}">
            @else
                <div class="w-7 h-7 rounded-lg bg-orange-100 text-orange-700 flex items-center justify-center font-bold text-sm shrink-0">
                    {{ strtoupper(substr(isset($project) ? $project->name : config('app.name'), 0, 1)) }}
                </div>
            @endif
            <span class="font-semibold text-gray-800 text-sm truncate transition-opacity duration-150"
                  :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">
                {{ isset($project) ? $project->name : config('app.name') }}
            </span>
        </div>

        {{-- Ítems de menú --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 space-y-0.5">
            @isset($project)
                @foreach($project->menuItems as $item)
                    @php
                        $itemTable = $item->projectTable;
                        if ($itemTable && !auth()->user()?->canViewTable($project, $itemTable->name)) continue;
                    @endphp
                    @if($item->children->count())
                        {{-- Ítem con submenú --}}
                        <div x-data="{ open: false }">
                            <button @click="sidebarOpen ? open = !open : sidebarOpen = true"
                                    class="w-full flex items-center justify-between px-2 py-2 text-sm rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                                <span class="flex items-center gap-2 min-w-0">
                                    @if($item->icon)
                                        <i class="{{ $item->icon }} w-5 text-center shrink-0"></i>
                                    @endif
                                    <span class="truncate transition-opacity duration-150"
                                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">{{ $item->label }}</span>
                                </span>
                                <svg class="w-3 h-3 shrink-0 transition-transform"
                                     :class="[open ? 'rotate-180' : '', sidebarOpen ? 'opacity-100' : 'opacity-0 w-0']"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open && sidebarOpen" class="ml-4 mt-0.5 space-y-0.5">
                                @foreach($item->children as $child)
                                    <a href="{{ $child->resolveUrl() }}"
                                       class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg {{ request()->url() === $child->resolveUrl() ? 'bg-orange-50 text-orange-700 font-medium' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-800' }}">
                                        @if($child->icon)<i class="{{ $child->icon }} w-4 text-center"></i>@endif
                                        {{ $child->label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        {{-- Ítem simple --}}
                        <a href="{{ $item->resolveUrl() }}"
                           title="{{ $item->label }}"
                           class="flex items-center gap-2 px-2 py-2 text-sm rounded-lg {{ request()->url() === $item->resolveUrl() ? 'bg-orange-50 text-orange-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                            @if($item->icon)
                                <i class="{{ $item->icon }} w-5 text-center shrink-0"></i>
                            @endif
                            <span class="truncate transition-opacity duration-150"
                                  :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">{{ $item->label }}</span>
                        </a>
                    @endif
                @endforeach
            @endisset
        </nav>

        {{-- Usuario en el pie del sidebar --}}
        <div class="border-t border-gray-200 p-3 overflow-hidden">
            <div class="flex items-center gap-2 text-xs text-gray-500">
                <div class="w-6 h-6 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-semibold text-xs shrink-0">
                    {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                </div>
                <span class="truncate transition-opacity duration-150"
                      :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">{{ auth()->user()->name ?? '' }}</span>
            </div>
        </div>

    </aside>

    {{-- ÁREA PRINCIPAL --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- HEADER --}}
        <header class="h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4 shrink-0">
            <div class="flex items-center gap-3">

                {{-- Botón hamburguesa (toggle sidebar) — visible siempre --}}
                <button @click="sidebarOpen = !sidebarOpen"
                        class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>

                {{-- Breadcrumb / título --}}
                @isset($breadcrumb)
                    <nav class="text-sm text-gray-400 flex items-center gap-1.5">
                        @foreach($breadcrumb as $crumb)
                            @if(!$loop->last)
                                <a href="{{ $crumb['url'] }}" class="hover:text-gray-600">{{ $crumb['label'] }}</a>
                                <span>/</span>
                            @else
                                <span class="text-gray-700 font-medium">{{ $crumb['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                @else
                    <h1 class="text-sm font-semibold text-gray-800">{{ $title ?? '' }}</h1>
                @endisset
            </div>

            {{-- Acciones del header --}}
            <div class="flex items-center gap-2">
                {{ $actions ?? '' }}
            </div>
        </header>

        {{-- CONTENIDO --}}
        <main class="flex-1 overflow-y-auto p-6">
            {{ $slot }}
        </main>

    </div>
</div>

@livewireScripts
@stack('modals')
</body>
</html>
