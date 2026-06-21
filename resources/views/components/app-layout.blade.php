@props(['project' => null, 'breadcrumb' => null, 'title' => null])

<!DOCTYPE html>
<html lang="es" class="invisible">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? config('app.name') }}</title>
    @if($project?->favicon)
        <link rel="icon" href="{{ asset($project->favicon) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    @livewireStyles
</head>
<body class="bg-gray-50 text-gray-800">
<script>document.documentElement.classList.remove('invisible');</script>

<div class="flex h-screen overflow-hidden"
     x-data="{ sidebarOpen: window.innerWidth >= 768 }"
     @resize.window="sidebarOpen = window.innerWidth >= 768">

    {{-- Overlay móvil --}}
    <div x-show="sidebarOpen"
         x-transition.opacity
         @click="sidebarOpen = false"
         class="fixed inset-0 bg-black/40 z-20 md:hidden"></div>

    {{-- SIDEBAR --}}
    <aside class="fixed md:static inset-y-0 left-0 z-30 flex flex-col bg-white border-r border-gray-200 transition-all duration-200 ease-in-out shrink-0"
           :class="sidebarOpen ? 'w-56' : 'w-0 md:w-14 overflow-hidden'">

        {{-- Logo --}}
        <a href="{{ route('proyectos') }}"
           class="h-14 flex items-center border-b border-gray-200 overflow-hidden shrink-0 hover:bg-gray-50 transition-colors"
           :class="sidebarOpen ? 'px-4 gap-3' : 'justify-center'">
            @if($project && $project->logo)
                <img src="{{ asset($project->logo) }}" class="h-7 w-auto shrink-0" alt="{{ $project->name }}">
            @else
                <div class="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center font-bold text-sm shrink-0">
                    {{ strtoupper(substr($project ? $project->name : config('app.name'), 0, 1)) }}
                </div>
            @endif
            <span class="font-semibold text-gray-800 text-sm truncate"
                  :class="sidebarOpen ? 'opacity-100' : 'opacity-0 hidden'">
                {{ $project ? $project->name : config('app.name') }}
            </span>
        </a>

        {{-- Menú --}}
        <nav class="flex-1 overflow-y-auto overflow-x-hidden py-3 px-2 space-y-0.5">
            @if($project)
                @php
                    $authUser       = auth()->user();
                    $allItems       = $project->menuItems;
                    $mainItems      = $allItems->filter(fn($i) =>
                        !$i->projectTable?->admin_only &&
                        $authUser?->canViewTable($project, $i->projectTable?->name ?? '')
                    );
                    $configItems    = $allItems->filter(fn($i) =>
                        $i->projectTable?->admin_only &&
                        $authUser?->canViewTable($project, $i->projectTable?->name ?? '')
                    );
                    $isProjectAdmin = $authUser?->isProjectAdmin($project);
                @endphp

                @foreach($mainItems as $item)
                    @if($item->children->count())
                        <div x-data="{ open: false }">
                            <button @click="sidebarOpen ? open = !open : sidebarOpen = true"
                                    class="w-full flex items-center justify-between px-2 py-2 text-sm rounded-lg text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                                <span class="flex items-center gap-2 min-w-0">
                                    @if($item->icon)<i class="{{ $item->icon }} w-5 text-center shrink-0 text-xs"></i>@endif
                                    <span class="truncate" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 hidden'">{{ $item->label }}</span>
                                </span>
                                <svg class="w-3 h-3 shrink-0 transition-transform" :class="[open ? 'rotate-180' : '', sidebarOpen ? '' : 'hidden']"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>
                            <div x-show="open && sidebarOpen" class="ml-4 mt-0.5 space-y-0.5">
                                @foreach($item->children as $child)
                                    <a href="{{ $child->resolveUrl() }}"
                                       class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg {{ request()->url() === $child->resolveUrl() ? 'bg-orange-50 text-orange-700 font-medium' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-800' }}">
                                        @if($child->icon)<i class="{{ $child->icon }} w-4 text-center text-xs"></i>@endif
                                        {{ $child->label }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @else
                        <a href="{{ $item->resolveUrl() }}"
                           title="{{ $item->label }}"
                           class="flex items-center gap-2 px-2 py-2 text-sm rounded-lg {{ request()->url() === $item->resolveUrl() ? 'bg-orange-50 text-orange-700 font-medium' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900' }}">
                            @if($item->icon)<i class="{{ $item->icon }} w-5 text-center shrink-0 text-xs"></i>@endif
                            <span class="truncate" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 hidden'">{{ $item->label }}</span>
                        </a>
                    @endif
                @endforeach

                {{-- Submenú Configuración --}}
                @if($configItems->count())
                    @php
                        $currentUrl   = request()->url();
                        $configActive = $configItems->contains(fn($i) => str_starts_with($currentUrl, $i->resolveUrl()));
                    @endphp
                    <div x-data="{ open: {{ $configActive ? 'true' : 'false' }} }" class="pt-2 mt-2 border-t border-gray-100">
                        <button @click="sidebarOpen ? open = !open : sidebarOpen = true"
                                class="w-full flex items-center justify-between px-2 py-2 text-sm rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600">
                            <span class="flex items-center gap-2 min-w-0">
                                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.28c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <span class="truncate text-xs font-semibold uppercase tracking-wide"
                                      :class="sidebarOpen ? 'opacity-100' : 'opacity-0 hidden'">Configuración</span>
                            </span>
                            <svg class="w-3 h-3 shrink-0 transition-transform" :class="[open ? 'rotate-180' : '', sidebarOpen ? '' : 'hidden']"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open && sidebarOpen" class="ml-4 mt-0.5 space-y-0.5">
                            @foreach($configItems as $item)
                                <a href="{{ $item->resolveUrl() }}"
                                   class="flex items-center gap-2 px-3 py-1.5 text-sm rounded-lg {{ request()->url() === $item->resolveUrl() ? 'bg-orange-50 text-orange-700 font-medium' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-800' }}">
                                    @if($item->icon)<i class="{{ $item->icon }} w-4 text-center text-xs"></i>@endif
                                    {{ $item->label }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endif
        </nav>

        {{-- Usuario con menú contextual --}}
        <div class="border-t border-gray-200 p-3 shrink-0" x-data="{
                menuUsuario: false,
                pos: '',
                toggle(btn) {
                    const r = btn.getBoundingClientRect();
                    this.pos = `position:fixed;bottom:${window.innerHeight - r.top + 8}px;left:${r.left}px;width:${r.width}px`;
                    this.menuUsuario = !this.menuUsuario;
                }
            }">
            <button @click="toggle($el)" @click.outside="menuUsuario = false"
                    class="w-full flex items-center gap-2 rounded-lg hover:bg-gray-100 p-1 transition-colors text-left">
                <div class="w-7 h-7 rounded-full bg-orange-100 text-orange-700 flex items-center justify-center font-semibold text-xs shrink-0">
                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                </div>
                <div class="flex-1 min-w-0" :class="sidebarOpen ? 'opacity-100' : 'opacity-0 hidden'">
                    <p class="text-xs font-medium text-gray-700 truncate">{{ auth()->user()->name ?? '' }}</p>
                    <p class="text-xs text-gray-400 truncate">{{ auth()->user()->email ?? '' }}</p>
                </div>
            </button>

            {{-- Menú contextual --}}
            <div x-show="menuUsuario" x-transition
                 :style="pos"
                 class="bg-white border border-gray-200 rounded-xl shadow-lg py-1 z-[100] text-sm">

                {{-- Perfil --}}
                <a href="{{ route('perfil') }}" @click="menuUsuario = false"
                   class="w-full flex items-center gap-2.5 px-3 py-2 text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                    </svg>
                    Perfil
                </a>

                {{-- Administrar proyecto --}}
                @if($project && auth()->user()?->isProjectAdmin($project))
                <a href="{{ route('config.projects.tables.index', $project) }}"
                   @click="menuUsuario = false"
                   class="flex items-center gap-2.5 px-3 py-2 text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 010 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 010-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    Administrar proyecto
                </a>
                @endif

                {{-- Gestionar usuarios (solo admin global) --}}
                @if(auth()->user()?->isAdmin())
                <a href="{{ route('config.users.index') }}"
                   @click="menuUsuario = false"
                   class="flex items-center gap-2.5 px-3 py-2 text-gray-600 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4 shrink-0 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                    </svg>
                    Gestionar usuarios
                </a>
                @endif

                <div class="my-1 border-t border-gray-100"></div>

                {{-- Cerrar sesión --}}
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="w-full flex items-center gap-2.5 px-3 py-2 text-red-500 hover:bg-red-50 transition-colors">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                        </svg>
                        Cerrar sesión
                    </button>
                </form>
            </div>
        </div>

    </aside>

    {{-- ÁREA PRINCIPAL --}}
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        {{-- HEADER --}}
        <header class="h-14 bg-white border-b border-gray-200 flex items-center px-4 shrink-0 gap-3">

            {{-- Toggle sidebar — siempre visible, no puede ser comprimido --}}
            <button @click="sidebarOpen = !sidebarOpen"
                    class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 shrink-0">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>

            {{-- Breadcrumb / título — ocupa el espacio disponible y trunca si es necesario --}}
            <div class="flex-1 min-w-0 overflow-hidden">
                @if($breadcrumb)
                    <nav class="text-sm text-gray-400 flex items-center gap-1.5">
                        @foreach($breadcrumb as $crumb)
                            @if(!$loop->last)
                                <a href="{{ $crumb['url'] }}" class="hover:text-gray-600 shrink-0">{{ $crumb['label'] }}</a>
                                <span class="shrink-0">/</span>
                            @else
                                <span class="text-gray-700 font-medium truncate">{{ $crumb['label'] }}</span>
                            @endif
                        @endforeach
                    </nav>
                @elseif($title)
                    <h1 class="text-sm font-semibold text-gray-800 truncate">{{ $title }}</h1>
                @endif
            </div>

            {{-- Acciones --}}
            <div class="flex items-center gap-2 shrink-0">
                {{ $actions ?? '' }}
            </div>
        </header>

        {{-- CONTENIDO --}}
        <main class="flex-1 overflow-y-auto p-6">
            @if(session('impersonating'))
            <div class="mb-4 flex items-center gap-3 px-4 py-2.5 bg-purple-600 text-white text-sm rounded-xl">
                <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                </svg>
                <span class="flex-1">Estás impersonando a <strong>{{ auth()->user()->name }}</strong></span>
                <form method="POST" action="{{ route('config.users.stop-impersonating') }}">
                    @csrf
                    <button type="submit"
                            class="text-xs px-3 py-1 bg-white/20 hover:bg-white/30 rounded-lg transition-colors font-medium">
                        Volver a mi cuenta
                    </button>
                </form>
            </div>
            @endif
            {{ $slot }}
        </main>

    </div>
</div>

@livewireScripts
</body>
</html>
