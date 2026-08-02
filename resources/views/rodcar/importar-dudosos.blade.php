<x-app-layout :project="$project" :breadcrumb="[['label'=>'Importar movimientos','url'=>route('rodcar.importar', $project->slug)],['label'=>'Revisar dudosos','url'=>'']]">

<div style="margin-bottom:16px">
  <h2 style="font-size:19px;margin-bottom:4px;font-weight:700">Movimientos dudosos</h2>
  <p style="color:#52697a;font-size:12.5px;margin:0">
    {{ $resumen['nuevos'] }} nuevos ya importados, {{ $resumen['duplicados'] }} duplicados descartados automáticamente.
    Estos {{ $resumen['dudosos'] }} tienen la misma fecha e importe que un movimiento ya existente, pero con un concepto distinto —
    revisa cuáles quieres importar igualmente (podría ser un duplicado con el texto ligeramente cambiado, o una coincidencia real).
  </p>
  @if($mensajeEnlace)
    <p style="color:#7e93a1;font-size:12px;margin-top:6px">{{ $mensajeEnlace }}</p>
  @endif
</div>

<form method="POST" action="{{ route('rodcar.importar.confirmar-dudosos', $project->slug) }}">
  @csrf
  <input type="hidden" name="token" value="{{ $token }}">

  <div class="rv-card">
    <table class="rv-table">
      <thead>
        <tr>
          <th style="width:36px"><input type="checkbox" id="rv-marcar-todos"></th>
          <th style="width:90px">Fecha</th>
          <th>Concepto</th>
          <th style="width:100px" class="num">Importe</th>
        </tr>
      </thead>
      <tbody>
        @foreach($dudosos as $i => $d)
        <tr>
          <td><input type="checkbox" name="indices[]" value="{{ $i }}"></td>
          <td>{{ \Carbon\Carbon::parse($d['fecha'])->format('d/m/Y') }}</td>
          <td>{{ $d['concepto'] }}</td>
          <td class="num">{{ number_format($d['importe'], 2, ',', '.') }} €</td>
        </tr>
        @endforeach
      </tbody>
    </table>
  </div>

  <div style="margin-top:16px;display:flex;gap:10px">
    <button type="submit" class="rv-btn">Importar seleccionados</button>
    <a href="{{ route('rodcar.importar', $project->slug) }}" style="align-self:center;font-size:12.5px;color:#7e93a1">Descartar el resto y terminar</a>
  </div>
</form>

<script>
document.getElementById('rv-marcar-todos').addEventListener('change', function () {
  document.querySelectorAll('input[name="indices[]"]').forEach(cb => cb.checked = this.checked);
});
</script>

<style>
.rv-card{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);overflow:hidden}
.rv-table{width:100%;border-collapse:collapse;font-size:12.5px}
.rv-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 12px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.rv-table td{padding:8px 12px;border-bottom:1px solid #eaf1f6;vertical-align:middle}
.rv-table td.num, .rv-table th.num{text-align:right}
.rv-btn{padding:7px 14px;font-size:12px;font-weight:600;background:#f97316;color:#fff;border:none;border-radius:6px;cursor:pointer}
.rv-btn:hover{background:#ea580c}
</style>

</x-app-layout>
