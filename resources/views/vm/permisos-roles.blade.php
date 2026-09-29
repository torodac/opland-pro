<x-app-layout :breadcrumb="$breadcrumb" :project="$project">
<style>
  .pr-root{
    color-scheme: light;
    --bg-page:      #f9f9f7;
    --surface:      #ffffff;
    --surface-2:    #f3f2ee;
    --text:         #0b0b0b;
    --text-soft:    #52514e;
    --muted:        #8d8b85;
    --grid:         #e3e2db;
    --ver:          #2a78d6;
    --editar:       #1a8f5c;
    --alerta:       #c2410c;
  }
  @media (prefers-color-scheme: dark) {
    :root:where(:not([data-theme="light"])) .pr-root{
      color-scheme: dark;
      --bg-page: #0d0d0d; --surface: #1a1a19; --surface-2: #232322;
      --text: #ffffff; --text-soft: #c3c2b7; --muted: #8d8b85; --grid: #2e2e2b;
      --ver: #5b9de8; --editar: #34b57d; --alerta: #e97b45;
    }
  }
  :root[data-theme="dark"] .pr-root{
    color-scheme: dark;
    --bg-page: #0d0d0d; --surface: #1a1a19; --surface-2: #232322;
    --text: #ffffff; --text-soft: #c3c2b7; --muted: #8d8b85; --grid: #2e2e2b;
    --ver: #5b9de8; --editar: #34b57d; --alerta: #e97b45;
  }

  .pr-root{ background:var(--bg-page); color:var(--text); padding:16px; font-size:13px; }

  .pr-leyenda{ display:flex; gap:18px; flex-wrap:wrap; align-items:center;
    margin-bottom:14px; color:var(--text-soft); font-size:12.5px; }
  .pr-leyenda span{ display:inline-flex; align-items:center; gap:6px; }

  .pr-scroll{ overflow:auto; max-height:calc(100vh - 220px);
    border:1px solid var(--grid); border-radius:10px; background:var(--surface); }

  table.pr{ border-collapse:separate; border-spacing:0; font-size:12.5px; }

  /* Cabecera y primera columna fijas: con 41 filas y 13 roles, sin esto se pierde el hilo. */
  table.pr thead th{ position:sticky; top:0; z-index:3; background:var(--surface-2);
    border-bottom:1px solid var(--grid); padding:7px 8px; vertical-align:bottom;
    text-align:center; }
  table.pr th.pr-item, table.pr td.pr-item{ position:sticky; left:0; z-index:2;
    background:var(--surface); border-right:1px solid var(--grid);
    text-align:left; min-width:230px; max-width:230px; }
  table.pr thead th.pr-item{ z-index:4; background:var(--surface-2); }

  table.pr td{ border-bottom:1px solid var(--grid); padding:5px 8px; text-align:center; }
  table.pr tbody tr:hover td{ background:var(--surface-2); }
  table.pr tbody tr:hover td.pr-item{ background:var(--surface-2); }

  .pr-rol{ font-weight:600; color:var(--text); white-space:nowrap; }
  .pr-rol-link{ color:inherit; text-decoration:none; }
  .pr-rol-link:hover{ color:var(--ver); text-decoration:underline; }
  .pr-rol-id{ color:var(--muted); font-weight:400; }
  .pr-usuarios{ position:relative; display:inline-flex; align-items:center; gap:4px; margin-top:5px;
    padding:1px 6px; border-radius:99px; background:var(--surface);
    border:1px solid var(--grid); font-size:10.5px; font-weight:600; color:var(--text-soft);
    cursor:help; white-space:nowrap; }
  .pr-usuarios i{ font-size:9px; color:var(--muted); }
  .pr-sin-usuarios{ opacity:.55; font-weight:400; }

  /* Tooltip propio: aparece al instante, sin la espera del nativo, y en lista. */
  .pr-tip{ position:absolute; top:calc(100% + 6px); left:50%; transform:translateX(-50%);
    z-index:30; display:none; min-width:150px; max-width:230px;
    padding:8px 10px; border-radius:8px; border:1px solid var(--grid);
    background:var(--surface); box-shadow:0 6px 18px rgba(0,0,0,.14);
    font-weight:400; text-align:left; white-space:normal; cursor:default; }
  .pr-usuarios:hover .pr-tip{ display:block; }
  .pr-tip strong{ display:block; font-size:10.5px; text-transform:uppercase;
    letter-spacing:.04em; color:var(--muted); margin-bottom:5px; }
  .pr-tip ul{ list-style:none; margin:0; padding:0; }
  .pr-tip li{ font-size:11.5px; line-height:1.5; color:var(--text); }
  .pr-tip li + li{ border-top:1px solid var(--grid); }

  /* En las últimas columnas se abre hacia la izquierda, o lo recorta el scroll horizontal. */
  table.pr thead th:nth-last-child(-n+3) .pr-tip{ left:auto; right:0; transform:none; }
  .pr-todo{ display:block; margin-top:3px; font-size:10px; color:var(--alerta); font-weight:700; }

  .pr-modulo td{ background:var(--surface-2); font-weight:700; font-size:11px;
    text-transform:uppercase; letter-spacing:.05em; color:var(--muted); text-align:left; }

  .pr-label{ font-weight:500; }
  .pr-tabla{ display:block; font-size:10.5px; color:var(--muted); font-family:ui-monospace,monospace; }
  .pr-solo-admin{ margin-left:5px; padding:0 4px; border-radius:3px; font-family:inherit;
    background:var(--surface-2); color:var(--muted); font-size:9.5px; }

  .i-ver    { color:var(--ver); }
  .i-editar { color:var(--editar); }
  .i-alerta { color:var(--alerta); }
  .i-nada   { color:var(--grid); }
  .i-libre  { color:var(--muted); font-size:10px; }
