<x-app-layout :project="$project" :breadcrumb="$breadcrumb">

    <div class="flex items-center gap-2 mb-3">
        <span style="font-size:0.8rem;color:#7e93a1">Ejercicio</span>
        <a href="{{ request()->fullUrlWithQuery(['anio' => $anio - 1, 'page' => null]) }}"
           style="width:26px;height:26px;border-radius:7px;border:1px solid #dce6ee;background:#fff;color:#52697a;display:inline-flex;align-items:center;justify-content:center;text-decoration:none">‹</a>
        <span style="font-size:0.85rem;font-weight:700;color:#16232b;min-width:50px;text-align:center">{{ $anio }}</span>
        <a href="{{ request()->fullUrlWithQuery(['anio' => $anio + 1, 'page' => null]) }}"
           style="width:26px;height:26px;border-radius:7px;border:1px solid #dce6ee;background:#fff;color:#52697a;display:inline-flex;align-items:center;justify-content:center;text-decoration:none">›</a>
    </div>

    <form method="GET" class="mb-4">
        <input type="hidden" name="anio" value="{{ $anio }}">
        <input type="text" name="q" value="{{ $q }}" placeholder="Buscar vivienda..."
               class="w-full max-w-xs border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-orange-200 focus:border-orange-300">
    </form>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                <tr>
                    <th class="px-4 py-2 text-left">Vivienda</th>
                    <th class="px-4 py-2 text-left" style="width:180px">Tarjeta {{ $anio }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($viviendas as $v)
                <tr>
                    <td class="px-4 py-2 text-gray-700">{{ $v->nombre }}</td>
                    <td class="px-4 py-2">
                        <div x-data="{
                                valor: {{ json_encode($v->codigo ?? '') }},
                                original: {{ json_encode($v->codigo ?? '') }},
                                estado: '',
                                async guardar() {
                                    if (this.valor === this.original) return;
                                    this.estado = 'guardando';
                                    try {
                                        const r = await fetch('{{ route('mb.tarjetas.asignacion.guardar', $project->slug) }}', {
                                            method: 'POST',
                                            headers: {'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'},
                                            body: JSON.stringify({ id_viviendas: {{ $v->id }}, anio: {{ $anio }}, codigo: this.valor })
                                        });
                                        const data = await r.json();
                                        if (r.ok) {
                                            this.original = this.valor;
                                            this.estado = 'ok';
                                        } else {
                                            this.valor = this.original;
                                            this.estado = 'error';
                                            alert(data.error || 'No se ha podido guardar.');
                                        }
                                    } catch (e) {
                                        this.valor = this.original;
                                        this.estado = 'error';
                                    }
                                    setTimeout(() => this.estado = '', 1500);
                                }
                             }">
                            <input type="text" x-model="valor" @blur="guardar()" @keydown.enter="$el.blur()"
                                   maxlength="20" placeholder="—"
                                   class="w-full border rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2"
                                   :class="estado === 'error' ? 'border-red-300 ring-red-100' : (estado === 'ok' ? 'border-green-300 ring-green-100' : 'border-gray-200 ring-orange-100 focus:border-orange-300')">
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="2" class="px-4 py-8 text-center text-gray-400">No hay viviendas.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $viviendas->links() }}
    </div>

</x-app-layout>
