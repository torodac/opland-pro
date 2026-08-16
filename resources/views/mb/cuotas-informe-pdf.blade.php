<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin:2cm 1.5cm 0.5cm 1.5cm; }
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:DejaVu Sans,sans-serif; font-size:9pt; color:#222; padding:0.5cm 0.5cm 0; }

.pdf-header       { display:table; width:100%; margin-bottom:14pt; }
.pdf-header-left  { display:table-cell; vertical-align:middle; }
.pdf-header-right { display:table-cell; vertical-align:middle; text-align:right; }
.pdf-title        { font-size:15pt; font-weight:bold; color:#16232b; }
.pdf-subtitle     { font-size:9pt; color:#6b7280; margin-top:2pt; }
.pdf-meta         { font-size:8pt; color:#9ca3af; }

.section-title { font-size:10.5pt; font-weight:bold; color:#16232b; margin:16pt 0 6pt;
                 padding-bottom:3pt; border-bottom:1.2pt solid #e5e7eb; }
.section-note  { font-size:7.5pt; color:#9ca3af; margin-bottom:6pt; }

.box       { border:1pt solid #e5e7eb; border-radius:5pt; padding:8pt 10pt; margin-bottom:6pt; }
.box-label { font-size:7.5pt; color:#6b7280; text-transform:uppercase; letter-spacing:0.3pt; }
.box-value { font-size:13pt; font-weight:bold; color:#16232b; margin-top:2pt; }

.cards       { display:table; width:100%; table-layout:fixed; border-spacing:6pt 0; }
.card-cell   { display:table-cell; }
.card        { border:1pt solid #e5e7eb; border-radius:5pt; padding:8pt; text-align:center; }
.card-label  { font-size:7.5pt; color:#6b7280; }
.card-value  { font-size:11.5pt; font-weight:bold; margin-top:3pt; }
.card-total  .card-value { color:#16232b; }
.card-pend   .card-value { color:#a15c07; }
.card-dem    .card-value { color:#b91c1c; }
.card-inc    .card-value { color:#374151; }

.leyenda { font-size:8pt; color:#6b7280; margin-top:6pt; }
.leyenda .dot { display:inline-block; width:8pt; height:8pt; margin-right:4pt; vertical-align:middle; border-radius:1.5pt; }

.chart-table { width:100%; border-collapse:collapse; }
.chart-table td.bar-cell { text-align:center; vertical-align:bottom; padding:0 3pt; }
.chart-table td.label-cell { text-align:center; font-size:8pt; font-weight:bold; color:#16232b;
                              padding-top:4pt; border-top:1.2pt solid #9ca3af; }
.bar-value { font-size:6.5pt; color:#374151; margin-bottom:2pt; line-height:1.3; }
.bar-seg   { width:22pt; margin:0 auto; }

.grupo-title { font-size:9pt; font-weight:bold; color:#16232b; background:#f3f4f6;
               padding:4pt 6pt; margin-top:8pt; border-radius:3pt; }
.tbl-viv    { width:100%; border-collapse:collapse; font-size:8pt; margin-top:2pt; }
.tbl-viv td { padding:2.5pt 6pt; border-bottom:0.75pt solid #f0f0f0; }
.tbl-viv td.importe { text-align:right; white-space:nowrap; }
.tbl-viv .perdido { color:#b91c1c; font-size:7pt; }

.sin-datos { font-size:8.5pt; color:#9ca3af; font-style:italic; padding:6pt 0; }
</style>
</head>
<body>

<div class="pdf-header">
    <div class="pdf-header-left">
        <div class="pdf-title">Informe de cuotas — {{ strtoupper($project->slug) }}</div>
        <div class="pdf-subtitle">Ejercicio actual: {{ $ejercicioActual }}</div>
    </div>
    <div class="pdf-header-right">
        <div class="pdf-meta">Generado el {{ $fechaGeneracion->format('d/m/Y H:i') }}</div>
    </div>
</div>

{{-- 1. Fichero importado más reciente --}}
<div class="section-title">Importe pendiente según fichero importado más reciente</div>
@if($ultimaExportacion)
    <div class="box">
        <div class="box-label">
            {{ \Illuminate\Support\Carbon::parse($ultimaExportacion)->format('d/m/Y') }}
            @if($ficherosUltima->isNotEmpty())
                &middot; {{ $ficherosUltima->implode(', ') }}
            @endif
        </div>
        <div class="box-value">{{ number_format($pendienteFichero, 2, ',', '.') }} €</div>
    </div>
@else
    <div class="sin-datos">No hay ningún fichero importado todavía.</div>
@endif

{{-- 2. Clasificación Opland --}}
<div class="section-title">Importe según clasificación Opland</div>
<div class="section-note">El Total pendiente debería coincidir con el importe del fichero de arriba.</div>
<div class="cards">
    <div class="card-cell">
        <div class="card card-total">
            <div class="card-label">Total pendiente</div>
            <div class="card-value">{{ number_format($pendienteTotalOpland, 2, ',', '.') }} €</div>
        </div>
    </div>
    <div class="card-cell">
        <div class="card card-inc">
            <div class="card-label">Incobrable</div>
            <div class="card-value">{{ number_format($incobrableOpland, 2, ',', '.') }} €</div>
        </div>
    </div>
    <div class="card-cell">
        <div class="card card-dem">
            <div class="card-label">Demandada</div>
            <div class="card-value">{{ number_format($demandadaOpland, 2, ',', '.') }} €</div>
        </div>
    </div>
    <div class="card-cell">
        <div class="card card-pend">
            <div class="card-label">Pendiente</div>
            <div class="card-value">{{ number_format($pendienteEstadoOpland, 2, ',', '.') }} €</div>
        </div>
    </div>
</div>

{{-- 3. Gráfico apilado por ejercicio --}}
<div class="section-title">Pendiente + demandado por ejercicio</div>
@if(empty($graficoBarras))
    <div class="sin-datos">No hay cuotas pendientes o demandadas.</div>
@else
    <table class="chart-table">
        <tr>
            @foreach($graficoBarras as $b)
                <td class="bar-cell" style="width:{{ round(100 / count($graficoBarras), 2) }}%; height:{{ $maxAlturaPx + 34 }}px">
                    <div class="bar-value">
                        @if($b['fuera_escala'])
                            <span style="color:#6b7280">&#9650;</span><br>
                        @endif
                        @if($b['demandado'] > 0)
                            <span style="color:#1e3a8a">{{ number_format($b['demandado'], 0, ',', '.') }}€</span><br>
                        @endif
                        <span style="color:#2563eb">{{ number_format($b['pendiente'], 0, ',', '.') }}€</span>
                    </div>
                    <div class="bar-seg" style="background:#1e3a8a; height:{{ $b['demandado_px'] }}px"></div>
                    <div class="bar-seg" style="background:#93c5fd; height:{{ $b['pendiente_px'] }}px"></div>
                </td>
            @endforeach
        </tr>
        <tr>
            @foreach($graficoBarras as $b)
                <td class="label-cell">{{ $b['ejercicio'] }}</td>
            @endforeach
        </tr>
    </table>
    <div class="leyenda">
        <span class="dot" style="background:#93c5fd"></span>Pendiente
        &nbsp;&nbsp;&nbsp;
        <span class="dot" style="background:#1e3a8a"></span>Demandado
    </div>
@endif

{{-- 4. Listado "A demandar" (mismo agrupamiento que el tooltip) --}}
<div class="section-title">Viviendas a demandar este ejercicio</div>
@if(empty($aDemandarGrupos))
    <div class="sin-datos">No hay viviendas próximas a demandar.</div>
@else
    @foreach($aDemandarGrupos as $ejercicio => $viviendas)
        <div class="grupo-title">{{ $ejercicio }}</div>
        <table class="tbl-viv">
            @foreach($viviendas as $v)
                <tr>
                    <td>{{ $v['nombre'] }}</td>
                    <td class="importe">
                        {{ number_format($v['recuperable'], 2, ',', '.') }} €
                        @if($v['perdido'] > 0)
                            <span class="perdido">(perdido: {{ number_format($v['perdido'], 2, ',', '.') }} €)</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endforeach
    <div class="box" style="margin-top:8pt">
        <div class="box-label">Total perdido (prescrito, fuera del plazo de 5 años)</div>
        <div class="box-value" style="color:#b91c1c">{{ number_format($totalPerdidoADemandar, 2, ',', '.') }} €</div>
    </div>
@endif

</body>
</html>
