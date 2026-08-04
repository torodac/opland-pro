<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
@page { margin: 25px 35px; }
body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #111; }
.hoja { page-break-after: always; position: relative; height: 1055px; }
.hoja:last-child { page-break-after: avoid; }

.cabecera-titulo { text-align: center; font-size: 15px; font-weight: bold; margin-bottom: 10px; }

.cabecera-fila2 { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
.cabecera-fila2 td { vertical-align: top; }
.logo-cell img { height: 58px; }
.caja-cell { text-align: right; }
.caja-vivienda-table { margin-left: auto; border-collapse: collapse; }
.caja-vivienda-label { font-size: 11px; text-align: right; vertical-align: top; padding-top: 13px; padding-right: 8px; white-space: nowrap; }
.caja-vivienda-td { border: 1px solid #000; width: 190px; height: 26px; padding: 4px 8px; }
.hoja-num-texto { text-align: right; font-size: 12px; font-weight: bold; margin-top: 5px; }

.pregunta-box { border: 1px solid #000; width: 100%; border-collapse: collapse; }
.pregunta-box td { padding: 8px; vertical-align: top; }
.pregunta-numero { font-size: 44px; width: 52px; text-align: center; vertical-align: middle; }
.qr-cell { width: 56px; text-align: center; }
.pregunta-box td.qr-cell { padding-top: 26px; }
.qr-cell img { width: 46px; height: 46px; }
.pregunta-texto-cell { padding: 8px 12px; }
.pregunta-texto { font-size: 13px; text-align: left; min-height: 30px; margin-bottom: 8px; }
.opciones-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
.opciones-table td { font-size: 11px; font-weight: bold; padding: 0; }
.opcion-no { width: 30%; text-align: right; }
.opcion-espaciador { width: 46px; }
.opcion-abs { width: 20%; text-align: center; color: #555; }
.opcion-si { width: 30%; text-align: left; }

.firma-label { position: absolute; left: 50%; bottom: 76px; font-size: 11px; white-space: nowrap; }
.firma-linea { position: absolute; right: 0; bottom: 0; width: 230px; border-bottom: 1px solid #000; height: 1px; }
</style>
</head>
<body>
@foreach($hojas as $numeroHoja)
<div class="hoja">
  <div class="cabecera-titulo">ASAMBLEA {{ mb_strtoupper($asamblea->tipo) }} DEL {{ $fechaTexto }}</div>

  <table class="cabecera-fila2">
    <tr>
      <td class="logo-cell" style="width:100px;">
        @if($logoDataUri)
        <img src="{{ $logoDataUri }}">
        @endif
      </td>
      <td class="caja-cell">
        <table class="caja-vivienda-table"><tr>
          <td class="caja-vivienda-label">Vivienda</td>
          <td class="caja-vivienda-td"></td>
        </tr></table>
        <div class="hoja-num-texto">Hoja de voto {{ str_pad($numeroHoja, 3, '0', STR_PAD_LEFT) }}</div>
      </td>
    </tr>
  </table>

  @foreach($preguntas as $p)
  <table class="pregunta-box" style="margin-bottom: {{ $espacioPregunta }}px">
    <tr>
      <td class="pregunta-numero">{{ $p->numero_pregunta }}</td>
      <td class="qr-cell">
        <img src="{{ $qrs[$numeroHoja][$p->numero_pregunta]['N'] }}">
      </td>
      <td class="pregunta-texto-cell">
        <div class="pregunta-texto">{!! $p->texto !!}</div>
        <table class="opciones-table">
          <tr>
            <td class="opcion-no">NO</td>
            <td class="opcion-espaciador"></td>
            <td class="opcion-abs">ABSTENCIÓN</td>
            <td class="opcion-espaciador"></td>
            <td class="opcion-si">SÍ</td>
          </tr>
        </table>
      </td>
      <td class="qr-cell">
        <img src="{{ $qrs[$numeroHoja][$p->numero_pregunta]['S'] }}">
      </td>
    </tr>
  </table>
  @endforeach

  <div class="firma-label">Firma del asistente (opcional):</div>
  <div class="firma-linea"></div>
</div>
@endforeach
</body>
</html>
