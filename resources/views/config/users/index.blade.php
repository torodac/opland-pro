<x-app-layout :project="null" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => 'Usuarios', 'url' => ''],
]">

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="flex items-center justify-between mb-6">
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Usuarios de la aplicación</h2>
        <p class="text-xs text-gray-400">Los usuarios se crean desde la tabla Usuarios de cada proyecto.</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">
        @forelse($users as $user)
            <div class="px-5 py-3 flex items-center gap-4">
                <div class="w-8 h-8 rounded-full bg-orange-100 flex items-center justify-center shrink-0">
                    <span class="text-sm font-semibold text-orange-600">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-800">{{ $user->name }}</p>
                    <p class="text-xs text-gray-400">{{ $user->email }}</p>
                </div>
                <div class="flex flex-wrap gap-1.5 justify-end">
                    @foreach($user->roles as $role)
                        @php
                            $label = match(true) {
                                $role->role === 'admin' => ['Admin global', 'bg-red-100 text-red-700'],
                                str_starts_with($role->role, 'admin_') => ['Admin ' . substr($role->role, 6), 'bg-orange-100 text-orange-700'],
                                str_ends_with($role->role, '_usuarios') => [substr($role->role, 0, -9), 'bg-blue-100 text-blue-700'],
                                default => [$role->role, 'bg-gray-100 text-gray-600'],
                            };
                        @endphp
                        <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $label[1] }}">{{ $label[0] }}</span>
                    @endforeach
                </div>
                <div class="flex gap-2 shrink-0">
                    @if(auth()->id() !== $user->id)
                    <form method="POST" action="{{ route('config.users.impersonate', $user) }}">
                        @csrf
                        <button type="submit" title="Impersonar a {{ $user->name }}"
                                class="text-xs px-3 py-1.5 bg-purple-50 hover:bg-purple-100 text-purple-600 rounded-lg transition-colors flex items-center gap-1">
                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z"/>
                            </svg>
                            Impersonar
                        </button>
                    </form>
                    @endif
                    <a href="{{ route('config.users.edit', $user) }}"
                       class="text-xs px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 rounded-lg transition-colors">
                        Editar
                    </a>
                    <form method="POST" action="{{ route('config.users.destroy', $user) }}"
                          onsubmit="return confirm('¿Eliminar usuario {{ addslashes($user->name) }}?')">
                        @csrf @method('DELETE')
                        <button type="submit"
                                class="text-xs px-3 py-1.5 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg transition-colors">
                            Eliminar
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="px-5 py-8 text-center text-sm text-gray-400">No hay usuarios registrados.</div>
        @endforelse
    </div>

</x-app-layout>
