<x-app-layout :project="$project" :breadcrumb="[['label'=>'Inicio','url'=>'']]">

@include('partials.role-badge', ['project' => $project, 'texto' => 'Algunos bloques de este dashboard (Reservas, RRHH, Ausencias sin conciliar, Limpieza/Mantenimiento sin imputar) solo son visibles para ciertos roles (Dirección general, Dirección de Operaciones, Coordinador limpieza, Coordinador mantenimiento, Director RRHH). Los ves todos por ser admin.'])

<style>
.db-grid    { display:grid; grid-template-columns:repeat(auto-fit,minmax(340px,1fr)); gap:12px; margin-bottom:12px; }
.db-card    { background:#fff; border:0.5px solid rgba(0,0,0,.08); border-radius:12px; padding:1rem 1.25rem; }
.dark .db-card { background:#1a1a1a; border-color:rgba(255,255,255,.08); }
.db-title   { font-size:13px; font-weight:500; color:#888; margin:0 0 10px; display:flex; align-items:center; gap:6px; }
.db-title i { font-size:15px; }
.db-count   { font-size:32px; font-weight:500; line-height:1; margin:0 0 12px; }
.db-table   { width:100%; border-collapse:collapse; font-size:12px; }
.db-table th{ text-align:left; padding:4px 6px; color:#aaa; font-weight:500; border-bottom:0.5px solid rgba(0,0,0,.06); }
.db-table td{ padding:5px 6px; border-bottom:0.5px solid rgba(0,0,0,.04); }
.dark .db-table th { border-color:rgba(255,255,255,.07); }
.dark .db-table td { border-color:rgba(255,255,255,.04); }
.db-table tr:last-child td { border-bottom:none; }
.badge-sm   { font-size:10px; padding:1px 6px; border-radius:4px; white-space:nowrap; }
.empty      { font-size:12px; color:#bbb; text-align:center; padding:1rem 0; }
.diff-pos   { color:#28a745; font-weight:500; }
.diff-neg   { color:#dc3545; font-weight:500; }

/* Tabla 7 días */
.dias7-table { width:100%; border-collapse:collapse; font-size:12px; }
.dias7-table th { padding:5px 8px; text-align:center; color:#aaa; font-weight:500; border-bottom:0.5px solid rgba(0,0,0,.06); white-space:nowrap; }
.dias7-table td { padding:6px 8px; text-align:center; border-bottom:none; }
.dias7-table .lbl-row td { font-size:11px; color:#aaa; padding-top:4px; }
.count-cell { font-size:18px; font-weight:600; }
.count-cell.zero { color:#ddd; }
.count-cell a { color:inherit; text-decoration:none; }
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

  {{-- Fila 1: contadores hoy -----------------------------------------------}}
  @if($verReservas)
  <div class="db-grid" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr));margin-bottom:12px;">
    <div class="db-card">
      <p class="db-title"><i class="ti ti-login-2"></i> Check-ins hoy <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Reservas con fecha de check-in hoy.</span></span></p>
      <p class="db-count" style="color:#185FA5;">{{ $checkinHoy->count() }}</p>
    </div>
    <div class="db-card">
      <p class="db-title"><i class="ti ti-logout-2"></i> Check-outs hoy <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Reservas con fecha de check-out hoy, ordenadas por tiempo de limpieza descendente.</span></span></p>
      <p class="db-count" style="color:#0F6E56;">{{ $checkoutHoy->count() }}</p>
    </div>
    <div class="db-card">
      <p class="db-title"><i class="ti ti-wash"></i> Tareas limpieza vencidas <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de limpieza con fecha planificada anterior a hoy que no tienen tiempo imputado.</span></span></p>
      <p class="db-count" style="color:#854F0B;">{{ $tareasLimpieza->count() }}</p>
    </div>
    <div class="db-card">
      <p class="db-title"><i class="ti ti-tool"></i> Tareas mant.+pisc. vencidas <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de mantenimiento y piscinas con fecha planificada anterior a hoy que no tienen tiempo imputado.</span></span></p>
      <p class="db-count" style="color:#854F0B;">{{ $tareasMantPisc->count() }}</p>
    </div>
  </div>
  @endif

  {{-- Fila 2: checkins y checkouts hoy -------------------------------------}}
  @if($verReservas)
  <div class="db-grid">

    <div class="db-card">
      <p class="db-title"><i class="ti ti-login-2"></i> Check-ins hoy <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Reservas con fecha de check-in hoy.</span></span></p>
      @if($checkinHoy->isEmpty())
        <p class="empty">Sin check-ins hoy</p>
      @else
      <table class="db-table">
        <thead><tr><th>Propiedad</th></tr></thead>
        <tbody>
          @foreach($checkinHoy as $r)
          <tr><td>{{ $r->vm_propiedades_nombre }}</td></tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>

    <div class="db-card">
      <p class="db-title"><i class="ti ti-logout-2"></i> Check-outs hoy <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Reservas con fecha de check-out hoy, ordenadas por tiempo de limpieza descendente.</span></span></p>
      @if($checkoutHoy->isEmpty())
        <p class="empty">Sin check-outs hoy</p>
      @else
      <table class="db-table">
        <thead><tr><th>Propiedad</th></tr></thead>
        <tbody>
          @foreach($checkoutHoy as $r)
          <tr><td>{{ $r->vm_propiedades_nombre }}</td></tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>

  </div>
  @endif

  {{-- Próximos 7 días — tabla columnas -------------------------------------}}
  @if($verReservas)
  <div class="db-grid">

    {{-- Check-ins próximos --}}
    <div class="db-card">
      <p class="db-title"><i class="ti ti-login-2"></i> Check-ins próximos 7 días <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Reservas con fecha de check-in en los próximos 7 días, agrupadas por día.</span></span></p>
      @php $dayLetters = [0=>'D',1=>'L',2=>'M',3=>'X',4=>'J',5=>'V',6=>'S']; @endphp
      <table class="dias7-table">
        <thead>
          <tr>
            @foreach($dias7 as $dia)
            @php $c = \Carbon\Carbon::parse($dia); @endphp
            <th>{{ $dayLetters[$c->dayOfWeek] }}{{ $c->format('j') }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          <tr>
            @foreach($dias7 as $dia)
            @php
              $reservasDia = $checkinProximos[$dia] ?? collect();
              $tooltip     = $reservasDia->pluck('vm_propiedades_nombre')->implode("\n");
              $cnt         = $reservasDia->count();
            @endphp
            <td class="count-cell {{ $cnt === 0 ? 'zero' : '' }}">
              @if($tooltip)
                <span class="app-tooltip">{{ $cnt ?: '·' }}<span class="app-tooltip-box">{{ $tooltip }}</span></span>
              @else
                {{ $cnt ?: '·' }}
              @endif
            </td>
            @endforeach
          </tr>
        </tbody>
      </table>
    </div>

    {{-- Check-outs próximos --}}
    <div class="db-card">
      <p class="db-title"><i class="ti ti-logout-2"></i> Check-outs próximos 7 días <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Reservas con fecha de check-out en los próximos 7 días, agrupadas por día y ordenadas por tiempo de limpieza.</span></span></p>
      @php $dayLetters = [0=>'D',1=>'L',2=>'M',3=>'X',4=>'J',5=>'V',6=>'S']; @endphp
      <table class="dias7-table">
        <thead>
          <tr>
            @foreach($dias7 as $dia)
            @php $c = \Carbon\Carbon::parse($dia); @endphp
            <th>{{ $dayLetters[$c->dayOfWeek] }}{{ $c->format('j') }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          <tr>
            @foreach($dias7 as $dia)
            @php
              $reservasDia  = $checkoutProximos[$dia] ?? collect();
              $cnt          = $reservasDia->count();
              $tooltipLines = $reservasDia->map(fn($r) =>
                $r->vm_propiedades_nombre . ($r->tiempo_limpieza ? ' (' . $r->tiempo_limpieza . 'h)' : '')
              )->implode("\n");
            @endphp
            <td class="count-cell {{ $cnt === 0 ? 'zero' : '' }}">
              @if($tooltipLines)
                <span class="app-tooltip">{{ $cnt ?: '·' }}<span class="app-tooltip-box">{{ $tooltipLines }}</span></span>
              @else
                {{ $cnt ?: '·' }}
              @endif
            </td>
            @endforeach
          </tr>
          {{-- Fila de horas totales de limpieza --}}
          <tr class="lbl-row">
            @foreach($dias7 as $dia)
            @php $sumH = ($checkoutProximos[$dia] ?? collect())->whereNotNull('tiempo_limpieza')->sum('tiempo_limpieza'); @endphp
            <td style="color:#0F6E56;font-size:11px;">{{ $sumH ? $sumH . 'h' : '' }}</td>
            @endforeach
          </tr>
        </tbody>
      </table>
    </div>

  </div>
  @endif

  {{-- Fila 3: alertas RRHH -------------------------------------------------}}
  <div class="db-grid">

    {{-- Próximas ausencias del usuario --}}
    @if($vmUsuario)
    <div class="db-card">
      <p class="db-title"><i class="ti ti-calendar-user"></i> Mis próximas ausencias <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Ausencias registradas para tu usuario con fecha de fin igual o posterior a hoy.</span></span></p>
      @if($proximasAusencias->isEmpty())
        <p class="db-empty">No hay ausencias próximas registradas.</p>
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
              <a href="{{ url($project->slug . '/horario') }}?semana={{ $lunes }}"
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

    @endif
    {{-- Turno sin fichaje --}}
    @if($verRRHH)
    <div class="db-card">
      <p class="db-title"><i class="ti ti-clock-exclamation"></i> Turno sin fichaje <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Usuarios con horario de tipo turno en fechas pasadas que no tienen ningún fichaje registrado ese día.</span></span></p>
      @if($turnoSinFichaje->isEmpty())
        <p class="empty">Sin incidencias</p>
      @else
      <table class="db-table">
        <thead><tr><th>Empleado</th><th>Fecha</th></tr></thead>
        <tbody>
          @foreach($turnoSinFichaje as $t)
          <tr>
            <td style="font-weight:500;">{{ $t->usuario }}</td>
            <td style="color:#888;">{{ \Carbon\Carbon::parse($t->fecha)->translatedFormat('d M Y') }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>

    @endif
  </div>

  @if($verRRHH)
  <div class="db-grid">

    {{-- Conflictos fichaje --}}
    <div class="db-card">
      <p class="db-title"><i class="ti ti-alert-triangle"></i> Conflictos en fichaje <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Fichajes que coinciden con un horario de descanso o con una ausencia registrada ese mismo día.</span></span></p>
      @if($conflictosFichaje->isEmpty())
        <p class="empty">Sin conflictos</p>
      @else
      <table class="db-table">
        <thead>
          <tr>
            <th>Empleado</th>
            <th>Fecha</th>
            <th>Conflictos</th>
          </tr>
        </thead>
        <tbody>
          @foreach($conflictosFichaje as $c)
          @php
            $c = (object)$c;
            $fichajeUrl = route('vm.fichaje_form', [$project->slug, $c->fichaje_id]);
            $horarioSemana = \Carbon\Carbon::parse($c->fecha)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
            $horarioUrl = route('horario', $project->slug) . '?semana=' . $horarioSemana;
            $fichaUsuarioUrl = route('vm.usuario', [$project->slug, $c->id_usuario]);
          @endphp
          <tr>
            <td>
              <a href="{{ $fichaUsuarioUrl }}" style="color:#185FA5;text-decoration:none;font-weight:500;">{{ $c->usuario }}</a>
            </td>
            <td style="white-space:nowrap;font-size:12px;">{{ \Carbon\Carbon::parse($c->fecha)->translatedFormat('d M Y') }}</td>
            <td>
              <div style="display:flex;flex-direction:column;gap:3px;">
                {{-- Badge Trabajo → fichaje --}}
                <a href="{{ $fichajeUrl }}" style="text-decoration:none;">
                  <span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.72rem;font-weight:600;background:#74aaf8;color:#fff;">Trabajo</span>
                </a>
                {{-- Badge Descanso → horario --}}
                @if($c->descanso)
                <a href="{{ $horarioUrl }}" style="text-decoration:none;">
                  <span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.72rem;font-weight:600;background:#F3F4F6;color:#6B7280;">Descanso</span>
                </a>
                @endif
                {{-- Badges ausencias → ficha ausencia --}}
                @foreach($c->ausencias as $aus)
                <a href="{{ route('ficha', [$project->slug, 'ausencias', $aus['id']]) }}" style="text-decoration:none;">
                  <span style="display:inline-block;padding:1px 8px;border-radius:10px;font-size:.72rem;font-weight:600;background:#FFF3CD;color:#856404;">{{ $aus['tipo'] }}</span>
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

    {{-- Fichaje vs imputaciones --}}
    <div class="db-card">
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
              <a href="{{ $fichajeUrl }}" style="color:#185FA5;text-decoration:none;font-size:12px;">
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

    @if($verLimpSinImp)
    {{-- Tareas limpieza vencidas --}}
    <div class="db-card">
      <p class="db-title"><i class="ti ti-wash"></i> Tareas limpieza vencidas sin imputar <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de limpieza con fecha planificada pasada que no tienen ninguna imputación registrada.</span></span></p>
      @if($tareasLimpieza->isEmpty())
        <p class="empty">Sin tareas vencidas</p>
      @else
      <table class="db-table">
        <thead><tr><th>Responsable</th><th>Propiedad</th><th>Planificada</th></tr></thead>
        <tbody>
          @foreach($tareasLimpieza as $t)
          <tr>
            <td>{{ $t->control_user_nombre }}</td>
            <td style="color:#888;">{{ $t->propiedad }}</td>
            <td style="color:#dc3545;">{{ \Carbon\Carbon::parse($t->fecha_planificada)->translatedFormat('d M') }}</td>
          </tr>
          @endforeach
        </tbody>
      </table>
      @endif
    </div>

    @endif
  </div>
  @endif

  @if($verMantSinImp)
  {{-- Tareas mantenimiento + piscinas --}}
  <div class="db-card" style="margin-bottom:12px;">
    <p class="db-title"><i class="ti ti-tool"></i> Tareas mantenimiento y piscinas vencidas sin imputar <span class="app-tooltip"><span style="display:inline-flex;align-items:center;justify-content:center;width:14px;height:14px;border-radius:50%;background:#e5e7eb;color:#6b7280;font-size:10px;font-weight:700;cursor:default;margin-left:4px;font-style:normal;">i</span><span class="app-tooltip-box">Tareas de mantenimiento y piscinas con fecha planificada pasada que no tienen ninguna imputación registrada.</span></span></p>
    @if($tareasMantPisc->isEmpty())
      <p class="empty">Sin tareas vencidas</p>
    @else
    <table class="db-table">
      <thead><tr><th>Tarea</th><th>Propiedad</th><th>Planificada</th></tr></thead>
      <tbody>
        @foreach($tareasMantPisc as $t)
        <tr>
          <td>{{ $t->nombre }}</td>
          <td style="color:#888;">{{ $t->propiedad }}</td>
          <td style="color:#dc3545;">{{ \Carbon\Carbon::parse($t->fecha_planificada)->translatedFormat('d M') }}</td>
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

    const r = await fetch(BASE_DB + '/validar-conciliacion', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DB, 'Accept': 'application/json' },
        body: JSON.stringify({ id_usuario: idUsuario, fecha, tipo }),
    });
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
    btn.disabled = true;
    btn.textContent = '…';

    const r = await fetch(BASE_DB + '/validar-fichaje', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF_DB, 'Accept': 'application/json' },
        body: JSON.stringify({ id: fichajeId }),
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
    baja: 'Baja médica', comp_festivo: 'Comp. festivo',
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
    const resp = await fetch(BASE + endpoint, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
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
</x-app-layout>
