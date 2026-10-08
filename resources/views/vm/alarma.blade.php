{{-- Ficha de la alarma de una propiedad.

     La palabra clave y la contraseña NO están en este HTML. Los recuadros salen vacíos y el
     valor se pide con una petición que primero apunta quién lo ha consultado. Si vinieran en el
     HTML enmascarados por CSS, bastaría con mirar el código fuente para leerlos sin dejar
     rastro, y el bloque de accesos de abajo no serviría de nada. --}}
<x-app-layout
    :breadcrumb="[
        ['label' => 'Alarmas', 'url' => route('vm.alarmas', $project->slug)],
        ['label' => $alarma->propiedad, 'url' => ''],
    ]"
    :project="$project">

{{-- En la cabecera, con la misma forma que los de la ficha estándar del no-code. --}}
<x-slot name="actions">
    @if($puedeEditar)
    <a href="{{ route('vm.alarma.nueva', $project->slug) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        <span class="hidden sm:inline">Nuevo</span>
    </a>
    <a href="{{ route('vm.alarma.editar', [$project->slug, $alarma->id]) }}"
       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 hover:bg-gray-200 text-gray-600 text-sm font-medium rounded-lg transition-colors">
        <i class="fa-solid fa-pen-to-square text-sm"></i>
        <span class="hidden sm:inline">Editar</span>
    </a>
    @endif
</x-slot>

<style>
.al-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .al-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.al-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;
          margin:0 0 12px;display:flex;align-items:center;gap:6px}
