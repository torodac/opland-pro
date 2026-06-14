<x-app-layout :project="null" :breadcrumb="[
    ['label' => 'Mi perfil', 'url' => ''],
]">

    @if(session('success'))
        <div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 text-sm rounded-lg">
            {{ session('success') }}
        </div>
    @endif

    <div class="max-w-lg mx-auto">
        <form method="POST" action="{{ route('perfil.update') }}">
            @csrf @method('PATCH')

            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">

                {{-- Nombre --}}
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-40 shrink-0 text-sm text-gray-400 pt-2">Nombre</label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           required
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 @error('name') border-red-400 @enderror">
                </div>

                {{-- Email (solo lectura) --}}
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-40 shrink-0 text-sm text-gray-400 pt-2">Email</label>
                    <span class="flex-1 text-sm text-gray-500 pt-2">{{ $user->email }}</span>
                </div>

                {{-- Roles (solo lectura) --}}
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-40 shrink-0 text-sm text-gray-400 pt-2">Rol</label>
                    <div class="flex-1 flex flex-wrap gap-1.5 pt-1">
                        @forelse($user->roles as $role)
                            @php
                                [$label, $cls] = match(true) {
                                    $role->role === 'admin'                      => ['Admin global',                      'bg-red-100 text-red-700'],
                                    str_starts_with($role->role, 'admin_')       => ['Admin ' . substr($role->role, 6),   'bg-orange-100 text-orange-700'],
                                    str_ends_with($role->role, '_usuarios')      => [substr($role->role, 0, -9),          'bg-blue-100 text-blue-700'],
                                    default                                       => [$role->role,                         'bg-gray-100 text-gray-600'],
                                };
                            @endphp
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $cls }}">{{ $label }}</span>
                        @empty
                            <span class="text-sm text-gray-400">Sin rol asignado</span>
                        @endforelse
                    </div>
                </div>

                {{-- Nueva contraseña --}}
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-40 shrink-0 text-sm text-gray-400 pt-2">Nueva contraseña</label>
                    <input type="password" name="password" autocomplete="new-password"
                           placeholder="Dejar vacío para no cambiar"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300 @error('password') border-red-400 @enderror">
                </div>

                {{-- Repetir contraseña --}}
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-40 shrink-0 text-sm text-gray-400 pt-2">Repita contraseña</label>
                    <input type="password" name="password_confirmation" autocomplete="new-password"
                           placeholder="Repita la nueva contraseña"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                </div>

            </div>

            @if($errors->any())
                <div class="mt-3 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                    <ul class="list-disc list-inside space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-4">
                <button type="submit"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>

</x-app-layout>
