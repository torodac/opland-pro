<x-app-layout :project="null" :breadcrumb="[
    ['label' => 'Admin', 'url' => route('config.projects.index')],
    ['label' => 'Usuarios', 'url' => route('config.users.index')],
    ['label' => $user->exists ? 'Editar usuario' : 'Nuevo usuario', 'url' => ''],
]">

    <div class="max-w-lg mx-auto">
        <form method="POST"
              action="{{ $user->exists ? route('config.users.update', $user) : route('config.users.store') }}">
            @csrf
            @if($user->exists) @method('PUT') @endif

            <div class="bg-white rounded-xl border border-gray-200 divide-y divide-gray-100">

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Nombre <span class="text-red-400">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $user->name) }}"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
                           required autofocus>
                </div>

                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Email <span class="text-red-400">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $user->email) }}"
                           class="flex-1 text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300"
                           required>
                </div>

                @if($user->exists)
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Contraseña</label>
                    <div class="flex-1">
                        <input type="password" name="password"
                               class="w-full text-sm border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-orange-300">
                        <p class="text-xs text-gray-400 mt-1">Déjala vacía para no cambiarla.</p>
                    </div>
                </div>
                @else
                <div class="px-5 py-4 flex items-start gap-4">
                    <label class="w-32 shrink-0 text-sm text-gray-400 pt-2">Contraseña</label>
                    <p class="text-sm text-gray-400 pt-2">Se asignará <span class="font-mono">bienvenido</span> y el usuario deberá cambiarla en su primer acceso.</p>
                </div>
                @endif

                {{-- Roles --}}
                <div class="px-5 py-4">
                    <p class="text-sm text-gray-400 mb-3">Roles y accesos</p>

                    @php $userRoles = old('roles', $user->exists ? $user->roles->pluck('role')->toArray() : []); @endphp

                    <label class="flex items-center gap-3 text-sm text-gray-700 mb-4 pb-4 border-b border-gray-100">
                        <input type="checkbox" name="roles[]" value="admin"
                               {{ in_array('admin', $userRoles) ? 'checked' : '' }}
                               class="w-4 h-4 accent-orange-500">
                        <span><strong>Admin global</strong> — acceso completo a todos los proyectos</span>
                    </label>

                    @foreach($projects as $project)
                        <div class="mb-3">
                            <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">{{ $project->name }}</p>
                            <div class="flex gap-4 ml-1">
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="roles[]" value="admin_{{ $project->slug }}"
                                           {{ in_array('admin_' . $project->slug, $userRoles) ? 'checked' : '' }}
                                           class="w-4 h-4 accent-orange-500">
                                    Administrador
                                </label>
                                <label class="flex items-center gap-2 text-sm text-gray-600">
                                    <input type="checkbox" name="roles[]" value="{{ $project->slug }}_usuarios"
                                           {{ in_array($project->slug . '_usuarios', $userRoles) ? 'checked' : '' }}
                                           class="w-4 h-4 accent-orange-500">
                                    Usuario
                                </label>
                            </div>
                        </div>
                    @endforeach
                </div>

            </div>

            @if($errors->any())
                <div class="mt-3 px-4 py-3 bg-red-50 border border-red-200 text-red-600 text-sm rounded-lg">
                    {{ $errors->first() }}
                </div>
            @endif

            <div class="flex gap-2 mt-4">
                <button type="submit"
                        class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                    {{ $user->exists ? 'Guardar cambios' : 'Crear usuario' }}
                </button>
                <a href="{{ route('config.users.index') }}"
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm rounded-lg transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    </div>

</x-app-layout>