.al-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.al-grid3{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.al-cell{background:rgba(0,0,0,.03);border-radius:8px;padding:.8rem}
.dark .al-cell{background:rgba(255,255,255,.04)}
.al-lbl{font-size:11px;color:#888;margin:0 0 3px}
.al-val{font-size:13px;font-weight:500;margin:0}

/* El recuadro sensible: un botón con pinta de campo tapado, para que se vea que hay algo y que
   pulsarlo tiene consecuencias. */
.al-secreto{display:flex;align-items:center;gap:8px;width:100%;text-align:left;cursor:pointer;
            border:0.5px dashed rgba(0,0,0,.2);background:transparent;border-radius:6px;
            padding:5px 8px;font-size:13px;font-family:inherit;color:#185FA5}
.dark .al-secreto{border-color:rgba(255,255,255,.25);color:#7FB2E5}
.al-secreto:hover{background:rgba(0,0,0,.03)}
.al-secreto[disabled]{cursor:default;color:#999;border-style:solid}
.al-puntos{letter-spacing:.18em;color:#999}
.al-revelado{font-size:14px;font-weight:600;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;
             margin:0;word-break:break-all}
.al-aviso{font-size:11px;color:#9A5B00;background:#FDF8F1;border:0.5px solid #E3C9A3;
          border-radius:6px;padding:.5rem .7rem;margin:0 0 12px;font-size:12px}
/* El registro de consultas va sin fondo ni borde: es un apéndice de la ficha, no una
   tarjeta más al mismo nivel que los datos. */
.al-plano{background:transparent;border:0;padding:1.1rem 0 0}
.dark .al-plano{background:transparent}
.al-tabla{width:100%;border-collapse:collapse}
.al-tabla th{text-align:left;padding:6px 10px;font-size:11px;color:#888;font-weight:500}
.al-tabla td{padding:6px 10px;font-size:13px;border-top:0.5px solid rgba(0,0,0,.06)}
.dark .al-tabla td{border-color:rgba(255,255,255,.08)}
</style>

<div style="padding:0 0 3rem;">

@if(session('status'))
    <div style="margin-bottom:1rem;padding:.7rem 1rem;border-radius:8px;background:#F1FAF4;
                border:0.5px solid #B7E0C4;color:#1B7F3B;font-size:13px">{{ session('status') }}</div>
@endif

{{-- ── Bloque 1: la alarma y su propiedad ──────────────────────────────── --}}
<div class="al-card">
  <div class="al-title"><i class="ti ti-shield-lock"></i>Alarma</div>
  <div class="al-grid">
    <div class="al-cell">
      <p class="al-lbl">Empresa de seguridad</p>
      <p class="al-val">{{ $alarma->empresa ?: '—' }}</p>
    </div>
    <div class="al-cell">
      <p class="al-lbl">Tipo de alarma</p>
      <p class="al-val">{{ $alarma->tipo_alarma ?: '—' }}</p>
    </div>
    <div class="al-cell">
      <p class="al-lbl">Número de contrato</p>
      <p class="al-val">{{ $alarma->num_contrato ?: '—' }}</p>
    </div>
    <div class="al-cell">
      <p class="al-lbl">Titular</p>
      <p class="al-val">{{ $alarma->titular ?: '—' }}</p>
    </div>
    <div class="al-cell">
      <p class="al-lbl">DNI o NIE del titular</p>
      <p class="al-val">{{ $alarma->titular_doc ?: '—' }}</p>
    </div>
    {{-- El nombre y la dirección se leen de la propiedad, no se guardan en la alarma. --}}
    <div class="al-cell">
      <p class="al-lbl">Propiedad</p>
      <p class="al-val">
        <a href="{{ url($project->slug . '/propiedades/' . $alarma->id_propiedades) }}"
           style="color:#185FA5;text-decoration:none">{{ $alarma->propiedad }}</a>
      </p>
    </div>
    <div class="al-cell" style="grid-column:span 2">
      <p class="al-lbl">Dirección de la propiedad</p>
      <p class="al-val">{{ $alarma->direccion ?: '—' }}</p>
    </div>
  </div>
</div>

{{-- ── Bloque 2: a quién se llama, en orden ────────────────────────────── --}}
<div class="al-card">
  <div class="al-title"><i class="ti ti-phone"></i>Contactos para incidencias</div>
  <div class="al-grid3">
    @foreach([1 => 'Primer contacto', 2 => 'Segundo contacto', 3 => 'Tercer contacto'] as $n => $etiqueta)
      @php
          $nombre   = $alarma->{"contacto{$n}_nombre"};
          $telefono = $alarma->{"contacto{$n}_telefono"};
      @endphp
      <div class="al-cell">
        <p class="al-lbl">{{ $etiqueta }}</p>
        <p class="al-val">{{ $nombre ?: '—' }}</p>
        @if($telefono)
          <p class="al-val" style="font-weight:400;color:#666;margin-top:2px">
            <a href="tel:{{ $telefono }}" style="color:#185FA5;text-decoration:none">{{ $telefono }}</a>
          </p>
        @endif
      </div>
    @endforeach
  </div>
</div>

{{-- ── Bloque 3: lo sensible ───────────────────────────────────────────── --}}
<div class="al-card">
  <div class="al-title"><i class="ti ti-key"></i>Credenciales</div>

  <p class="al-aviso">
    Al mostrar la palabra clave o la contraseña queda registrado tu nombre y la hora en el
    apartado de abajo.
  </p>

  <div class="al-grid3">
    <div class="al-cell">
      <p class="al-lbl">Usuario en la app</p>
      <p class="al-val">{{ $alarma->app_usuario ?: '—' }}</p>
    </div>

    @foreach(['palabra_clave' => $alarma->tiene_palabra_clave, 'app_password' => $alarma->tiene_app_password] as $campo => $tiene)
      <div class="al-cell">
        <p class="al-lbl">{{ $sensibles[$campo] }}</p>
        @if($tiene)
          <button type="button" class="al-secreto" id="btn-{{ $campo }}"
                  onclick="revelar('{{ $campo }}')">
            <span class="al-puntos">••••••••</span>
            <span style="font-size:12px">Mostrar</span>
          </button>
          <p class="al-revelado" id="val-{{ $campo }}" style="display:none"></p>
        @else
          <p class="al-val">—</p>
        @endif
      </div>
    @endforeach
  </div>
</div>

{{-- ── Bloque 4: quién ha mirado qué ───────────────────────────────────── --}}
<div class="al-card al-plano">
  <div class="al-title"><i class="ti ti-eye"></i>Consultas registradas</div>
  <table class="al-tabla">
    <thead>
      <tr>
        <th>Fecha</th>
        <th>Usuario</th>
        <th>Campo consultado</th>
      </tr>
    </thead>
    <tbody id="al-accesos">
      @forelse($accesos as $x)
        <tr>
          <td style="white-space:nowrap;font-variant-numeric:tabular-nums">
            {{ \Carbon\Carbon::parse($x->fecha)->format('d/m/Y H:i') }}
          </td>
          <td>{{ $x->usuario }}</td>
          <td>{{ $sensibles[$x->campo] ?? $x->campo }}</td>
        </tr>
      @empty
        <tr id="al-sin-accesos">
          <td colspan="3" style="color:#888;font-size:13px">Nadie ha consultado estos datos todavía.</td>
        </tr>
      @endforelse
    </tbody>
  </table>
</div>

</div>

<script>
const AL_CSRF = '{{ csrf_token() }}';
const AL_URL  = '{{ route('vm.alarma.revelar', [$project->slug, $alarma->id]) }}';

async function revelar(campo) {
    const btn = document.getElementById('btn-' + campo);
    btn.disabled = true;

    const r = await fetch(AL_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': AL_CSRF, 'Accept': 'application/json' },
        body: JSON.stringify({ campo: campo }),
    });
    const j = await r.json().catch(() => ({}));

    if (!r.ok || !j.ok) {
        btn.disabled = false;
        alert('No se ha podido mostrar el dato.');
        return;
    }
    if (j.valor === null) {
        btn.disabled = false;
        alert('Este dato no se puede leer. Vuelve a guardarlo desde Editar.');
        return;
    }

    const destino = document.getElementById('val-' + campo);
    destino.textContent   = j.valor;
    destino.style.display = 'block';
    btn.style.display     = 'none';

    // La consulta que se acaba de hacer se añade arriba sin recargar: el usuario tiene que ver
    // que su acceso ha quedado apuntado, no enterarse la próxima vez que entre.
    if (j.acceso) {
        const sin = document.getElementById('al-sin-accesos');
        if (sin) sin.remove();
        const fila = document.createElement('tr');
        fila.innerHTML = '<td style="white-space:nowrap;font-variant-numeric:tabular-nums"></td><td></td><td></td>';
        fila.children[0].textContent = j.acceso.fecha;
        fila.children[1].textContent = j.acceso.usuario;
        fila.children[2].textContent = j.acceso.campo;
        document.getElementById('al-accesos').prepend(fila);
    }
}
</script>
</x-app-layout>
