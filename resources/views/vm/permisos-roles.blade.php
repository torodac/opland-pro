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
    border-bottom:1px solid var(--grid); padding:7px 8px; vertical-align:bottom; }
  table.pr th.pr-item, table.pr td.pr-item{ position:sticky; left:0; z-index:2;
    background:var(--surface); border-right:1px solid var(--grid);
    text-align:left; min-width:230px; max-width:230px; }
  table.pr thead th.pr-item{ z-index:4; background:var(--surface-2); }

  table.pr td{ border-bottom:1px solid var(--grid); padding:5px 8px; text-align:center; }
  table.pr tbody tr:hover td{ background:var(--surface-2); }
  table.pr tbody tr:hover td.pr-item{ background:var(--surface-2); }

  .pr-rol{ font-weight:600; color:var(--text); white-space:nowrap; }
  .pr-rol-id{ color:var(--muted); font-weight:400; }
  .pr-usuarios{ margin-top:4px; font-size:10.5px; line-height:1.35; color:var(--muted);
    font-weight:400; max-width:130px; white-space:normal; }
  .pr-sin-usuarios{ font-style:italic; }
  .pr-todo{ display:block; margin-top:3px; font-size:10px; color:var(--alerta); font-weight:700; }

  .pr-modulo td{ background:var(--surface-2); font-weight:700; font-size:11px;
    text-transform:uppercase; letter-spacing:.05em; color:var(--muted); text-align:left; }

  .pr-label{ font-weight:500; }
  .pr-tabla{ display:block; font-size:10.5px; color:var(--muted); font-family:ui-monospace,monospace; }

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
              <div class="pr-rol"><span class="pr-rol-id">{{ $rol->id }}</span> {{ $rol->nombre }}</div>
              @if($rol->ve_todo || $rol->edita_todo)
                <span class="pr-todo">
                  {{ $rol->ve_todo && $rol->edita_todo ? 've y edita todo' : ($rol->ve_todo ? 've todo' : 'edita todo') }}
                </span>
              @endif
              <div class="pr-usuarios">
                @if($rol->usuarios)
                  {{ implode(', ', $rol->usuarios) }}
                @else
                  <span class="pr-sin-usuarios">sin usuarios</span>
                @endif
              </div>
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
