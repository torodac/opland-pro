@php
    $fmt = fn($n) => $n === null ? '—' : number_format((float) $n, 2, ',', '.') . ' €';
    $pendiente = empty($reserva->estado_fianza);

    // Las fotos con retención son la base de todo lo de abajo: habilitan el botón, llenan la
    // modal y suman el total. Se calcula una vez aquí en vez de recorrer $fotos tres veces.
    $conRetencion = $fotos->filter(fn($f) => (float) ($f->retencion_importe ?? 0) > 0)->values();
    $totalRetencion = (float) $conRetencion->sum('retencion_importe');
    [$estadoFondo, $estadoTinta] = colorEstadoFianza($estado);
@endphp

<x-app-layout
    :breadcrumb="[
        ['label' => 'Dev. fianzas', 'url' => route('vm.dev-fianzas', $project->slug)],
        ['label' => $reserva->booking_id . ($reserva->nombre ? ' · ' . $reserva->nombre : ''), 'url' => ''],
    ]"
    :project="$project">

<style>
.df-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .df-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.df-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px;display:flex;align-items:center;gap:6px}
.df-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.df-cell{background:rgba(0,0,0,.03);border-radius:8px;padding:.8rem}
.df-lbl{font-size:11px;color:#888;margin:0 0 3px}
.df-val{font-size:13px;font-weight:500;margin:0}
.df-modal{position:fixed;inset:0;background:rgba(0,0,0,.4);display:none;align-items:center;justify-content:center;z-index:60}
#df-lightbox{background:rgba(0,0,0,.82)}
.df-modal.open{display:flex}
.df-modal-box{background:#fff;border-radius:12px;padding:1.25rem;width:min(460px,92vw)}
.dark .df-modal-box{background:#1a1a1a}
.df-btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;border:0.5px solid rgba(0,0,0,.15);background:#fff}
/* Un boton deshabilitado tiene que PARECERLO. Los estilos de color van en el atributo style del
   propio boton, asi que hay que ganarles con !important; y las variantes disabled: de Tailwind
   no existen en el CSS compilado de este proyecto, por eso se escribe aqui a mano. */
.df-btn[disabled]{background:#F4F5F6 !important;color:#B4B8BD !important;border-color:rgba(0,0,0,.08) !important;cursor:not-allowed}
.df-input{font-size:13px;padding:6px 10px;border-radius:6px;border:0.5px solid rgba(0,0,0,.18);background:#fff;width:100%}
.df-input:focus{outline:none;border-color:#9AB8D8;box-shadow:0 0 0 3px rgba(24,95,165,.12)}
.df-nav{position:absolute;top:50%;transform:translateY(-50%);width:44px;height:44px;border-radius:50%;border:0;
        background:rgba(255,255,255,.15);color:#fff;font-size:30px;line-height:1;cursor:pointer;
        display:flex;align-items:center;justify-content:center;padding:0 0 4px}
.df-nav:hover{background:rgba(255,255,255,.3)}

/* El escudo va ENCIMA de la foto, no al lado: lo que se decide es sobre esa imagen, y una
   columna aparte obligaria a emparejarla con la vista.
   Es la propia silueta del escudo, sin pastilla ni circulo detras: apagado, blanco translucido;
   encendido, amarillo solido. La sombra proyectada no es adorno -- sin ella la silueta clara
   desaparece sobre una foto clara, que en fotos de limpieza son la mayoria. */
.df-foto{position:relative;border-radius:8px;overflow:hidden;border:0.5px solid rgba(0,0,0,.08);cursor:zoom-in}
.df-foto.marcada{border-color:#F5B301;box-shadow:0 0 0 2px rgba(245,179,1,.4)}
/* Procedencia y retencion, sobre la propia foto: se leen de un vistazo sin abrir el visor.
   El degradado evita que el texto se pierda sobre una foto clara. */
.df-origen{position:absolute;left:0;right:0;bottom:0;padding:14px 6px 4px;font-size:10px;line-height:1.2;
           color:#fff;background:linear-gradient(transparent,rgba(0,0,0,.72));
           white-space:nowrap;overflow:hidden;text-overflow:ellipsis;pointer-events:none}
.df-origen.inc{color:#FFD9D9}
.df-retencion{position:absolute;top:7px;left:7px;font-size:11px;font-weight:700;color:#fff;
              background:rgba(155,28,28,.92);border-radius:5px;padding:2px 6px;pointer-events:none}
.df-escudo{position:absolute;top:7px;right:7px;border:0;background:none;padding:2px;
           cursor:pointer;line-height:0;font-size:19px;
           color:rgba(255,255,255,.62);
           filter:drop-shadow(0 1px 2px rgba(0,0,0,.6));
           transition:color .14s,transform .14s,filter .14s}
.df-escudo:hover{color:rgba(255,255,255,.95);transform:scale(1.12)}
.df-escudo.on{color:#F5B301;filter:drop-shadow(0 1px 3px rgba(0,0,0,.5))}
.df-escudo.on:hover{color:#FFC72C}
.df-escudo[disabled]{cursor:default}
.df-escudo[disabled]:hover{transform:none;color:rgba(255,255,255,.62)}
.df-escudo.on[disabled]:hover{color:#F5B301}
/* En el visor la imagen es grande, asi que el escudo tambien: a 19px se perdia. */
.df-escudo-visor{top:12px;right:12px;font-size:27px}
.df-nav[disabled]{opacity:.2;cursor:default}
</style>

<div style="padding:0 0 3rem;">

{{-- ── Bloque 1: los datos de la decisión ───────────────────────────────── --}}
<div class="df-card">
  <div class="df-title"><i class="ti ti-cash"></i>Fianza</div>

  <div class="df-grid">
    <div class="df-cell">
      <p class="df-lbl">Checkout</p>
      <p class="df-val">{{ \Carbon\Carbon::parse($reserva->check_out_date)->format('d/m/Y') }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Booking</p>
      <p class="df-val">
        <a href="{{ url($project->slug . '/reservas/' . $reserva->id) }}" style="color:#185FA5;text-decoration:none">
          {{ $reserva->booking_id }}
        </a>
      </p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Huésped</p>
      <p class="df-val">{{ $reserva->nombre ?: '—' }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Propiedad</p>
      <p class="df-val">{{ $reserva->propiedad ?: '—' }}</p>
    </div>

    {{-- Segunda fila: primero la evidencia (comentarios y fotos) y luego el dinero, que es el
         orden en que se mira para decidir. --}}
    <div class="df-cell">
      <p class="df-lbl">Fotos</p>
      <p class="df-val">{{ $fotos->count() }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Fianza</p>
      <p class="df-val">{{ $fmt($reserva->fianza) }}</p>
    </div>
    <div class="df-cell">
      <p class="df-lbl">Retención</p>
      {{-- Mientras está pendiente se enseña lo que llevas marcado en las fotos, no el campo
           guardado: es lo que se va a confirmar, y verlo crecer evita confirmar un total que
           no se esperaba. --}}
      <p class="df-val">
        @if($pendiente && $totalRetencion > 0)
          {{ $fmt($totalRetencion) }}
          <span style="font-weight:400;font-size:12px;color:#888">· sin confirmar</span>
        @else
          {{ $reserva->fianza_retencion !== null ? $fmt($reserva->fianza_retencion) : '—' }}
        @endif
      </p>
    </div>
    @if($estado)
    <div class="df-cell">
      <p class="df-lbl">Estado</p>
      <p class="df-val">
        <span style="background:{{ $estadoFondo }};color:{{ $estadoTinta }};padding:2px 10px;border-radius:6px;font-size:14px">{{ $estado }}</span>
      </p>
    </div>
    @endif

    @if(!$pendiente)
    <div class="df-cell" style="grid-column:1/-1">
      <p class="df-lbl">Decisión</p>
      <p class="df-val">
        {{ $reserva->estado_fianza }}
        @if($reserva->decidido_por)
          <span style="font-weight:400;color:#888">
            · {{ $reserva->decidido_por }}
            @if($reserva->fianza_fecha), {{ \Carbon\Carbon::parse($reserva->fianza_fecha)->format('d/m/Y H:i') }}@endif
          </span>
        @endif
      </p>
      @if($reserva->fianza_motivo_retencion)
        <p style="font-size:13px;margin:6px 0 0;white-space:pre-line">{{ $reserva->fianza_motivo_retencion }}</p>
      @endif
    </div>
    @endif
  </div>

  @if($pendiente && $puedeEditar)
    <div style="display:flex;gap:8px;margin-top:12px;align-items:center;flex-wrap:wrap">
      {{-- El canal de cobro decide cómo se devuelve el dinero y a quién le llegan las tareas.
           Va antes que los botones porque es una elección previa, no un detalle de la confirmación. --}}
      <label style="font-size:12px;color:#666;display:flex;align-items:center;gap:6px">
        Canal de cobro
        <select id="df-canal" class="df-input" style="width:auto;padding:5px 8px">
          <option value="">— elegir —</option>
          @foreach($canales as $canal)
            <option value="{{ $canal }}">{{ $canal }}</option>
          @endforeach
        </select>
      </label>
      <button type="button" class="df-btn" onclick="conforme()"
              style="border-color:#B7E0C4;background:#F1FAF4;color:#1B7F3B">
        Conforme
      </button>
      {{-- Deshabilitado mientras ninguna foto tenga retención. El atributo `disabled` por sí solo
           no basta para que LO PAREZCA: las variantes `disabled:` de Tailwind no existen en el
           CSS compilado de este proyecto, así que el aspecto se fuerza desde `.df-btn[disabled]`
           en el CSS de esta misma vista. --}}
      <button type="button" class="df-btn" id="df-btn-retener" onclick="abrirRetener()"
              @disabled($conRetencion->isEmpty())
              title="{{ $conRetencion->isEmpty() ? 'Marca primero el importe a retener en alguna foto' : 'Revisar y confirmar la retención' }}"
              style="border-color:#E3C9A3;background:#FDF8F1;color:#9A5B00">
        Confirmar retención
        <span id="df-btn-retener-n" style="font-weight:600">@if($conRetencion->isNotEmpty())({{ $fmt($totalRetencion) }})@endif</span>
      </button>
    </div>
  @elseif($pendiente)
    <p style="font-size:12px;color:#bbb;margin:12px 0 0">No tienes permiso para decidir sobre esta fianza.</p>
  @else
    <p style="font-size:12px;color:#bbb;margin:12px 0 0">
      Ya decidida. Para rehacerla, deja el estado en blanco en
      <a href="{{ url($project->slug . '/reservas/' . $reserva->id) }}" style="color:#185FA5">la ficha de la reserva</a>.
    </p>
  @endif

  {{-- Transferencia: el número de cuenta llega por el enlace a Filipinas. Aquí se ve el estado de
       ese dato; el IBAN en claro solo aparece a Dirección general, Contabilidad y admin. --}}
  @if(($reserva->fianza_canal_cobro ?? null) === 'Transferencia')
    <div style="margin-top:12px;font-size:13px;color:#555">
      @if($iban)
        <strong>IBAN:</strong> <span style="font-family:monospace">{{ $iban }}</span>
      @elseif(!empty($reserva->fianza_iban_guardado_at))
        <strong>IBAN:</strong> guardado (solo lo ven Dirección general, Contabilidad y admin).
      @else
        <strong>IBAN:</strong> pendiente. Filipinas lo introduce desde el enlace que se les envió.
      @endif
    </div>
  @endif
</div>

{{-- ── Bloque 2: comentarios de las limpiezas de salida ──────────────────── --}}
<div class="df-card">
  <div class="df-title">
    <i class="ti ti-message-2"></i>Comentarios de la limpieza de salida
    @if($comentarios->count())<span style="color:#bbb;font-weight:400">{{ $comentarios->count() }}</span>@endif
  </div>

  {{-- Agrupado por tarea: el nombre se ve siempre, tenga comentarios o no, porque saber qué
       limpieza se hizo (y poder abrirla) ya es información aunque nadie haya escrito nada. --}}
  @if($tareas->isNotEmpty())
    <div style="display:flex;flex-direction:column;gap:14px">
      @foreach($tareas as $t)
        @php $suyos = $comentarios->where('tarea_id', $t->id); @endphp
        <div>
          <div style="font-size:12px;margin-bottom:5px;display:flex;gap:6px;flex-wrap:wrap;align-items:baseline">
            <a href="{{ route('vm.tarea', [$project->slug, 'limpieza', $t->id]) }}"
               target="_blank" rel="noopener"
               style="color:#185FA5;text-decoration:none;font-weight:500">
              {{ $t->nombre }}
              <i class="ti ti-external-link" style="font-size:11px;opacity:.7"></i>
            </a>
            @if($t->fecha_planificada)
              <span style="color:#aaa;font-size:11px">{{ \Carbon\Carbon::parse($t->fecha_planificada)->format('d/m/Y') }}</span>
            @endif
            @if($t->estado)
              <span style="color:#aaa;font-size:11px">· {{ $t->estado }}</span>
            @endif
            <span style="color:#ccc;font-size:11px">· {{ $suyos->count() }} {{ $suyos->count() === 1 ? 'comentario' : 'comentarios' }}</span>
          </div>

          @if($suyos->count())
            <div style="display:flex;flex-direction:column;gap:8px">
              @foreach($suyos as $com)
                <div style="border-left:2px solid #e5e5e5;padding:2px 0 2px 10px">
                  <div style="font-size:11px;color:#aaa;margin-bottom:2px">
                    {{ $com->fecha ? \Carbon\Carbon::parse($com->fecha)->translatedFormat('D j M · H:i') : '' }}
                  </div>
                  <div style="font-size:13px;white-space:pre-line">{{ $com->comentario }}</div>
                </div>
              @endforeach
            </div>
          @else
            <p style="font-size:13px;color:#999;margin:0 0 0 12px">Sin comentarios.</p>
          @endif
        </div>
      @endforeach
    </div>
  @else
    <p style="font-size:13px;color:#999;margin:0">
      Esta reserva no tiene ninguna limpieza de salida asociada.
    </p>
  @endif
</div>

{{-- ── Bloque 3: incidencias reportadas ──────────────────────────────────── --}}
{{-- Separadas de los comentarios a propósito: un comentario es una nota, una incidencia es
     trabajo abierto con un desperfecto detrás, y es lo que justifica una retención. --}}
@if($incidencias->isNotEmpty())
<div class="df-card">
  <div class="df-title">
    <i class="ti ti-alert-triangle"></i>Incidencias reportadas
    <span style="color:#bbb;font-weight:400">{{ $incidencias->count() }}</span>
  </div>

  <div style="display:flex;flex-direction:column;gap:12px">
    @foreach($incidencias as $i)
      <div style="border-left:3px solid #E3A3A3;padding:2px 0 2px 12px">
        <div style="font-size:12px;display:flex;gap:6px;flex-wrap:wrap;align-items:baseline">
          <a href="{{ route('vm.tarea', [$project->slug, 'mantenimiento', $i->id]) }}"
             target="_blank" rel="noopener"
             style="color:#185FA5;text-decoration:none;font-weight:500">
            {{ $i->nombre }}
            <i class="ti ti-external-link" style="font-size:11px;opacity:.7"></i>
          </a>
          @if($i->Tipo)<span style="color:#aaa;font-size:11px">· {{ $i->Tipo }}</span>@endif
          @if($i->estado)<span style="color:#aaa;font-size:11px">· {{ $i->estado }}</span>@endif
          @if($i->breezeway_createdat)
            <span style="color:#aaa;font-size:11px">· {{ \Carbon\Carbon::parse($i->breezeway_createdat)->format('d/m/Y H:i') }}</span>
          @endif
          @if($i->breezeway_task_id)
            <a href="https://app.breezeway.io/task/{{ $i->breezeway_task_id }}"
               target="_blank" rel="noopener" title="Abrir esta incidencia en Breezeway"
               style="color:#185FA5;text-decoration:none;font-size:11px">· Breezeway</a>
          @endif
          @if($i->vinculo_deducido)
            {{-- Honestidad sobre la procedencia: Breezeway no manda esta relación por su API, así
                 que la hemos deducido por propiedad y día. Quien decide sobre dinero tiene que
                 saber que este vínculo es una suposición nuestra. --}}
            <span title="Breezeway no expone por API de qué limpieza salió esta incidencia: la hemos deducido por propiedad y fecha."
                  style="color:#B07D00;font-size:11px;border:0.5px solid #E8D3A0;background:#FDF8F1;border-radius:4px;padding:0 5px">vínculo deducido</span>
          @endif
        </div>
        @if($i->descripcion)
          <p style="font-size:13px;margin:4px 0 0;white-space:pre-line;color:#333">{{ $i->descripcion }}</p>
        @endif
      </div>
    @endforeach
  </div>
</div>
@endif

{{-- ── Bloque 4: galería ─────────────────────────────────────────────────── --}}
<div class="df-card">
  <div class="df-title">
    <i class="ti ti-camera"></i>Fotos de la limpieza de salida
    @if($fotos->count())<span style="color:#bbb;font-weight:400">{{ $fotos->count() }}</span>@endif
    {{-- Cuantas van al informe: es el dato que se mira al preparar el reporte. --}}
    <span id="df-marcadas-wrap" style="margin-left:auto;font-size:11px;font-weight:500;color:#B07D00;
          text-transform:none;letter-spacing:0;{{ $fotos->where('informe_estancia', 1)->count() ? '' : 'display:none' }}">
      <i class="fa-solid fa-shield" style="color:#F5B301"></i>
      <span id="df-marcadas">{{ $fotos->where('informe_estancia', 1)->count() }}</span> para el informe
    </span>
  </div>

  @if($fotos->count())
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:8px">
      @foreach($fotos as $foto)
        @php
            $url     = asset('storage/' . $foto->file_foto);
            $marcada = (bool) ($foto->informe_estancia ?? 0);
        @endphp
        <div class="df-foto {{ $marcada ? 'marcada' : '' }}" id="foto-{{ $foto->id }}"
             onclick="ampliar({{ $loop->index }})" title="{{ $foto->tarea_nombre }}">
          <img src="{{ $url }}" alt="Foto de la limpieza" loading="lazy"
               style="width:100%;aspect-ratio:1;object-fit:cover;display:block">
          {{-- stopPropagation: sin esto, pulsar el escudo abriria tambien el visor. --}}
          <button type="button" class="df-escudo {{ $marcada ? 'on' : '' }}"
                  id="escudo-{{ $foto->id }}"
                  onclick="event.stopPropagation(); abrirRetencionFoto({{ $foto->id }})"
                  {{ $puedeEditar ? '' : 'disabled' }}
                  title="{{ $puedeEditar
                      ? ($marcada ? 'Va al informe de la estancia. Pulsa para editar o quitar.'
                                  : 'Marcar para el informe e indicar retención.')
                      : ($marcada ? 'Se usará en el informe de la estancia' : 'No seleccionada para el informe') }}">
            <i class="fa-solid fa-shield"></i>
          </button>
          {{-- De dónde sale la foto. Importa al decidir una retención: no es lo mismo un
               desperfecto que reportó quien limpiaba que una foto rutinaria de la limpieza. --}}
          <span class="df-origen {{ $foto->origen === 'incidencia' ? 'inc' : '' }}">
            @if($foto->origen === 'incidencia')<i class="ti ti-alert-triangle"></i>@endif
            {{ $foto->tarea_nombre }}
          </span>
          {{-- El importe retenido, visible sin abrir nada: es el dato que se revisa al final. --}}
          <span class="df-retencion" id="retencion-{{ $foto->id }}"
                style="{{ (float) ($foto->retencion_importe ?? 0) > 0 ? '' : 'display:none' }}">
            {{ $fmt($foto->retencion_importe) }}
          </span>
        </div>
      @endforeach
    </div>
  @else
    <p style="font-size:13px;color:#999;margin:0">Sin fotos.</p>
  @endif
</div>

</div>

{{-- Visor. Se navega con las flechas, con el teclado y con la rueda; se cierra pulsando fuera
     de la imagen o con Escape. Los controles paran la propagación para que pulsarlos no cuente
     como "pulsar fuera" y cierre el visor. --}}
<div id="df-lightbox" class="df-modal" onclick="cerrarVisor()">
  <button type="button" id="df-prev" onclick="event.stopPropagation(); mover(-1)"
          title="Anterior (←)" class="df-nav" style="left:16px">‹</button>

  <div onclick="event.stopPropagation()" style="position:relative;display:flex;flex-direction:column;align-items:center;gap:8px">
    <img id="df-lightbox-img" src="" alt="" style="max-width:82vw;max-height:82vh;border-radius:8px">
    {{-- El mismo escudo que en la rejilla: la decision se toma mirando la foto grande, que es
         donde de verdad se ve si sirve para el informe. --}}
    <button type="button" id="df-escudo-visor" class="df-escudo df-escudo-visor"
            onclick="event.stopPropagation(); abrirRetencionFoto(DF_FOTOS[dfIdx].id)"
            {{ $puedeEditar ? '' : 'disabled' }}>
      <i class="fa-solid fa-shield"></i>
    </button>
    <div style="color:#fff;font-size:12px;text-align:center;text-shadow:0 1px 3px rgba(0,0,0,.6)">
      <span id="df-contador"></span>
      <span id="df-tarea" style="opacity:.75"></span>
    </div>
  </div>

  <button type="button" id="df-next" onclick="event.stopPropagation(); mover(1)"
          title="Siguiente (→)" class="df-nav" style="right:16px">›</button>
</div>

{{-- Pop-up de la foto: concepto e importe. Un solo badge con dos sentidos, por decisión expresa,
     así que la nota del importe a cero no es decorativa: sin ella nadie adivinaría cómo llevar
     una foto al informe sin retener nada. --}}
<div id="df-foto-retencion" class="df-modal">
  <div class="df-modal-box">
    <p style="font-weight:500;font-size:15px;margin:0 0 4px">Foto del informe de la estancia</p>
    <p style="font-size:12px;color:#888;margin:0 0 14px">
      Indica qué se retiene y cuánto. <strong>Deja el importe a 0</strong> para llevar la foto al
      informe sin retener nada.
    </p>

    <img id="df-foto-retencion-img" src="" alt=""
         style="width:100%;max-height:200px;object-fit:contain;border-radius:6px;background:#F4F5F6;margin-bottom:12px">

    <label style="font-size:12px;color:#666;display:block;margin-bottom:3px">Concepto</label>
    <input type="text" id="df-foto-concepto" class="df-input" maxlength="255"
           placeholder="Qué se ha encontrado" style="margin-bottom:12px">

    <label style="font-size:12px;color:#666;display:block;margin-bottom:3px">Importe a retener (€)</label>
    <input type="number" id="df-foto-importe" class="df-input" step="0.01" min="0" value="0">

    <p id="df-foto-error" style="font-size:12px;color:#9B1C1C;margin:10px 0 0;display:none"></p>

    <div style="display:flex;gap:8px;justify-content:space-between;margin-top:1rem">
      <button type="button" class="df-btn" id="df-foto-quitar" onclick="quitarFoto()"
              style="border-color:#E3BDBD;color:#9B1C1C">Quitar del informe</button>
      <span style="display:flex;gap:8px">
        <button type="button" class="df-btn" onclick="cerrarFotoRetencion()">Cancelar</button>
        <button type="button" class="df-btn" onclick="guardarFotoRetencion()"
                style="border-color:#B7E0C4;background:#F1FAF4;color:#1B7F3B">Guardar</button>
      </span>
    </div>
  </div>
</div>

{{-- Modal de confirmación: el resumen de lo que se le va a cobrar al huésped, con la foto al
     lado de cada línea. Es la última pantalla antes de que el dinero se mueva. --}}
<div id="df-retener" class="df-modal">
  <div class="df-modal-box" style="max-width:580px">
    <p style="font-weight:500;font-size:15px;margin:0 0 4px">Confirmar retención</p>
    <p style="font-size:12px;color:#888;margin:0 0 14px">
      Fianza de la reserva: <strong>{{ $fmt($reserva->fianza) }}</strong>. La retención no puede superarla.
    </p>

    <table style="width:100%;border-collapse:collapse;font-size:13px">
      <thead>
        <tr style="background:rgba(0,0,0,.02)">
          <th style="text-align:left;padding:6px 8px;font-size:11px;color:#888;font-weight:500;width:64px">Foto</th>
          <th style="text-align:left;padding:6px 8px;font-size:11px;color:#888;font-weight:500">Concepto</th>
          <th style="text-align:right;padding:6px 8px;font-size:11px;color:#888;font-weight:500;width:90px">Importe</th>
        </tr>
      </thead>
      <tbody id="df-retener-lineas"></tbody>
      <tfoot>
        <tr style="border-top:0.5px solid rgba(0,0,0,.12)">
          <td colspan="2" style="padding:8px;text-align:right;font-weight:600">Total a retener</td>
          <td id="df-retener-total" style="padding:8px;text-align:right;font-weight:700;font-variant-numeric:tabular-nums"></td>
        </tr>
      </tfoot>
    </table>

    <p id="df-retener-aviso" style="font-size:12px;color:#9B1C1C;margin:10px 0 0;display:none"></p>

    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:1rem">
      <button type="button" class="df-btn" onclick="cerrarRetener()">Cancelar</button>
      <button type="button" class="df-btn" onclick="confirmarRetener()"
              style="border-color:#E3C9A3;background:#FDF8F1;color:#9A5B00">Confirmar</button>
    </div>
  </div>
</div>

<script>
const CSRF_DF = '{{ csrf_token() }}';
const BASE_DF = '{{ url($project->slug . "/dev-fianzas/" . $reserva->id) }}';

@php
    // El array se arma aqui y no dentro de @json: esa directiva empareja parentesis a mano y un
    // array multilinea con casts la despista, cortando la expresion y generando PHP invalido.
    // Se construye aqui, en un @php, y no dentro de @json(): Blade empareja parentesis de forma
    // ingenua y un array a varias lineas dentro de @json genera PHP invalido. Paso una vez, y
    // view:cache no lo detecta porque compila sin comprobar que el PHP resultante sea valido.
    $dfFotosJs = $fotos->map(fn($f) => [
        'id'       => $f->id,
        'url'      => asset('storage/' . $f->file_foto),
        'tarea'    => $f->tarea_nombre,
        'origen'   => $f->origen ?? 'limpieza',
        'marcada'  => (bool) ($f->informe_estancia ?? 0),
        'concepto' => $f->retencion_concepto,
        'importe'  => $f->retencion_importe !== null ? (float) $f->retencion_importe : 0,
    ])->values();
@endphp
const DF_FOTOS = @json($dfFotosJs);

let dfIdx = 0;

function pintarVisor() {
    const f = DF_FOTOS[dfIdx];
    if (!f) return;
    document.getElementById('df-lightbox-img').src = f.url;
    document.getElementById('df-contador').textContent = (dfIdx + 1) + ' / ' + DF_FOTOS.length;
    document.getElementById('df-tarea').textContent = f.tarea ? ' · ' + f.tarea : '';
    pintarEscudoVisor();
    // Sin ciclo: con una sola foto las dos flechas quedan desactivadas y se ve que no hay más.
    document.getElementById('df-prev').disabled = dfIdx === 0;
    document.getElementById('df-next').disabled = dfIdx === DF_FOTOS.length - 1;
}

function ampliar(i) {
    dfIdx = i;
    pintarVisor();
    document.getElementById('df-lightbox').classList.add('open');
}

function mover(paso) {
    const siguiente = dfIdx + paso;
    if (siguiente < 0 || siguiente >= DF_FOTOS.length) return;
    dfIdx = siguiente;
    pintarVisor();
}

function cerrarVisor() { document.getElementById('df-lightbox').classList.remove('open'); }

document.addEventListener('keydown', function (e) {
    const abierto = document.getElementById('df-lightbox')?.classList.contains('open');
    if (!abierto) return;
    if (e.key === 'ArrowLeft')  { e.preventDefault(); mover(-1); }
    if (e.key === 'ArrowRight') { e.preventDefault(); mover(1); }
    if (e.key === 'Escape')     { cerrarVisor(); }
});
// El escudo abre el pop-up de concepto e importe; el contador y el total de arriba se
// recalculan desde DF_FOTOS cada vez, para que no puedan desviarse del dinero real.
const PUEDE_MARCAR = @json((bool) $puedeEditar);

function tituloEscudo(marcada) {
  if (!PUEDE_MARCAR) {
    return marcada ? 'Se usará en el informe de la estancia' : 'No seleccionada para el informe';
  }
  return marcada
    ? 'Va al informe de la estancia. Pulsa para editar o quitar.'
    : 'Marcar para el informe e indicar retención.';
}

// El escudo del visor no tiene estado propio: lo toma de la foto que se esté mostrando.
function pintarEscudoVisor() {
  const btn = document.getElementById('df-escudo-visor');
  const f   = DF_FOTOS[dfIdx];
  if (!btn || !f) return;
  btn.classList.toggle('on', !!f.marcada);
  btn.title = tituloEscudo(!!f.marcada);
}

// ── El escudo de la foto ────────────────────────────────────────────────────
// Un solo badge con dos sentidos: marcar una retencion implica que la foto va al informe, y para
// llevarla al informe SIN retener se deja el importe a cero. El pop-up lo dice, porque no se
// adivina.
let dfFotoActual = null;

function abrirRetencionFoto(id) {
    if (!PUEDE_MARCAR) return;
    const f = DF_FOTOS.find(x => x.id === id);
    if (!f) return;

    dfFotoActual = id;
    document.getElementById('df-foto-retencion-img').src = f.url;
    document.getElementById('df-foto-concepto').value = f.concepto || '';
    document.getElementById('df-foto-importe').value = f.importe || 0;
    // Quitar solo tiene sentido sobre una foto que ya esta marcada.
    document.getElementById('df-foto-quitar').style.display = f.marcada ? '' : 'none';
    errorFoto('');
    document.getElementById('df-foto-retencion').classList.add('open');
    document.getElementById('df-foto-concepto').focus();
}

function cerrarFotoRetencion() {
    document.getElementById('df-foto-retencion').classList.remove('open');
    dfFotoActual = null;
}

function errorFoto(txt) {
    const p = document.getElementById('df-foto-error');
    p.textContent = txt;
    p.style.display = txt ? '' : 'none';
}

function guardarFotoRetencion() {
    const concepto = document.getElementById('df-foto-concepto').value.trim();
    const importe  = parseFloat(document.getElementById('df-foto-importe').value || '0') || 0;

    // Se valida aqui para no hacer el viaje, y otra vez en el servidor porque esto decide sobre
    // dinero y el navegador no es una autoridad.
    if (importe > 0 && !concepto) { errorFoto('Indica el concepto de la retencion.'); return; }
    enviarFoto({ concepto: concepto, importe: importe });
}

function quitarFoto() { enviarFoto({ quitar: true }); }

async function enviarFoto(payload) {
    const id = dfFotoActual;
    const btn = document.getElementById('escudo-' + id);
    if (!id || !btn || btn.dataset.enviando === '1') return;
    btn.dataset.enviando = '1';

    try {
        const r = await fetch(@json(route('vm.dev-fianza.foto-informe', [$project->slug, '__ID__'])).replace('__ID__', id), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DF, 'Accept': 'application/json' },
            body: JSON.stringify(payload),
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.ok) {
            errorFoto(j.error || (j.errors ? Object.values(j.errors).flat().join(' ') : 'No se ha podido guardar.'));
            return;
        }

        // El estado vive en DF_FOTOS: es lo que lee el visor al cambiar de foto, asi que si no se
        // actualiza aqui, pasar a la siguiente y volver mostraria el escudo como estaba al cargar.
        const f = DF_FOTOS.find(x => x.id === id);
        if (f) { f.marcada = j.marcada; f.concepto = j.concepto; f.importe = j.importe || 0; }

        btn.classList.toggle('on', j.marcada);
        document.getElementById('foto-' + id)?.classList.toggle('marcada', j.marcada);
        btn.title = tituloEscudo(j.marcada);

        const chip = document.getElementById('retencion-' + id);
        if (chip) {
            chip.textContent = eur(j.importe || 0);
            chip.style.display = (j.importe || 0) > 0 ? '' : 'none';
        }

        pintarEscudoVisor();
        recontar();
        cerrarFotoRetencion();
    } finally {
        btn.dataset.enviando = '0';
    }
}

function eur(n) {
    return Number(n).toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20AC';
}

// Recalcula de DF_FOTOS en vez de ir sumando y restando: sumar incrementos acaba desviandose en
// cuanto una peticion falla a medias, y aqui el numero que se desvia es dinero.
function recontar() {
    const marcadas = DF_FOTOS.filter(f => f.marcada);
    const conRet   = DF_FOTOS.filter(f => (f.importe || 0) > 0);
    const total    = conRet.reduce((s, f) => s + Number(f.importe || 0), 0);

    const cont = document.getElementById('df-marcadas');
    if (cont) {
        cont.textContent = marcadas.length;
        document.getElementById('df-marcadas-wrap').style.display = marcadas.length ? '' : 'none';
    }

    const btn = document.getElementById('df-btn-retener');
    if (btn) {
        btn.disabled = conRet.length === 0;
        btn.title = conRet.length ? 'Revisar y confirmar la retencion'
                                  : 'Marca primero el importe a retener en alguna foto';
        document.getElementById('df-btn-retener-n').textContent = conRet.length ? '(' + eur(total) + ')' : '';
    }
}

// ── Confirmar la retencion ──────────────────────────────────────────────────
function abrirRetener() {
    const conRet = DF_FOTOS.filter(f => (f.importe || 0) > 0);
    if (!conRet.length) return;   // el boton esta deshabilitado, pero por si acaso

    const tbody = document.getElementById('df-retener-lineas');
    tbody.innerHTML = conRet.map(f =>
        '<tr style="border-top:0.5px solid rgba(0,0,0,.06)">'
      + '<td style="padding:6px 8px"><img src="' + f.url + '" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:4px;display:block"></td>'
      + '<td style="padding:6px 8px">' + escaparHtml(f.concepto || '') + '</td>'
      + '<td style="padding:6px 8px;text-align:right;font-variant-numeric:tabular-nums">' + eur(f.importe) + '</td>'
      + '</tr>').join('');

    const total = conRet.reduce((s, f) => s + Number(f.importe || 0), 0);
    document.getElementById('df-retener-total').textContent = eur(total);

    // El aviso de que se pasa de la fianza se da ANTES de pulsar Confirmar: el servidor lo
    // rechazaria igual, pero enterarse despues de confirmar es peor experiencia.
    const aviso = document.getElementById('df-retener-aviso');
    const fianza = Number(@json((float) ($reserva->fianza ?? 0)));
    if (fianza > 0 && total > fianza + 0.005) {
        aviso.textContent = 'El total supera la fianza (' + eur(fianza) + '). Ajusta algun importe antes de confirmar.';
        aviso.style.display = '';
    } else {
        aviso.style.display = 'none';
    }

    document.getElementById('df-retener').classList.add('open');
}

function cerrarRetener() { document.getElementById('df-retener').classList.remove('open'); }

function escaparHtml(t) {
    const d = document.createElement('div');
    d.textContent = t;
    return d.innerHTML;
}

async function confirmarRetener() {
    const r = await fetch(BASE_DF + '/retener', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DF, 'Accept': 'application/json' },
        body: JSON.stringify({ canal: document.getElementById('df-canal')?.value }),
    });
    const j = await r.json().catch(() => ({}));
    if (j.error) { alert(j.error); return; }
    if (j.errors) { alert(Object.values(j.errors).flat().join('\n')); return; }
    location.href = '{{ route('vm.dev-fianzas', $project->slug) }}';
}

async function conforme() {
    if (!confirm('¿Aprobar la devolución completa de la fianza?')) return;

    const r = await fetch(BASE_DF + '/conforme', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DF, 'Accept': 'application/json' },
        body: JSON.stringify({ canal: document.getElementById('df-canal')?.value }),
    });
    const j = await r.json().catch(() => ({}));
    if (j.error) { alert(j.error); return; }
    location.href = '{{ route('vm.dev-fianzas', $project->slug) }}';
}

</script>
</x-app-layout>
