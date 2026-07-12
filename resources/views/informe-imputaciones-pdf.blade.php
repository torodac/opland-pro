<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin:2cm 1.5cm 0.5cm 1.5cm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:DejaVu Sans,sans-serif; font-size:9pt; color:#222; padding:0.5cm 0.5cm 0; }

.pdf-header       { display:table; width:100%; margin-bottom:12pt; }
.pdf-header-left  { display:table-cell; vertical-align:middle; }
.pdf-header-right { display:table-cell; vertical-align:middle; text-align:right; width:120pt; }
.pdf-title        { font-size:13pt; font-weight:bold; color:#333; }
.pdf-period       { font-size:9pt; color:#666; margin-top:2pt; }

.pdf-cols  { display:table; width:100%; }
.col-left  { display:table-cell; vertical-align:top; width:145pt; padding-right:10pt; }
.col-right { display:table-cell; vertical-align:top; }

.panel       { border:1pt solid #dde; border-radius:4pt; padding:6pt; margin-bottom:8pt; }
.panel-title { font-size:7.5pt; font-weight:bold; color:#444; margin-bottom:5pt;
               border-bottom:1pt solid #eee; padding-bottom:3pt; }

.leyenda-row  { display:table; width:100%; margin-bottom:3pt; }
.leyenda-dot  { display:table-cell; width:10pt; vertical-align:middle; }
.leyenda-dot span { display:inline-block; width:8pt; height:8pt; border-radius:2pt; }
.leyenda-name { display:table-cell; font-size:7pt; vertical-align:middle; }

.tbl-small    { width:100%; border-collapse:collapse; font-size:7pt; }
.tbl-small th { text-align:center; padding:2pt 3pt; font-weight:bold;
                background:#f5f5f5; border-bottom:1pt solid #ddd; color:#555; }
.tbl-small td { text-align:center; padding:2pt 3pt; border-bottom:1pt solid #f0f0f0; }
.tbl-small td:first-child { text-align:left; font-weight:bold; }
.tbl-small tr.tot td { font-weight:bold; border-top:1pt solid #ccc; background:#f9f9f9; }

.saldo-box { background:#eef2ff; border-radius:3pt; padding:4pt 6pt; font-size:7pt;
             margin-top:4pt; border:1pt solid #ccd; }

.tbl-main    { width:100%; border-collapse:collapse; font-size:7.5pt; }
.tbl-main th { background:#f0f2f5; color:#444; font-weight:bold;
               padding:4pt 3pt; text-align:center; border:1pt solid #d5d8dc; }
.tbl-main td { padding:3pt 3pt; text-align:center; border:1pt solid #e8eaed; white-space:nowrap; }
.tbl-main .col-dia { width:28pt; }
.tbl-main .col-time { width:22pt; }
.tbl-main .col-dur { width:28pt; }
.tbl-main .col-km { width:22pt; }
.tbl-main .col-tipo { width:18pt; }
.tbl-main tr.wk td { background:#fafafa; color:#bbb; }

.tipo-badge { display:inline-block; padding:1pt 5pt; border-radius:8pt;
              font-size:6.5pt; font-weight:bold; color:#fff; }
.he-pos { color:#1a7a34; }
.he-neg { color:#cc2200; }

.firma-sec  { margin-top:28pt; font-size:8pt; }
.firma-line { border-bottom:1pt solid #999; width:180pt; height:40pt; margin-top:10pt; margin-bottom:8pt; }
.nombre-nif { font-size:8.5pt; font-weight:bold; width:180pt; }
</style>
</head>
<body>
@php use App\Http\Controllers\Vm\InformeImputacionesController as IC; @endphp
@php

$tipo_color = [
    'Asuntos propios' => '#34c163',
    'Baja'            => '#7b3f8c',
    'Compensacion'    => '#e83e8c',
    'Revisar'         => '#fd7e14',
    'Vacaciones'      => '#e8b800',
    'Absentismo'      => '#dc3545',
];
$color_trabajo = '#74aaf8';

if (!function_exists('tc')) { function tc($nombre, $map) {
    if (isset($map[$nombre])) return $map[$nombre];
    $n = mb_strtolower($nombre);
    if (str_starts_with($n, 'comp')) return '#e83e8c';
    if (str_contains($n, 'vacac'))  return '#e8b800';
    if (str_contains($n, 'baja'))   return '#7b3f8c';
    if (str_contains($n, 'asunto')) return '#34c163';
    return '#888';
} }

$meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio',
             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

$sum_ep = array_sum(array_column($year_stats, 'ep'));
$sum_en = array_sum(array_column($year_stats, 'en'));
$sum_et = array_sum(array_column($year_stats, 'total'));
@endphp

<div class="pdf-header">
    <div class="pdf-header-left">
        @if(!empty($sin_contrato) && $sin_contrato)
        @php
            $ffc = \Carbon\Carbon::parse($fecha_fin_contrato)->locale('es');
            $tituloFecha = $ffc->isoFormat('dddd, D [de] MMMM [de] YYYY');
        @endphp
        <div class="pdf-title">Informe liquidación con fecha {{ $tituloFecha }}</div>
        @else
        <div class="pdf-title">Informe Mensual de Fichaje</div>
        @endif
        <div class="pdf-period">{{ $meses_es[$month] }} {{ $year }} &mdash; {{ $usuario->nombre ?? '' }}</div>
    </div>
</div>

<div class="pdf-cols">

    <div class="col-left">

        <div class="panel">
            <div class="panel-title">Leyenda</div>
            @foreach($tipos as $t)
                @php $tn = mb_strtolower($t->nombre); if (str_starts_with($tn, 'comp. ')) continue; @endphp
                <div class="leyenda-row">
                    <div class="leyenda-dot"><span style="background:{{ tc($t->nombre, $tipo_color) }}"></span></div>
                    <div class="leyenda-name">{{ $t->nombre }}</div>
                </div>
            @endforeach
            <div class="leyenda-row">
                <div class="leyenda-dot"><span style="background:{{ $color_trabajo }}"></span></div>
                <div class="leyenda-name">Trabajo</div>
            </div>
        </div>

        @if(!$is_liquidado)
        <div class="panel">
            <div class="panel-title">Sigma horas extra {{ $year }}</div>
            <table class="tbl-small">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th style="color:#1a7a34">+</th>
                        <th style="color:#cc2200">-</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($year_stats as $s)
                <tr>
                    <td>{{ $s['label'] }}</td>
                    <td style="color:#1a7a34">{{ $s['ep'] != 0 ? number_format($s['ep'],1,',','') : '' }}</td>
                    <td style="color:#cc2200">{{ $s['en'] != 0 ? number_format($s['en'],1,',','') : '' }}</td>
                    <td style="color:{{ $s['total'] >= 0 ? '#1a7a34' : '#cc2200' }}">{{ number_format($s['total'],1,',','') }}</td>
                </tr>
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="tot">
                        <td>Sigma</td>
                        <td style="color:#1a7a34">{{ number_format($sum_ep,1,',','') }}</td>
                        <td style="color:#cc2200">{{ number_format($sum_en,1,',','') }}</td>
                        <td style="color:{{ $sum_et >= 0 ? '#1a7a34' : '#cc2200' }}">{{ number_format($sum_et,1,',','') }}</td>
                    </tr>
                </tfoot>
            </table>
            <div class="saldo-box">
                Saldo historico: <strong>{{ IC::fmtHoras($hist_extras, true) ?: '0h 00m' }}</strong>
            </div>
            @if(!empty($sin_contrato) && $sin_contrato)
            <div style="margin-top:6pt;font-size:8pt;color:#374151;">Horas extras compensadas en la liquidación.</div>
            @endif
        </div>
        @endif

        @if(!$is_liquidado)
        <div class="panel">
            <div class="panel-title">Registro dias {{ $year }}</div>
            <table class="tbl-small">
                <thead>
                    <tr>
                        <th>Mes</th>
                        <th style="color:#4e8ef7">T</th>
                        <th style="color:#f0960a">C</th>
                        <th style="color:#e8b800">V</th>
                        <th style="color:#7b3f8c">B</th>
                        <th style="color:#34c163">AA</th>
                        <th>S</th>
                        <th style="color:#999">Lab.</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($year_stats as $s)
                    @if($s['total_dias'] > 0)
                    <tr>
                        <td>{{ $s['label'] }}</td>
                        <td>{{ $s['dias_col']['T'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['C'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['V'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['B'] ?: '' }}</td>
                        <td>{{ $s['dias_col']['AA'] ?: '' }}</td>
                        <td>{{ $s['total_dias'] }}</td>
                        <td style="color:#999">{{ $s['lab'] }}</td>
                    </tr>
                    @endif
                @endforeach
                </tbody>
                <tfoot>
                    <tr class="tot">
                        <td>S</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['T'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['C'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['V'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['B'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['dias_col']['AA'] ?? 0, $year_stats)) }}</td>
                        <td>{{ array_sum(array_map(fn($s) => $s['total_dias'], $year_stats)) }}</td>
                        <td style="color:#999">{{ array_sum(array_map(fn($s) => $s['lab'], $year_stats)) }}</td>
                    </tr>
                </tfoot>
            </table>
            @if(!empty($sin_contrato) && $sin_contrato)
            <div style="margin-top:6pt;font-size:8pt;color:#374151;">Días de vacaciones saldados en la liquidación.</div>
            @endif
        </div>
        @endif

    </div>

    <div class="col-right">
        <table class="tbl-main">
            <thead>
                <tr>
                    <th class="col-dia">Día</th>
                    <th class="col-time">Inicio</th>
                    <th class="col-time">Fin</th>
                    <th class="col-dur">Jornada</th>
                    <th class="col-dur">Pausa</th>
                    <th class="col-dur">Extras</th>
                    <th class="col-dur">Reales</th>
                    <th class="col-dur">Tareas</th>
                    <th class="col-km">Km</th>
                    <th class="col-tipo">Tipo</th>
                </tr>
            </thead>
            <tbody>
            @foreach($dias as $dia)
            @php
                $badges = [];
                if ($dia['is_rotatorio'])       $badges[] = ['Desc. Fest.','#6f42c1'];
                elseif ($dia['is_fest_trab'])   $badges[] = ['Trab. fest.','#0d6efd'];
                elseif ($dia['tipo'])            $badges[] = [$dia['tipo']->nombre, tc($dia['tipo']->nombre, $tipo_color)];
                elseif ($dia['entrada'])         $badges[] = ['Trabajo', $color_trabajo];
                if ($dia['horario_tipo'] === 'descanso') $badges[] = ['Descanso', '#F3F4F6', '#6B7280'];
                $conflicto = count($badges) > 1;
            @endphp
            <tr class="{{ $dia['weekend'] ? 'wk' : '' }}" @if($conflicto) style="background:#ffff00;" @endif>
                <td style="font-weight:bold{{ $dia['is_festivo'] ? ';background:#ffe0e0;color:#cc0000' : '' }}">
                    {{ $dia['dow'] }} {{ $dia['num'] }}
                </td>
                <td>{{ $dia['entrada'] ?? '' }}</td>
                <td>{{ $dia['salida'] ?? '' }}</td>
                <td>{{ $dia['tf_min'] !== null ? IC::fmtMin($dia['tf_min']) : '' }}</td>
                <td @if($dia['pausa_resaltada']) style="font-weight:bold;color:#1a5fd4" @endif>
                    {{ $dia['p_min'] !== null && $dia['p_min'] > 0 ? $dia['p_min']."'" : '' }}
                </td>
                <td class="{{ ($dia['he_min'] ?? 0) > 0 ? 'he-pos' : (($dia['he_min'] ?? 0) < 0 ? 'he-neg' : '') }}">
                    {{ $dia['he_min'] !== null ? IC::fmtMin($dia['he_min'], true) : '' }}
                </td>
                @php $efMin = ($dia['tf_min'] !== null && $dia['p_min'] !== null) ? $dia['tf_min'] - $dia['p_min'] : $dia['tf_min']; @endphp
                <td>{{ $efMin !== null ? IC::fmtMin($efMin) : '' }}</td>
                <td>{{ $dia['ht_min'] > 0 ? IC::fmtMin($dia['ht_min']) : '' }}</td>
                <td>{{ $dia['km'] !== null && $dia['km'] > 0 ? number_format($dia['km'], 1, ',', '') : ($dia['entrada'] ? '0,00' : '') }}</td>
                <td>
                    @foreach($badges as $badge)
                        <div><span class="tipo-badge" style="background:{{ $badge[1] }};color:{{ $badge[2] ?? '#fff' }}">{{ $badge[0] }}</span></div>
                    @endforeach
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>

        <div class="firma-sec">
            <strong>Firma:</strong>
            <div class="firma-line"></div>
            <div class="nombre-nif">
                {{ $usuario->nombre ?? '' }}
                @if(!empty($usuario->dni)) con NIF {{ $usuario->dni }} @endif
            </div>
        </div>
    </div>

</div>
</body>
</html>