</style>

<div class="pr-root">

  <p class="pr-leyenda">
    <span><i class="fa-solid fa-pen-to-square i-editar"></i> ver y editar</span>
    <span><i class="fa-regular fa-eye i-ver"></i> solo ver</span>
    <span><i class="fa-solid fa-minus i-nada"></i> sin acceso</span>
    <span><i class="fa-solid fa-triangle-exclamation i-alerta"></i> edita sin poder ver</span>
    <span><i class="fa-solid fa-lock-open i-libre"></i> la entrada no tiene permiso asociado</span>
  </p>

  <div class="pr-scroll">
    <table class="pr">
      <thead>
        <tr>
          <th class="pr-item">Entrada del menú</th>
          @foreach($roles as $rol)
            <th>
              <div class="pr-rol">
                <span class="pr-rol-id">{{ $rol->id }}</span>
                {{-- Se abre en pestaña nueva para no perder la posición del scroll en la matriz --}}
                <a href="{{ $rol->url }}" target="_blank" rel="noopener" class="pr-rol-link">{{ $rol->nombre }}</a>
              </div>
              @if($rol->ve_todo || $rol->edita_todo)
                <span class="pr-todo">
                  {{ $rol->ve_todo && $rol->edita_todo ? 've y edita todo' : ($rol->ve_todo ? 've todo' : 'edita todo') }}
                </span>
              @endif
              {{-- Los nombres van al tooltip: en pantalla solo el recuento, porque trece
                   columnas con sus listas de empleados se comían media pantalla de alto. --}}
              {{-- Tooltip propio en vez del nativo: el del navegador tarda casi un segundo en
                   aparecer y solo admite texto plano. Es CSS puro, sin JavaScript. --}}
              <span class="pr-usuarios {{ $rol->usuarios ? '' : 'pr-sin-usuarios' }}">
                <i class="fa-regular fa-user"></i>{{ count($rol->usuarios) }}
                <span class="pr-tip">
                  @if($rol->usuarios)
                    <strong>{{ count($rol->usuarios) }} {{ count($rol->usuarios) === 1 ? 'persona' : 'personas' }}</strong>
                    <ul>
                      @foreach($rol->usuarios as $u)<li>{{ $u }}</li>@endforeach
                    </ul>
                  @else
                    <strong>Nadie tiene este rol</strong>
                  @endif
                </span>
              </span>
            </th>
          @endforeach
        </tr>
      </thead>
      <tbody>
        @php $moduloActual = null; @endphp
        @foreach($filas as $fila)
          @if($fila->modulo !== $moduloActual)
            @php $moduloActual = $fila->modulo; @endphp
            <tr class="pr-modulo">
              <td class="pr-item" colspan="{{ $roles->count() + 1 }}">{{ $moduloActual }}</td>
            </tr>
          @endif
          <tr>
            <td class="pr-item">
              <span class="pr-label">{{ $fila->label }}</span>
              <span class="pr-tabla">
                {{ $fila->tabla ?? 'sin tabla' }}@if($fila->is_virtual) · virtual @endif
                @if($fila->admin_only)<span class="pr-solo-admin">no sale en el menú</span>@endif
              </span>
            </td>
            @foreach($roles as $rol)
              @php $c = $fila->celdas[$rol->id] ?? 'nada'; @endphp
              <td>
                @switch($c)
                  @case('editar')
                    <i class="fa-solid fa-pen-to-square i-editar" title="Ve y edita"></i>
                    @break
                  @case('ver')
                    <i class="fa-regular fa-eye i-ver" title="Solo ve"></i>
                    @break
                  @case('edita_sin_ver')
                    <i class="fa-solid fa-triangle-exclamation i-alerta"
                       title="Tiene permiso de edición pero no de vista: la pantalla no le aparece"></i>
                    @break
                  @case('sin_permiso')
                    <i class="fa-solid fa-lock-open i-libre" title="Esta entrada no tiene tabla asociada: la ve todo el mundo"></i>
                    @break
                  @default
                    <i class="fa-solid fa-minus i-nada" title="Sin acceso"></i>
                @endswitch
              </td>
            @endforeach
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <p style="margin-top:12px;color:var(--muted);font-size:12px;max-width:760px">
    Una lista de permisos vacía significa <strong>sin restricción</strong>, no «sin acceso»: por eso
    un rol puede aparecer con todo concedido sin tener nada marcado en su ficha. Se avisa bajo el
    nombre del rol cuando es el caso.
  </p>

</div>
</x-app-layout>
