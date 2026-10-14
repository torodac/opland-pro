<x-app-layout :project="$project" :breadcrumb="[['label'=>'Inicio','url'=>'']]">

@include('partials.role-badge', ['project' => $project, 'texto' => 'Algunos bloques de este dashboard (Reservas, RRHH, Ausencias sin conciliar, Limpieza/Mantenimiento sin imputar, Informes mensuales pendientes de firma) solo son visibles para ciertos roles (Dirección general, Dirección de Operaciones, Coordinador limpieza, Coordinador mantenimiento, Director RRHH). Los ves todos por ser admin.'])

<style>
.db-grid    { display:grid; grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); gap:12px; margin-bottom:12px; }
.db-card    { background:#fff; border:0.5px solid rgba(0,0,0,.08); border-radius:12px; padding:1rem 1.25rem; }
.dark .db-card { background:#1a1a1a; border-color:rgba(255,255,255,.08); }
.db-title   { font-size:13px; font-weight:500; color:#888; margin:0 0 10px; display:flex; align-items:center; gap:6px; }
.db-title i { font-size:15px; }
/* Bloques plegables. El título entero es el mando: un objetivo de clic grande, y la flecha sola
   seria diminuta. El margin-bottom se quita al plegar para que la tarjeta cerrada no deje un
   hueco debajo del titulo. */
.db-title.db-plegable { cursor:pointer; user-select:none; }
.db-card.db-cerrado .db-title { margin-bottom:0; }
/* Chip y flecha van juntos en un contenedor con margin-left:auto, en vez de repartir ese auto
   entre los dos segun haya chip o no: asi el margen lo pone un solo sitio. */
.db-mando   { margin-left:auto; display:flex; align-items:center; gap:6px; }
.db-chip    { background:#EFF1F3; color:#4B5563; font-size:11px; font-weight:600;
              line-height:1; padding:3px 8px; border-radius:999px; font-variant-numeric:tabular-nums; }
.dark .db-chip { background:#2a2a2a; color:#9ca3af; }
.db-flecha  { color:#9ca3af; font-size:13px; line-height:1; transition:transform .15s; }
.db-card.db-cerrado .db-flecha { transform:rotate(-90deg); }
.db-count   { font-size:32px; font-weight:500; line-height:1; margin:0 0 12px; }
.db-table   { width:100%; border-collapse:collapse; font-size:12px; }
.db-table th{ text-align:left; padding:4px 6px; color:#aaa; font-weight:500; border-bottom:0.5px solid rgba(0,0,0,.06); }
.db-table td{ padding:5px 6px; border-bottom:0.5px solid rgba(0,0,0,.04); }
.dark .db-table th { border-color:rgba(255,255,255,.07); }
.dark .db-table td { border-color:rgba(255,255,255,.04); }
.db-table tr:last-child td { border-bottom:none; }
.badge-sm   { font-size:10px; padding:1px 6px; border-radius:4px; white-space:nowrap; }
/* Visé de "dar por bueno". Va junto a la casuística, no con los botones de navegación de la
   derecha: resuelve la fila en vez de llevarte a otro sitio, y mezclarlo con ellos descuadraba
   esa columna. Círculo naranja con el visé en blanco, para que se distinga de un vistazo de los
   badges rectangulares de navegación: aquello lleva a otro sitio, esto cierra la incidencia.
   width = height y el line-height centran el símbolo sin depender de la fuente. */
.db-vise    { border:0; cursor:pointer; background:#F97316; color:#fff;
              width:18px; height:18px; border-radius:50%; padding:0;
              font-size:11px; line-height:18px; text-align:center;
              display:inline-block; margin-left:8px; vertical-align:middle; }
.db-vise:hover { background:#C2410C; }
.db-vise[disabled] { background:#E5E7EB; color:#9CA3AF; cursor:default; }
/* Listados largos: 10 filas visibles y scroll para el resto (la altura exacta la fija el JS
   del final, porque hay filas de doble altura -- las de badges apilados). El max-height de
   aquí es el respaldo por si ese JS no llega a ejecutarse. */
.db-scroll  { max-height:268px; overflow-y:auto; }
.db-scroll .db-table thead th { position:sticky; top:0; background:#fff; z-index:1; }
.dark .db-scroll .db-table thead th { background:#1a1a1a; }
.empty      { font-size:12px; color:#bbb; text-align:center; padding:1rem 0; }
.diff-pos   { color:#28a745; font-weight:500; }
.diff-neg   { color:#dc3545; font-weight:500; }

[title] { cursor:default; }

/* Fichaje widget */
.fwrap{display:flex;flex-direction:column;gap:6px;background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:10px 14px;min-width:260px}
.dark .fwrap{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.f-btn{width:36px;height:36px;border-radius:50%;border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:opacity .15s}
.f-btn:hover{opacity:.85}
.f-btn:disabled{opacity:.4;cursor:not-allowed}
.f-btn.play{background:#185FA5;color:#fff}
.f-btn.pause{background:#E6F1FB;color:#185FA5;border:0.5px solid #B5D4F4}
.f-btn.stop{background:#FCEBEB;color:#A32D2D;border:0.5px solid #F7C1C1}
.f-time{font-size:22px;font-weight:500;color:#111;font-variant-numeric:tabular-nums;line-height:1}
.dark .f-time{color:#eee}
.f-label{font-size:11px;color:#aaa;}
.f-done{font-size:13px;color:#27500A;font-weight:500}
</style>

<div style="padding:0 0 3rem;">

  {{-- Saludo + widget fichaje --}}
  <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:1.5rem;flex-wrap:wrap;">
    <div>
      <p style="font-size:22px;font-weight:500;margin:0;">Hola, {{ auth()->user()->name ?? 'bienvenido' }} 👋</p>
      <p style="font-size:13px;color:#888;margin:4px 0 0;">{{ now()->translatedFormat('l, j \d\e F \d\e Y') }}</p>
    </div>
    @if ($vmUsuario)
    <div class="fwrap" id="fw">
      <div style="display:flex;align-items:center;gap:10px;">
        <div id="fw-btns" style="display:flex;gap:6px;align-items:center;"></div>
        <div class="f-time" id="fw-time">0:00</div>
        <div id="fw-badges" style="display:flex;flex-direction:column;gap:3px;align-items:flex-end;margin-left:auto;padding-right:2px;"></div>
      </div>
      <div id="fw-label" class="f-label"></div>
    </div>
    @endif
  </div>

  {{-- Recordatorios SSCC (asignados a mí, mi rol o mi departamento) --}}
  @if($vmUsuario)
  <div class="db-card" style="margin-bottom:12px;">
    <p class="db-title"><i class="ti ti-list-check"></i> Mis tareas <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas SSCC asignadas a ti, a tu rol o a tu departamento. No repercuten en el informe mensual.</span></span></p>
    @if($recordatoriosSscc->isEmpty())
      <p class="db-empty">Sin tareas pendientes.</p>
    @else
    <table class="db-table" id="tbl-recordatorios-sscc">
      <thead><tr><th>Tarea</th><th>Planificada</th><th></th></tr></thead>
      <tbody>
        @foreach($recordatoriosSscc as $t)
        <tr data-id="{{ $t->id }}">
          <td style="font-weight:500;">{{ $t->nombre }}</td>
          <td style="color:#888;">{{ $t->fecha_planificada ? \Carbon\Carbon::parse($t->fecha_planificada)->translatedFormat('d M') : '—' }}</td>
          <td style="white-space:nowrap;">
            <a href="{{ route('ficha', [$project->slug, 'tareas_sscc', $t->id]) }}" target="_blank" rel="noopener"
               class="badge-sm" style="background:#E6F1FB;color:#0C447C;text-decoration:none;padding:3px 8px;border-radius:4px;display:inline-block;">
              Ver
            </a>
            <button class="badge-sm" style="background:#EAF3DE;color:#27500A;border:none;cursor:pointer;padding:3px 8px;border-radius:4px;"
                    onclick="marcarSsccHecho(this, {{ $t->id }})">
              Hecho
            </button>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
  @endif

  {{-- Fila 1: contadores hoy -----------------------------------------------}}
  @if($verReservas)
  <div class="db-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:12px;">
    <div class="db-card">
      <p class="db-title"><i class="ti ti-wash"></i> Limpieza completada sin imputar <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de limpieza marcadas como Completada que no tienen ninguna imputación de tiempo registrada.</span></span></p>
      <p class="db-count" style="color:#854F0B;">{{ $tareasLimpieza->count() }}</p>
    </div>
    <div class="db-card">
      <p class="db-title"><i class="ti ti-tool"></i> Mantenimiento completado sin imputar <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de mantenimiento marcadas como Completada que no tienen ninguna imputación de tiempo registrada.</span></span></p>
      <p class="db-count" style="color:#854F0B;">{{ $tareasMantPisc->count() }}</p>
    </div>
  </div>
  @endif

  {{-- Flujo semanal y carga de limpieza (navegable) ---------------------------}}
  @if($verReservas)
  <div style="margin-bottom:12px;">
    @include('partials.vm-carga-semanal')
  </div>
  @endif

  {{-- Fila 3: alertas RRHH -------------------------------------------------}}
  <div class="db-grid">

    {{-- Próximas ausencias del usuario --}}
    @if($vmUsuario)
    <div class="db-card">
      <p class="db-title"><i class="ti ti-calendar-user"></i> Mis próximas ausencias <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Ausencias registradas para tu usuario con fecha de fin igual o posterior a hoy.</span></span></p>
      @if($proximasAusencias->isEmpty())
        <p class="empty">No hay ausencias próximas registradas.</p>
      @else
      <table class="db-table">
        <thead><tr><th>Tipo</th><th>Desde</th><th>Hasta</th><th>Días</th><th>Comentario</th></tr></thead>
        <tbody>
          @foreach($proximasAusencias as $a)
          @php
            $dias = \Carbon\Carbon::parse($a->fecha_inicio)->diffInDays(\Carbon\Carbon::parse($a->fecha_fin)) + 1;
            $tipoCol = (function($tipo) {
              $map = [
                'Vacaciones'      => '#e8b800',
                'Baja'            => '#7b3f8c',
                'Compensación'    => '#e83e8c',
                'Revisar'         => '#fd7e14',
                'Asuntos propios' => '#34c163',
                'Absentismo'      => '#dc3545',
              ];
              if (isset($map[$tipo])) return $map[$tipo];
              $n = mb_strtolower($tipo);
              if (str_starts_with($n, 'comp')) return '#e83e8c';
              if (str_contains($n, 'vacac'))   return '#e8b800';
              return '#6b7280';
            })($a->tipo);
            $col = ['bg' => $tipoCol . '22', 'tx' => $tipoCol];
          @endphp
          <tr>
            <td><span style="display:inline-block;padding:1px 7px;border-radius:10px;font-size:11px;font-weight:500;background:{{ $col['bg'] }};color:{{ $col['tx'] }}">{{ $a->tipo }}</span></td>
            <td>{{ \Carbon\Carbon::parse($a->fecha_inicio)->isoFormat('D MMM YYYY') }}</td>
            <td>{{ \Carbon\Carbon::parse($a->fecha_fin)->isoFormat('D MMM YYYY') }}</td>
            <td style="text-align:center">{{ $dias }}</td>
            <td style="color:#9ca3af;font-size:11px">{{ $a->comentario ?? '—' }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>
    @endif

    {{-- Conciliaciones ausencias --}}
    @if($verAusenciasSin)
    <div class="db-card">
      <p class="db-title"><i class="ti ti-calendar-exclamation"></i> Ausencias en Horario no registradas por RRHH <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Muestra horarios de tipo distinto a turno y descanso (vacaciones, baja, comp_festivo, comp_horas, asuntos, absentismo) en fechas pasadas para los que no existe ninguna ausencia registrada que cubra ese día para ese usuario. En otras palabras: el horario dice que el empleado estaba de vacaciones/baja/etc., pero no hay una ausencia registrada que lo respalde.</span></span></p>
      @if($conciliaciones->isEmpty())
        <p class="empty">Sin incidencias</p>
      @else
      <table class="db-table" id="tbl-conciliaciones">
        <thead><tr><th>Empleado</th><th>Fecha</th><th>Tipo</th><th></th></tr></thead>
        <tbody>
          @foreach($conciliaciones as $c)
          @php $lunes = \Carbon\Carbon::parse($c->fecha)->startOfWeek()->toDateString(); @endphp
          <tr data-id-usuario="{{ $c->id_usuario }}" data-fecha="{{ $c->fecha }}" data-tipo="{{ $c->tipo }}">
            <td style="font-weight:500;">{{ $c->usuario }}</td>
            <td>
              <a target="_blank" rel="noopener" href="{{ route('horario', $project->slug) }}?semana={{ $lunes }}"
                 style="color:#185FA5;text-decoration:none;font-size:12px;">
                {{ \Carbon\Carbon::parse($c->fecha)->translatedFormat('d M Y') }}
              </a>
            </td>
            <td><span class="badge-sm" style="background:#FEF3C7;color:#92400E;">{{ $c->tipo }}</span></td>
            <td>
              <button class="badge-sm btn-validar" style="background:#EAF3DE;color:#27500A;border:none;cursor:pointer;padding:3px 8px;border-radius:4px;" onclick="validarConciliacion(this)">
                Validar
              </button>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>

    {{-- Conflictos de ausencias --}}
    <div class="db-card">
      <p class="db-title"><i class="ti ti-calendar-x"></i> Conflictos de ausencias <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Días en los que un mismo empleado tiene dos o más ausencias registradas a la vez (por ejemplo, una baja dentro de una semana de vacaciones). Pulsa cada badge para abrir la ausencia correspondiente.</span></span></p>
      @if($conflictosAusencias->isEmpty())
        <p class="empty">Sin conflictos</p>
      @else
      <table class="db-table">
        <thead><tr><th>Empleado</th><th>Fecha</th><th>Ausencias</th></tr></thead>
        <tbody>
          @foreach($conflictosAusencias as $c)
          @php
            $c = (object) $c;
            // Mismos colores que el resto de la app para cada tipo de ausencia (ver horario.blade.php)
            $colorAus = function (string $tipo): array {
                $t = mb_strtolower($tipo);
                if (str_starts_with($t, 'comp'))     return ['#FCE7F3', '#9D174D'];
                if (str_contains($t, 'vacac'))       return ['#FEF3C7', '#92400E'];
                if (str_contains($t, 'baja'))        return ['#EDE9FE', '#5B21B6'];
                if (str_contains($t, 'asunto'))      return ['#D1FAE5', '#065F46'];
                if (str_contains($t, 'absent'))      return ['#FEE2E2', '#991B1B'];
                return ['#FFF3CD', '#856404'];
            };
          @endphp
          <tr>
            <td>
              <a target="_blank" rel="noopener" href="{{ route('vm.usuario', [$project->slug, $c->id_usuario]) }}" style="color:#185FA5;text-decoration:none;font-weight:500;">{{ $c->usuario }}</a>
            </td>
            <td style="white-space:nowrap;font-size:12px;">{{ \Carbon\Carbon::parse($c->fecha)->translatedFormat('d M Y') }}</td>
            <td>
              <div style="display:flex;flex-direction:column;gap:3px;">
                @foreach($c->ausencias as $aus)
                @php [$bg, $col] = $colorAus($aus['tipo']); @endphp
                <a target="_blank" rel="noopener" href="{{ route('ficha', [$project->slug, 'ausencias', $aus['id']]) }}" style="text-decoration:none;">
                  <span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.72rem;font-weight:600;background:{{ $bg }};color:{{ $col }};">{{ $aus['tipo'] }}</span>
                </a>
                @endforeach
              </div>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>

    @endif
  </div>

  {{-- Incidencias de ausencias en Horario: las revisa RRHH, no Operaciones. Es la ausencia
       registrada contra el cuadrante, no el fichaje contra el cuadrante. --}}
  @if($verAusenciasSin)
  <div class="db-card" style="margin-bottom:12px;">
    <p class="db-title"><i class="ti ti-calendar-question"></i> Incidencias de ausencias en Horario <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Días en los que el problema está en la ausencia. Los revisa RRHH. Dos casos:<br><br>&bull; <b>Fichaje en &lt;ausencia&gt;</b> — fichó un día que tiene una ausencia registrada (vacaciones, baja...). O el fichaje sobra, o la ausencia no debería cubrir ese día.<br>&bull; <b>Horario dice X y la ausencia es Y</b> — el cuadrante y la ausencia registrada se contradicen. Incluye el caso de un <b>Turno o un Descanso planificado sobre un día de ausencia</b>, que en el cuadrante parece un día normal y es el más difícil de ver a ojo.<br><br>Se muestran también los días que aún no han llegado, separados arriba: esos todavía se pueden evitar.<br><br>No es lo mismo que "Ausencias en Horario no registradas por RRHH": allí no existe ninguna ausencia detrás del horario; aquí existe, pero no coincide. Y solo se revisan los departamentos que aparecen en el planificador.<br><br>Lo que no cuadra entre el fichaje y el turno o el descanso va al bloque "Incidencias de fichajes con turnos y descansos del Horario", que resuelve Operaciones.</span></span></p>
    @if($incidenciasAusencias->isEmpty())
      <p class="empty">Sin incidencias</p>
    @else
    <table class="db-table">
      <thead><tr><th>Empleado</th><th>Fecha</th><th>Casuística</th><th></th></tr></thead>
      <tbody>
        @php $yaPasadasPintado = false; @endphp
        @foreach($incidenciasAusencias as $a)
        @php
          $a = (object) $a;
          $casos = [];
          if ($a->fichaje_id) {
              foreach ($a->ausencias as $aus) $casos[] = 'Fichaje en ' . $aus['tipo'];
          }
          if ($a->horario_distinto) {
              $casos[] = 'Horario dice ' . $a->horario_distinto['horario']
                  . ' y la ausencia es ' . $a->horario_distinto['ausencia'];
          }
          $lunes = \Carbon\Carbon::parse($a->fecha)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        @endphp
        {{-- Separador entre lo que aun se puede evitar y lo que ya solo se puede corregir. Se
             pinta una sola vez, al cruzar de las proximas a las pasadas. --}}
        @if(!$a->futuro && !$yaPasadasPintado)
        @php $yaPasadasPintado = true; @endphp
        <tr class="db-sep"><td colspan="4" style="padding:6px 8px;font-size:11px;color:#9ca3af;background:rgba(0,0,0,.015);">Ya pasados</td></tr>
        @endif
        <tr @if($a->fichaje_id) data-fid="{{ $a->fichaje_id }}" @endif>
          <td>
            <a target="_blank" rel="noopener" href="{{ route('vm.usuario', [$project->slug, $a->id_usuario]) }}" style="color:#185FA5;text-decoration:none;font-weight:500;">{{ $a->usuario }}</a>
          </td>
          <td style="white-space:nowrap;font-size:12px;">
            {{ \Carbon\Carbon::parse($a->fecha)->translatedFormat('d M Y') }}
            @if($a->futuro)
              <span class="badge-sm" style="background:#EFF6FF;color:#1E40AF;padding:1px 6px;margin-left:4px;">próximo</span>
            @endif
          </td>
          {{-- El visé va pegado a la casuística, no con los botones de navegación: es la acción
               que RESUELVE la fila, no otro sitio al que ir. --}}
          <td style="color:#6b7280;">
            {{ implode(', ', $casos) }}
            @if($a->fichaje_id)
            <button class="db-vise"
                    title="Dar el fichaje por bueno: deja de aparecer como incidencia"
                    onclick="validarFichaje(this, {{ $a->fichaje_id }})">&check;</button>
            @endif
          </td>
          <td style="white-space:nowrap;text-align:right;">
            @if($a->fichaje_id)
            <a target="_blank" rel="noopener" href="{{ route('vm.fichaje_form', [$project->slug, $a->fichaje_id]) }}"
               class="badge-sm" style="background:#EAF3DE;color:#27500A;text-decoration:none;padding:3px 8px;"
               title="Abrir el fichaje de ese día">Fichaje</a>
            @endif
            <a target="_blank" rel="noopener" href="{{ route('horario', $project->slug) }}?semana={{ $lunes }}"
               class="badge-sm" style="background:#EFF6FF;color:#1E40AF;text-decoration:none;padding:3px 8px;"
               title="Ver el horario de esa semana">Horario</a>
            @foreach($a->ausencias as $aus)
            <a target="_blank" rel="noopener" href="{{ route('ficha', [$project->slug, 'ausencias', $aus['id']]) }}"
               class="badge-sm" style="background:#FEF3C7;color:#92400E;text-decoration:none;padding:3px 8px;"
               title="Abrir la ausencia de ese día ({{ $aus['tipo'] }})">Ausencia</a>
            @endforeach
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
  @endif

  @if($verIncidenciasFichaje)
  {{-- Incidencias de fichaje contra el cuadrante: las corrige Operaciones. Las tres casuísticas
       son excluyentes entre sí (o hay fichaje o no lo hay), así que ninguna fila se repite.
       Va a ancho completo, fuera del db-grid: con cuatro columnas (y la de acciones con hasta
       tres botones) a media página se parten las líneas. --}}
  <div class="db-card" style="margin-bottom:12px;">
      <p class="db-title"><i class="ti ti-alert-triangle"></i> Incidencias de fichajes con turnos y descansos del Horario <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Días pasados en los que el fichaje no cuadra con el turno o el descanso del cuadrante. Los corrige el área de Operaciones. Dos casos:<br><br>&bull; <b>Turno sin fichaje</b> — tenía turno en el cuadrante y no hay ningún fichaje suyo ese día.<br>&bull; <b>Fichaje en descanso</b> — fichó un día que su cuadrante marca como descanso.<br><br>Un mismo día puede acumular los dos. Solo se reclaman a los departamentos que aparecen en el planificador de horarios.<br><br>Todo lo que tiene que ver con ausencias — fichar un día de vacaciones o de baja, o que el cuadrante y la ausencia se contradigan — va al bloque "Incidencias de ausencias en Horario", que revisa RRHH.</span></span></p>
      @if($incidenciasFichaje->isEmpty())
        <p class="empty">Sin incidencias</p>
      @else
      <table class="db-table">
        <thead>
          <tr>
            <th>Empleado</th>
            <th>Fecha</th>
            <th>Casuística</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($incidenciasFichaje as $c)
          @php
            $c = (object) $c;
            $horarioUrl = route('horario', $project->slug) . '?semana='
                . \Carbon\Carbon::parse($c->fecha)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
            // Sin fichaje_id la incidencia es "turno sin fichar", y el botón crea el fichaje en
            // vez de abrirlo. El alta solo se ofrece si de verdad se va a poder guardar: fuera del
            // límite de fecha, store() lo rechazaría (ver VmFichajePermisos).
            $puedeCrearFichaje = $puedeFicharSinLimite || $c->fecha >= $fechaMinimaFichaje;
            // Casuística en texto llano. Los dos casos son excluyentes entre sí (o hay fichaje o
            // no lo hay), pero se enumeran igual por si en el futuro deja de serlo.
            $casuisticas = [];
            if ($c->sin_fichaje) $casuisticas[] = 'Turno sin fichaje';
            if ($c->descanso)    $casuisticas[] = 'Fichaje en descanso';
          @endphp
          {{-- data-fid: validar quita la fila de los dos bloques a la vez, porque un mismo
               fichaje puede estar listado en los dos. --}}
          <tr @if($c->fichaje_id) data-fid="{{ $c->fichaje_id }}" @endif>
            <td>
              <a target="_blank" rel="noopener" href="{{ route('vm.usuario', [$project->slug, $c->id_usuario]) }}" style="color:#185FA5;text-decoration:none;font-weight:500;">{{ $c->usuario }}</a>
            </td>
            <td style="white-space:nowrap;font-size:12px;">{{ \Carbon\Carbon::parse($c->fecha)->translatedFormat('d M Y') }}</td>
            {{-- El visé va pegado a la casuística, no con los botones de navegación: es la
                 accion que RESUELVE la fila, no otro sitio al que ir. --}}
            <td style="color:#6b7280;">
              {{ implode(', ', $casuisticas) }}
              @if($c->fichaje_id)
              <button class="db-vise"
                      title="Dar el fichaje por bueno: deja de aparecer como incidencia"
                      onclick="validarFichaje(this, {{ $c->fichaje_id }})">&check;</button>
              @endif
            </td>
            <td style="white-space:nowrap;text-align:right;">
              @if($c->fichaje_id)
              <a target="_blank" rel="noopener" href="{{ route('vm.fichaje_form', [$project->slug, $c->fichaje_id]) }}"
                 class="badge-sm" style="background:#EAF3DE;color:#27500A;text-decoration:none;padding:3px 8px;"
                 title="Abrir el fichaje de ese día">Fichaje</a>
              {{-- Crear fichaje solo se ofrece cuando la incidencia es un turno sin fichar: en un
                   conflicto de horario contra ausencia, dar de alta un fichaje no arregla nada. --}}
              @elseif($c->sin_fichaje && $puedeCrearFichaje)
              <button class="badge-sm" style="background:#EAF3DE;color:#27500A;border:none;cursor:pointer;padding:3px 8px;"
                      title="Crear el fichaje de ese día"
                      onclick="abrirFichajeNuevo({ usuario: {{ $c->id_usuario }}, fecha: '{{ $c->fecha }}', onGuardado: () => this.closest('tr').remove() })">Fichaje</button>
              @endif
              <a target="_blank" rel="noopener" href="{{ $horarioUrl }}"
                 class="badge-sm" style="background:#EFF6FF;color:#1E40AF;text-decoration:none;padding:3px 8px;"
                 title="Ver el horario de esa semana">Horario</a>
              {{-- Con la casuística en texto llano, este botón es la única vía a la ausencia que
                   provoca el conflicto: si ese día ya tiene una, la abre en vez de crear otra. --}}
              {{-- Los días de este bloque no tienen ausencia por definición (si la tuvieran
                   estarían en el de RRHH), así que aquí el botón solo puede ofrecer crearla --}}
              @if($verAusenciasSin)
              <a target="_blank" rel="noopener" href="{{ route('vm.ausencias_form', $project->slug) }}?nueva=1&usuario={{ $c->id_usuario }}&fecha={{ $c->fecha }}"
                 class="badge-sm" style="background:#FEF3C7;color:#92400E;text-decoration:none;padding:3px 8px;"
                 title="Registrar una ausencia ese día">Ausencia</a>
              @endif
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
  </div>

  {{-- Fichaje vs imputaciones: sin RRHH, que no gestiona la imputación por tarea.
       A ancho completo, igual que "Incidencias de fichaje": son seis columnas y a media página
       se parten. --}}
  @if($verFichajeVsImput)
  <div class="db-card" style="margin-bottom:12px;">
      <p class="db-title"><i class="ti ti-scale"></i> Fichaje vs imputaciones (diff &gt; 30 min) <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Fichajes cuya duración real difiere en más de 30 minutos respecto al total de imputaciones registradas ese día para ese usuario.</span></span></p>
      @if($desviaciones->isEmpty())
        <p class="empty">Sin desviaciones</p>
      @else
      <table class="db-table" id="tbl-desviaciones">
        <thead>
          <tr>
            <th>Empleado</th>
            <th>Fecha</th>
            <th style="text-align:right;">Fichaje</th>
            <th style="text-align:right;">Imputado</th>
            <th style="text-align:right;">Diff</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          @foreach($desviaciones as $d)
          @php
            $fh  = intdiv($d->fichaje_min, 60) . 'h ' . str_pad($d->fichaje_min % 60, 2, '0', STR_PAD_LEFT) . 'm';
            $ih  = intdiv($d->imputado_min, 60) . 'h ' . str_pad($d->imputado_min % 60, 2, '0', STR_PAD_LEFT) . 'm';
            $dh  = intdiv($d->diferencia_min, 60) . 'h ' . str_pad($d->diferencia_min % 60, 2, '0', STR_PAD_LEFT) . 'm';
            $pos = $d->fichaje_min > $d->imputado_min;
            $fichajeUrl = route('vm.fichaje_form', [$project->slug, $d->fichaje_id]);
          @endphp
          <tr data-fid="{{ $d->fichaje_id }}">
            <td style="font-weight:500;">{{ $d->usuario }}</td>
            <td>
              <a target="_blank" rel="noopener" href="{{ $fichajeUrl }}" style="color:#185FA5;text-decoration:none;font-size:12px;">
                {{ \Carbon\Carbon::parse($d->fecha)->translatedFormat('d M Y') }}
              </a>
            </td>
            <td style="text-align:right;">{{ $fh }}</td>
            <td style="text-align:right;">{{ $ih }}</td>
            <td style="text-align:right;" class="{{ $pos ? 'diff-pos' : 'diff-neg' }}">{{ $pos ? '+' : '-' }}{{ $dh }}</td>
            <td>
              <button class="badge-sm" style="background:#EAF3DE;color:#27500A;border:none;cursor:pointer;padding:3px 8px;border-radius:4px;"
                      onclick="validarFichaje(this, {{ $d->fichaje_id }})">
                Validar
              </button>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
  </div>
  @endif

  @if($verLimpSinImp)
  {{-- Tareas limpieza completadas sin imputar, también a ancho completo --}}
  <div class="db-card" style="margin-bottom:12px;">
      <p class="db-title"><i class="ti ti-wash"></i> Limpieza completada sin imputar <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de limpieza marcadas como Completada que no tienen ninguna imputación registrada.</span></span></p>
      @if($tareasLimpieza->isEmpty())
        <p class="empty">Sin pendientes</p>
      @else
      <table class="db-table">
        <thead><tr><th>Tarea</th><th>Responsables</th><th>Propiedad</th><th>Planificada</th><th></th></tr></thead>
        <tbody>
          @foreach($tareasLimpieza as $t)
          <tr>
            <td><a target="_blank" rel="noopener" href="{{ route('vm.tarea', [$project->slug, 'limpieza', $t->id]) }}" style="color:#111827;font-weight:600;text-decoration:none;">{{ $t->nombre }}</a></td>
            <td>
              @forelse($t->responsables as $r)
                <span style="display:inline-block;background:#f3f4f6;color:#374151;font-size:11px;font-weight:500;padding:2px 8px;border-radius:999px;margin:1px 2px 1px 0;white-space:nowrap;">{{ $r }}</span>
              @empty
                <span style="color:#c1c5cb;">—</span>
              @endforelse
            </td>
            <td style="color:#888;">{{ $t->propiedad }}</td>
            <td style="color:#888;">{{ \Carbon\Carbon::parse($t->fecha_planificada)->translatedFormat('d M') }}</td>
            <td>
              <button class="badge-sm" style="background:#EAF3DE;color:#27500A;border:none;cursor:pointer;padding:3px 8px;border-radius:4px;"
                      onclick="validarTarea(this, {{ $t->id }}, 'limpieza')">
                Validar
              </button>
            </td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
  </div>
  @endif
  @endif

  @if($verMantSinImp)
  {{-- Tareas mantenimiento completadas sin imputar --}}
  <div class="db-card" style="margin-bottom:12px;">
    <p class="db-title"><i class="ti ti-tool"></i> Mantenimiento completado sin imputar <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de mantenimiento marcadas como Completada que no tienen ninguna imputación registrada.</span></span></p>
    @if($tareasMantPisc->isEmpty())
      <p class="empty">Sin pendientes</p>
    @else
    <table class="db-table">
      <thead><tr><th>Tarea</th><th>Responsables</th><th>Propiedad</th><th>Planificada</th><th></th></tr></thead>
      <tbody>
        @foreach($tareasMantPisc as $t)
        <tr>
          <td><a target="_blank" rel="noopener" href="{{ route('vm.tarea', [$project->slug, 'mantenimiento', $t->id]) }}" style="color:#111827;font-weight:600;text-decoration:none;">{{ $t->nombre }}</a></td>
          <td>
            @forelse($t->responsables as $r)
              <span style="display:inline-block;background:#f3f4f6;color:#374151;font-size:11px;font-weight:500;padding:2px 8px;border-radius:999px;margin:1px 2px 1px 0;white-space:nowrap;">{{ $r }}</span>
            @empty
              <span style="color:#c1c5cb;">—</span>
            @endforelse
          </td>
          <td style="color:#888;">{{ $t->propiedad }}</td>
          <td style="color:#888;">{{ \Carbon\Carbon::parse($t->fecha_planificada)->translatedFormat('d M') }}</td>
          <td>
            <button class="badge-sm" style="background:#EAF3DE;color:#27500A;border:none;cursor:pointer;padding:3px 8px;border-radius:4px;"
                    onclick="validarTarea(this, {{ $t->id }}, 'mantenimiento')">
              Validar
            </button>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
@endif

@if($verLimpSinImp)
{{-- Limpiezas de salida sin reserva asociada. Se arregla en Breezeway, no aquí, así que la
     columna que importa de verdad es la del enlace. --}}
<div class="db-card" style="margin-bottom:12px;">
  <p class="db-title"><i class="ti ti-link-off"></i> Limpiezas de salida sin reserva asociada <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Limpiezas de salida (o de salida y entrada) planificadas desde el 1 de octubre que en Breezeway no están enlazadas a ninguna reserva. Sin ese enlace, la reserva aparece en Dev. fianzas sin comentarios ni fotos. Se corrige en Breezeway, con el botón «Enlace reserva» de la tarea.</span></span></p>
  @if($limpiezasSinReserva->isEmpty())
    <p class="empty">Sin pendientes</p>
  @else
  <table class="db-table">
    <thead><tr><th>Tarea</th><th>Propiedad</th><th>Planificada</th><th>Estado</th><th>Breezeway</th></tr></thead>
    <tbody>
      @foreach($limpiezasSinReserva as $t)
      <tr>
        <td><a target="_blank" rel="noopener" href="{{ route('vm.tarea', [$project->slug, 'limpieza', $t->id]) }}" style="color:#111827;font-weight:600;text-decoration:none;">{{ $t->nombre }}</a></td>
        <td style="color:#888;">{{ $t->propiedad ?: '—' }}</td>
        <td style="color:#888;white-space:nowrap;">{{ \Carbon\Carbon::parse($t->fecha_planificada)->translatedFormat('d M Y') }}</td>
        <td style="color:#888;">{{ $t->estado ?: '—' }}</td>
        <td>
          <a target="_blank" rel="noopener"
             href="https://app.breezeway.io/task/{{ $t->breezeway_task_id }}"
             title="Abrir esta tarea en Breezeway para enlazarla a su reserva"
             style="color:#185FA5;text-decoration:none;white-space:nowrap;">#{{ $t->breezeway_task_id }}</a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>
  @endif
</div>
@endif

@if($verInformesPendientes)
@php $pasoLabels = ['aprueba' => 'Supervisor', 'rrhh' => 'RRHH', 'coordinador' => 'Coordinador', 'trabajador' => 'Trabajador', 'direccion' => 'Dirección']; @endphp
  {{-- Informes mensuales pendientes de alguna firma --}}
  <div class="db-card" style="margin-bottom:12px;">
    <p class="db-title"><i class="ti ti-file-check"></i> Informes mensuales pendientes de firma <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Informes mensuales con el flujo de aprobación iniciado y sin completar todavía. Director RRHH y Dirección general ven todos; Dirección de Operaciones solo los de su equipo supervisado.</span></span></p>
    @if($informesPendientes->isEmpty())
      <p class="empty">Sin informes pendientes</p>
    @else
    <table class="db-table">
      <thead><tr><th>Empleado</th><th>Mes</th><th>Esperando firma de</th><th>Desde</th><th></th></tr></thead>
      <tbody>
        @foreach($informesPendientes as $i)
        <tr>
          <td><a target="_blank" rel="noopener" href="{{ route('vm.usuario', [$project->slug, $i->id_usuario]) }}" style="color:#185FA5;text-decoration:none;font-weight:500;">{{ $i->usuario }}</a></td>
          <td style="color:#888;">{{ \Carbon\Carbon::create($i->anio, $i->mes, 1)->translatedFormat('F Y') }}</td>
          <td><span class="badge-sm" style="background:#E6F1FB;color:#0C447C;">{{ $pasoLabels[$i->paso_actual] ?? $i->paso_actual }}</span></td>
          <td style="color:#888;">{{ \Carbon\Carbon::parse($i->marcado_at)->translatedFormat('d M') }}</td>
          <td>
            <a href="{{ route('informe-imputaciones', $project->slug) }}?year={{ $i->anio }}&month={{ $i->mes }}&user_id={{ $i->id_usuario }}" target="_blank" rel="noopener"
               class="badge-sm" style="background:#EAF3DE;color:#27500A;text-decoration:none;padding:3px 8px;border-radius:4px;display:inline-block;">
              Ver informe
            </a>
          </td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
@endif

@if($verRRHH)
  {{-- Personas de Breezeway sin usuario mapeado en Opland --}}
  <div class="db-card" style="margin-bottom:12px;">
    <p class="db-title"><i class="ti ti-user-question"></i> Personas de Breezeway sin usuario en Opland <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Personas que aparecen asignadas a tareas en Breezeway pero no tienen ningún usuario de Opland con ese ID de Breezeway. Para que dejen de salir aquí, hay que dar de alta (o editar) el usuario en Opland con este ID en el campo Breezeway ID.</span></span></p>
    @if($breezewayPendientes->isEmpty())
      <p class="empty">Sin pendientes</p>
    @else
    <table class="db-table">
      <thead><tr><th>Nombre</th><th>Breezeway ID</th><th>Alta</th><th>Tareas</th></tr></thead>
      <tbody>
        @foreach($breezewayPendientes as $p)
        <tr>
          <td>{{ $p->nombre }}</td>
          <td style="color:#888;">{{ $p->breezeway_id }}</td>
          <td style="color:#888;">{{ \Carbon\Carbon::parse($p->fecha_alta)->translatedFormat('d M') }}</td>
          <td style="color:#dc3545;">{{ $p->num_tareas }}</td>
        </tr>
        @endforeach
      </tbody>
    </table>
    @endif
  </div>
@endif

</div>

<script>
const CSRF_DB = '{{ csrf_token() }}';
const BASE_DB = '{{ url($project->slug . "/dashboard") }}';

async function validarConciliacion(btn) {
    const tr        = btn.closest('tr');
    const idUsuario = tr.dataset.idUsuario;
    const fecha     = tr.dataset.fecha;
    const tipo      = tr.dataset.tipo;

    btn.disabled = true;
    btn.textContent = '…';

    const r = await window.fetchConAprobacion(BASE_DB + '/validar-conciliacion', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DB, 'Accept': 'application/json' },
        body: JSON.stringify({ id_usuario: idUsuario, fecha, tipo }),
    });
    if (!r) { btn.disabled = false; btn.textContent = 'Validar'; return; }
    const data = await r.json();

    if (data.ok) {
        const ini = data.fecha_inicio, fin = data.fecha_fin;
        document.querySelectorAll('#tbl-conciliaciones tbody tr').forEach(row => {
            if (row.dataset.idUsuario === idUsuario && row.dataset.tipo === tipo) {
                const f = row.dataset.fecha;
                if (f >= ini && f <= fin) row.remove();
            }
        });
    } else {
        btn.disabled = false;
        btn.textContent = 'Validar';
        alert(data.msg || 'Error al validar');
    }
}

async function validarFichaje(btn, fichajeId) {
    // Se guarda el contenido original en vez de reponer la palabra "Validar": el mismo botón es
    // un visé en los bloques de incidencias y un botón con texto en el de desviaciones.
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.textContent = '…';

    const r = await window.fetchConAprobacion(BASE_DB + '/validar-fichaje', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DB, 'Accept': 'application/json' },
        body: JSON.stringify({ id: fichajeId }),
    });
    if (!r) { btn.disabled = false; btn.innerHTML = original; return; }
    const data = await r.json();

    if (data.ok) {
        // Un mismo fichaje puede estar listado en varios bloques a la vez (desviaciones,
        // incidencias de fichaje, incidencias de ausencias). Se quitan todas sus filas, no solo
        // aquella desde la que se ha pulsado: dejar las otras daria a entender que siguen
        // pendientes hasta recargar la pagina.
        const filas = document.querySelectorAll('tr[data-fid="' + fichajeId + '"]');
        if (filas.length) { filas.forEach(tr => tr.remove()); }
        else { btn.closest('tr').remove(); }
    } else {
        btn.disabled = false;
        btn.innerHTML = original;
        alert('Error al validar');
    }
}

async function validarTarea(btn, id, tipo) {
    btn.disabled = true;
    btn.textContent = '…';

    const r = await fetch(BASE_DB + '/validar-tarea', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DB, 'Accept': 'application/json' },
        body: JSON.stringify({ id, tipo }),
    });
    const data = await r.json();

    if (data.ok) {
        btn.closest('tr').remove();
    } else {
        btn.disabled = false;
        btn.textContent = 'Validar';
        alert('Error al validar');
    }
}

async function marcarSsccHecho(btn, id) {
    btn.disabled = true;
    btn.textContent = '…';

    const r = await fetch(BASE_DB + '/sscc/' + id + '/hecho', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': CSRF_DB, 'Accept': 'application/json' },
    });
    const data = await r.json().catch(() => ({}));

    if (r.ok && data.ok) {
        btn.closest('tr').remove();
    } else {
        btn.disabled = false;
        btn.textContent = 'Hecho';
        alert(data.error || 'Error al marcar como hecho');
    }
}
</script>


@if ($vmUsuario)
<script>
(function () {
  const BASE  = '{{ route("vm.dashboard", $project->slug) }}';
  const CSRF  = document.querySelector('meta[name=csrf-token]')?.content ?? '';
  const btns  = document.getElementById('fw-btns');
  const time  = document.getElementById('fw-time');
  const label = document.getElementById('fw-label');
  let ticker  = null;

  function btn(cls, icon, title, fn) {
    const b = document.createElement('button');
    b.className = 'f-btn ' + cls;
    b.title = title;
    b.innerHTML = icon;
    b.onclick = fn;
    return b;
  }

  const PLAY = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>';
  const PAUS = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="5" width="4" height="14"/><rect x="14" y="5" width="4" height="14"/></svg>';
  const STOP = '<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><rect x="6" y="6" width="12" height="12"/></svg>';

  function hms(sec) {
    const h = Math.floor(sec / 3600);
    const m = Math.floor((sec % 3600) / 60);
    const s = sec % 60;
    return h > 0
      ? h + ':' + String(m).padStart(2,'0') + ':' + String(s).padStart(2,'0')
      : m + ':' + String(s).padStart(2,'0');
  }

  function startTick(fromSec) {
    if (ticker) clearInterval(ticker);
    let elapsed = fromSec;
    time.textContent = hms(elapsed);
    ticker = setInterval(() => { elapsed++; time.textContent = hms(elapsed); }, 1000);
  }

  function stopTick() { if (ticker) { clearInterval(ticker); ticker = null; } }

  function timeToSec(t) {
    if (!t) return 0;
    const p = t.split(':');
    return parseInt(p[0]) * 3600 + parseInt(p[1]) * 60 + parseInt(p[2] ?? 0);
  }

  function nowSec() {
    const n = new Date();
    return n.getHours() * 3600 + n.getMinutes() * 60 + n.getSeconds();
  }

  const badges = document.getElementById('fw-badges');
  const TIPO_LABEL = {
    turno: 'Turno', descanso: 'Descanso', vacaciones: 'Vacaciones',
    baja: 'Baja', comp_festivo: 'Comp. festivo',
    comp_horas: 'Comp. horas', asuntos: 'Asuntos propios', absentismo: 'Absentismo',
  };
  const TIPO_COLOR = {
    turno: '#185FA5', descanso: '#888', vacaciones: '#0F6E56',
    baja: '#c0392b', comp_festivo: '#8e44ad', comp_horas: '#8e44ad',
    asuntos: '#d68910', absentismo: '#c0392b',
  };

  function renderBadges(festivo, tipoHorario) {
    badges.innerHTML = '';
    if (festivo) {
      const b = document.createElement('span');
      b.style.cssText = 'font-size:10px;font-weight:600;padding:1px 7px;border-radius:999px;background:#fff3cd;color:#856404;border:1px solid #ffc107;white-space:nowrap;';
      b.textContent = 'Festivo';
      badges.appendChild(b);
    }
    if (tipoHorario) {
      const color = TIPO_COLOR[tipoHorario] ?? '#555';
      const b = document.createElement('span');
      b.style.cssText = 'font-size:10px;font-weight:600;padding:1px 7px;border-radius:999px;color:#fff;white-space:nowrap;background:' + color + ';';
      b.textContent = TIPO_LABEL[tipoHorario] ?? tipoHorario;
      badges.appendChild(b);
    }
  }

  function render(f, heMin) {
    btns.innerHTML = '';
    stopTick();

    if (!f) {
      time.textContent  = '0:00';
      label.textContent = 'Sin fichar';
      btns.appendChild(btn('play', PLAY, 'Iniciar jornada', doEntrada));
      return;
    }

    if (f.hora_fin) {
      const inicio = timeToSec(f.hora_inicio);
      const fin    = timeToSec(f.hora_fin);
      let total    = fin - inicio;
      let pausaMin = 0;
      if (f.pausa_inicio && f.pausa_fin) {
        pausaMin = Math.round((timeToSec(f.pausa_fin) - timeToSec(f.pausa_inicio)) / 60);
        total -= pausaMin * 60;
      }
      time.textContent = hms(Math.max(0, total));
      label.innerHTML = '';
      // Texto base: Finalizado · HH:MM – HH:MM
      label.appendChild(document.createTextNode('Finalizado · ' + f.hora_inicio.substring(0,5) + ' – ' + f.hora_fin.substring(0,5)));
      // Pausa entre pausa y HE para ocupar el ancho disponible
      if (pausaMin > 0 || (heMin !== null && heMin !== undefined)) {
        const row2 = document.createElement('span');
        row2.style.cssText = 'display:flex;justify-content:space-between;width:100%;margin-top:1px;';
        const left = document.createElement('span');
        left.style.color = '#aaa';
        left.textContent = pausaMin > 0 ? '(' + pausaMin + ' min de pausa)' : '';
        row2.appendChild(left);
        if (heMin !== null && heMin !== undefined) {
          const absMin = Math.abs(heMin);
          const hh     = Math.floor(absMin / 60);
          const mm     = String(absMin % 60).padStart(2, '0');
          const sp     = document.createElement('span');
          sp.style.cssText = 'font-weight:600;color:' + (heMin >= 0 ? '#1a7a3f' : '#c0392b') + ';';
          sp.textContent   = (heMin >= 0 ? '+' : '–') + (hh > 0 ? hh + 'h ' : '') + mm + 'm';
          row2.appendChild(sp);
        }
        label.appendChild(document.createElement('br'));
        label.appendChild(row2);
      }
      label.style.width = '100%';
      const b = btn('play', PLAY, '', null); b.disabled = true;
      btns.appendChild(b);
      return;
    }

    if (f.pausa_inicio && !f.pausa_fin) {
      const trabajados  = timeToSec(f.pausa_inicio) - timeToSec(f.hora_inicio);
      time.textContent  = hms(Math.max(0, trabajados));
      label.textContent = 'En pausa desde ' + f.pausa_inicio.substring(0,5);
      btns.appendChild(btn('play', PLAY, 'Reanudar', doPausa));
      btns.appendChild(btn('stop', STOP, 'Finalizar jornada', doSalida));
      return;
    }

    // Jornada activa
    let pausados = 0;
    if (f.pausa_inicio && f.pausa_fin) pausados = timeToSec(f.pausa_fin) - timeToSec(f.pausa_inicio);
    const base = nowSec() - timeToSec(f.hora_inicio) - pausados;
    const pausaMinActiva = f.pausa_inicio && f.pausa_fin ? Math.round(pausados / 60) : 0;
    label.textContent = pausaMinActiva > 0
      ? 'Desde ' + f.hora_inicio.substring(0,5) + ' (' + pausaMinActiva + ' min de pausa)'
      : 'Desde ' + f.hora_inicio.substring(0,5);
    startTick(Math.max(0, base));
    btns.appendChild(btn('pause', PAUS, 'Pausar', doPausa));
    btns.appendChild(btn('stop',  STOP, 'Finalizar jornada', doSalida));
  }

  async function post(endpoint) {
    document.querySelectorAll('.f-btn').forEach(b => b.disabled = true);
    const resp = await window.fetchConAprobacion(BASE + endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    if (!resp) { await load(); return; } // cancelado o bloqueado (fetchConAprobacion ya avisó)
    const data = await resp.json().catch(() => ({}));
    if (!resp.ok) alert(data.error ?? 'Error al fichar');
    await load();
  }

  async function doEntrada() { await post('/fichaje-entrada'); }
  async function doPausa()   { await post('/fichaje-pausa'); }
  async function doSalida()  { await post('/fichaje-salida'); }

  async function load() {
    label.textContent = '…';
    const resp = await fetch(BASE + '/fichaje-hoy', { headers: { 'Accept': 'application/json' } });
    const data = await resp.json().catch(() => ({}));
    renderBadges(data.festivo ?? false, data.tipo_horario ?? null);
    render(data.fichaje ?? null, data.he_min ?? null);
  }

  load();
})();
</script>

  @endif

@if($verRRHH)
@include('partials.vm-fichaje-modal', ['project' => $project, 'usuarios' => $usuariosFichaje])
@endif

{{-- Listados largos: 10 filas visibles y scroll para el resto. Se hace en un solo punto, y no
     tabla a tabla, para que valga igual para los bloques que se añadan más adelante. --}}
<script>
document.querySelectorAll('.db-card table.db-table').forEach(tabla => {
    const filas = tabla.querySelectorAll('tbody tr');
    if (filas.length <= 10) return;

    // Se mide antes de mover la tabla: la distancia de su borde superior al de la fila 11 es
    // la cabecera más 10 filas, contando bien las de doble altura (las de badges apilados).
    const alto = Math.round(filas[10].getBoundingClientRect().top - tabla.getBoundingClientRect().top);

    const caja = document.createElement('div');
    caja.className = 'db-scroll';
    tabla.parentNode.insertBefore(caja, tabla);
    caja.appendChild(tabla);
    if (alto > 0) caja.style.maxHeight = alto + 'px';
});
</script>

{{-- Bloques plegables. Igual que el scroll de arriba: en un solo punto, para que los bloques que
     se añadan después lo hereden sin tocar nada.

     Va DESPUÉS del script de alturas a propósito: aquel mide con getBoundingClientRect, y sobre
     un bloque ya plegado mediría cero. --}}
<script>
(function () {
    // El estado se guarda por bloque, para que plegar lo que no te interesa aguante entre
    // visitas. La clave es el texto del título: los bloques no tienen id, y usar su posición
    // haría que insertar uno nuevo desplazara el estado de todos los demás.
    const GUARDADO = 'db-cerrados:{{ $project->slug }}';
    let cerrados;
    try { cerrados = new Set(JSON.parse(localStorage.getItem(GUARDADO) || '[]')); }
    catch (e) { cerrados = new Set(); }

    const guardar = () => {
        try { localStorage.setItem(GUARDADO, JSON.stringify([...cerrados])); } catch (e) {}
    };

    // Dos bloques comparten título ("Limpieza completada sin imputar" sale en dos variantes de
    // maquetación), así que al repetirse se numera: si no, plegar uno plegaría el otro.
    const vistas = new Map();

    document.querySelectorAll('.db-card').forEach(tarjeta => {
        const titulo = tarjeta.querySelector('.db-title');
        if (!titulo || titulo.dataset.plegable === '1') return;
        titulo.dataset.plegable = '1';

        // El texto del título SIN el tooltip: su contenido es largo y cambia cada vez que se
        // reescribe la ayuda, y entonces el estado guardado dejaría de encontrarse.
        const soloTexto = titulo.cloneNode(true);
        soloTexto.querySelectorAll('.app-tooltip').forEach(t => t.remove());
        const base = (soloTexto.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60);
        const n = (vistas.get(base) || 0) + 1;
        vistas.set(base, n);
        const clave = n > 1 ? base + ' #' + n : base;

        // Todo lo que va detrás del título pasa a un contenedor, que es lo que se pliega.
        const cuerpo = document.createElement('div');
        cuerpo.className = 'db-body';
        while (titulo.nextSibling) cuerpo.appendChild(titulo.nextSibling);
        tarjeta.appendChild(cuerpo);

        const mando = document.createElement('span');
        mando.className = 'db-mando';

        // Chip solo en los listados. Las filas separadoras no son elementos: contarlas diría que
        // hay una incidencia más de las que hay.
        const filas = [...cuerpo.querySelectorAll('table.db-table tbody tr')]
            .filter(tr => !tr.classList.contains('db-sep'));
        if (cuerpo.querySelector('table.db-table')) {
            const chip = document.createElement('span');
            chip.className = 'db-chip';
            chip.textContent = filas.length;
            chip.title = filas.length === 1 ? '1 elemento' : filas.length + ' elementos';
            mando.appendChild(chip);
        }

        const flecha = document.createElement('span');
        flecha.className = 'db-flecha';
        flecha.textContent = '▾';
        mando.appendChild(flecha);

        titulo.appendChild(mando);
        titulo.classList.add('db-plegable');

        // Accesible con teclado: el título no es un <button>, así que se le da rol y foco.
        titulo.setAttribute('role', 'button');
        titulo.setAttribute('tabindex', '0');

        const pintar = (cerrado) => {
            tarjeta.classList.toggle('db-cerrado', cerrado);
            cuerpo.hidden = cerrado;
            titulo.setAttribute('aria-expanded', cerrado ? 'false' : 'true');
        };
        pintar(cerrados.has(clave));

        const alternar = () => {
            const cerrado = !tarjeta.classList.contains('db-cerrado');
            pintar(cerrado);
            cerrado ? cerrados.add(clave) : cerrados.delete(clave);
            guardar();
        };

        titulo.addEventListener('click', (e) => {
            // El icono de ayuda vive dentro del título y abre su propio tooltip: pulsarlo no
            // debe plegar el bloque.
            if (e.target.closest('.app-tooltip')) return;
            alternar();
        });
        titulo.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); alternar(); }
        });
    });
})();
</script>
</x-app-layout>
