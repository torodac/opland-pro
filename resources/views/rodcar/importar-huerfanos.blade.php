<x-app-layout :project="$project" :breadcrumb="[['label'=>'Importar movimientos','url'=>route('rodcar.importar', $project->slug)],['label'=>'Vincular lote','url'=>'']]">

<div style="margin-bottom:16px">
  <h2 style="font-size:19px;margin-bottom:4px;font-weight:700">Vincular lote: {{ $loteRow->nombre_archivo }}</h2>
  <p style="color:#52697a;font-size:12.5px;margin:0">
    Estos {{ $huerfanos->count() }} movimientos de tarjeta no se pudieron enlazar automáticamente con ningún cargo de la cuenta.
    Elige a qué movimiento de la cuenta corresponden y se vincularán todos juntos.
  </p>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;align-items:start">

  <div>
    <div style="margin-bottom:8px;font-size:12.5px;font-weight:700;color:#16232b">
      Movimientos sin enlazar
      <span style="font-weight:400;color:#7e93a1">— suma {{ number_format($huerfanos->sum('importe'), 2, ',', '.') }} €</span>
    </div>
    <div class="rv-card">
      <table class="rv-table">
        <thead><tr><th>Fecha</th><th>Concepto</th><th class="num">Importe</th></tr></thead>
        <tbody>
          @foreach($huerfanos as $h)
          <tr>
            <td>{{ \Carbon\Carbon::parse($h->fecha_operacion)->format('d/m/Y') }}</td>
            <td>{{ $h->nombre }}</td>
            <td class="num">{{ number_format($h->importe, 2, ',', '.') }} €</td>
          </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  <div>
    <form method="POST" action="{{ route('rodcar.importar.vincular', [$project->slug, $loteRow->id]) }}">
      @csrf
      <div style="margin-bottom:8px;font-size:12.5px;font-weight:700;color:#16232b">Movimiento de la cuenta al que corresponde</div>
      <div class="rv-card" style="max-height:420px;overflow-y:auto">
        <table class="rv-table">
          <thead><tr><th style="width:30px"></th><th>Fecha</th><th>Concepto</th><th class="num">Importe</th></tr></thead>
          <tbody>
            @foreach($candidatos as $c)
            <tr>
              <td><input type="radio" name="id_movs" value="{{ $c->id }}" required></td>
              <td>{{ \Carbon\Carbon::parse($c->fecha_operacion)->format('d/m/Y') }}</td>
              <td>{{ $c->nombre }}</td>
              <td class="num">{{ number_format($c->importe, 2, ',', '.') }} €</td>
            </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <button type="submit" class="rv-btn" style="margin-top:12px">Vincular</button>
    </form>
  </div>

</div>

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
