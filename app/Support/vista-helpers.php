<?php

// Funciones de presentación que antes se declaraban dentro de un bloque @php de cada blade.
// Una función declarada en una vista se registra en el ámbito global del proceso, así que la
// segunda vez que se renderiza esa misma vista en la misma petición PHP aborta con
// "Cannot redeclare function ...". Aquí se declaran una sola vez, al cargar el autoload.
//
// Se conservan los nombres originales para no tocar las llamadas de las vistas.

// ── Formato de tiempo ────────────────────────────────────────────────────────

// vm/fichaje.blade.php
function fmtTime(?string $t): string
{
    if (!$t) return '—';
    return substr($t, 0, 5);
}

// vm/fichaje.blade.php -- minutos con signo, el negativo con el guion largo tipográfico
function fmtMin2(?int $min): string
{
    if ($min === null) return '—';
    $neg = $min < 0;
    $abs = abs($min);
    return ($neg ? '−' : '') . intdiv($abs, 60) . 'h ' . str_pad($abs % 60, 2, '0', STR_PAD_LEFT) . 'm';
}

// vm/tarea.blade.php
function minToHm(int $min): string
{
    if ($min <= 0) return '0h 00m';
    return intdiv($min, 60) . 'h ' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'm';
}

// vm/tareas_list.blade.php -- igual que minToHm() salvo en el cero, que ahí se pinta vacío en vez
// de "0h 00m". El sufijo "Tl" nació para esquivar el choque de nombres con minToHm(); ahora que
// las dos viven aquí el sufijo sobra, pero se mantiene para no tocar la vista.
function minToHmTl(int $min): string
{
    if ($min <= 0) return '—';
    return intdiv($min, 60) . 'h ' . str_pad($min % 60, 2, '0', STR_PAD_LEFT) . 'm';
}

// informe-imputaciones-list.blade.php -- horas decimales como "+8:30" / "-1:15"
function sprintfHoras($decimal)
{
    $signo = $decimal < 0 ? '-' : '+';
    $abs   = abs($decimal);
    $h     = (int) floor($abs);
    $m     = (int) round(($abs - $h) * 60);
    return sprintf('%s%d:%02d', $signo, $h, $m);
}

// informe-imputaciones-list.blade.php -- minutos como "2d 03h 45m"
function sprintfDiasHorasMin($totalMin)
{
    $d = intdiv($totalMin, 1440);
    $h = intdiv($totalMin % 1440, 60);
    $m = $totalMin % 60;
    $partes = [];
    if ($d > 0) $partes[] = "{$d}d";
    if ($h > 0 || $d > 0) $partes[] = sprintf('%02dh', $h);
    $partes[] = sprintf('%02dm', $m);
    return implode(' ', $partes);
}

// ── Colores y celdas ─────────────────────────────────────────────────────────

// informe-imputaciones.blade.php -- color de la leyenda de tipos de ausencia. El criterio es el de
// VmHorasService::colorTipoAusencia(), el mismo que usan los badges del día; $map solo permite
// que una vista concreta sobrescriba algún color puntual.
function tipoColor($nombre, $map = [])
{
    return $map[$nombre] ?? \App\Services\VmHorasService::colorTipoAusencia($nombre);
}

// horario.blade.php -- celda del cuadrante
function horarioCellHtml($h, bool $isFest = false): string
{
    if (!$h) {
        if ($isFest) return '<span class="hc hc-festivo">Día festivo</span>';
        return '<div class="hce"></div>';
    }
    if ($h->tipo === 'turno') {
        $de = $h->hora_inicio ? substr($h->hora_inicio, 0, 5) : '?';
        $a  = $h->hora_fin   ? substr($h->hora_fin,    0, 5) : '?';
        return "<span class=\"hc hc-turno\">{$de}–{$a}</span>";
    }
    $labels = [
        'descanso'     => 'Descanso',
        'vacaciones'   => 'Vacaciones',
        'baja'         => 'Baja',
        'comp_festivo' => 'Comp. festivo',
        'comp_horas'   => 'Comp. horas',
        'asuntos'      => 'Asuntos propios',
        'absentismo'   => 'Absentismo',
    ];
    $lbl = $labels[$h->tipo] ?? $h->tipo;
    return "<span class=\"hc hc-{$h->tipo}\">{$lbl}</span>";
}

// horario.blade.php -- solo se usa cuando NO hay ningún horario (turno/descanso/...) puesto ese
// día: si lo hay, tiene prioridad y esta función ni se llama.
function ausenciaCellHtml(string $tipo): string
{
    $t = mb_strtolower($tipo);
    $cls = 'hc-aus';
    if (str_starts_with($t, 'comp')) $cls = 'hc-compensacion';
    elseif (str_contains($t, 'vacac'))  $cls = 'hc-vacaciones';
    elseif (str_contains($t, 'baja'))   $cls = 'hc-baja';
    elseif (str_contains($t, 'asunto')) $cls = 'hc-asuntos';
    elseif (str_contains($t, 'absent')) $cls = 'hc-absentismo';
    return "<span class=\"hc aus-readonly {$cls}\" title=\"Ausencia registrada\">{$tipo}</span>";
}

// ── Contratos (vm/usuario.blade.php) ─────────────────────────────────────────

function estadoContrato($c, $contratos): string
{
    $hoy = date('Y-m-d');
    if ($c->fecha_alta > $hoy) return 'Próximo';
    if (!$c->fecha_baja || $c->fecha_baja > $hoy) return 'Activo';
    return 'Finalizado';
}

function varPct($actual, $prev): ?float
{
    if (!$prev || $prev->salario_base == 0) return null;
    return round(($actual->salario_base - $prev->salario_base) / $prev->salario_base * 100, 1);
}

function varAbs($actual, $prev): ?float
{
    if (!$prev) return null;
    return round($actual->salario_base - $prev->salario_base, 2);
}

// Los contratos llegan DESC; para la variación hace falta el anterior cronológico, es decir el de
// mayor fecha_alta por debajo de la del contrato actual.
function prevContrato($c, $contratos)
{
    return $contratos
        ->filter(fn($x) => $x->fecha_alta < $c->fecha_alta)
        ->sortByDesc('fecha_alta')
        ->first();
}
