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

        {{-- Firma manuscrita: se usa para validar informes mensuales --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mt-4">
            <label class="block text-sm text-gray-400 mb-3">Firma</label>

            <div style="position:relative;width:110px;height:64px;">
                <div style="width:110px;height:64px;border:1px solid #e5e7eb;border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden;background:#fafafa;">
                    @if($signature_url)
                        <img src="{{ $signature_url }}" alt="Firma actual" style="max-width:100%;max-height:100%;">
                    @else
                        <span class="text-xs text-gray-300">Sin firma</span>
                    @endif
                </div>
                <button type="button" onclick="toggleFirmaEdit()" title="Editar firma"
                        style="position:absolute;bottom:-4px;right:-4px;width:28px;height:28px;border-radius:50%;background:#fff;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.3);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#555;padding:0;">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z"/></svg>
                </button>
            </div>

            <div id="firma-edit-block" style="display:none;margin-top:14px;">
                <canvas id="signature-pad" width="400" height="150" style="border:1px solid #d1d5db;border-radius:8px;width:100%;max-width:400px;touch-action:none;"></canvas>

                <div class="mt-3 flex gap-2">
                    <button type="button" onclick="window.__sigPad.clear()"
                            class="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
                        Borrar
                    </button>
                    <button type="button" onclick="guardarFirma()"
                            class="px-3 py-1.5 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
                        Guardar firma
                    </button>
                    <button type="button" onclick="toggleFirmaEdit()"
                            class="px-3 py-1.5 text-gray-500 hover:text-gray-700 text-sm font-medium transition-colors">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>
    <script>
        function toggleFirmaEdit() {
            const block = document.getElementById('firma-edit-block');
            const show = block.style.display === 'none';
            block.style.display = show ? 'block' : 'none';
            if (show) {
                if (!window.__sigPad) {
                    window.__sigPad = new SignaturePad(document.getElementById('signature-pad'));
                }
                window.__sigPad.clear();
            }
        }

        function guardarFirma() {
            if (!window.__sigPad || window.__sigPad.isEmpty()) {
                alert('Dibuja tu firma antes de guardar.');
                return;
            }
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '{{ route('perfil.firma') }}';

            const csrf = document.createElement('input');
            csrf.type = 'hidden'; csrf.name = '_token'; csrf.value = '{{ csrf_token() }}';
            form.appendChild(csrf);

            const sig = document.createElement('input');
            sig.type = 'hidden'; sig.name = 'signature'; sig.value = window.__sigPad.toDataURL('image/png');
            form.appendChild(sig);

            document.body.appendChild(form);
            form.submit();
        }
    </script>

</x-app-layout>
