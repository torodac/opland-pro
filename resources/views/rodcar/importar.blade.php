<x-app-layout :project="$project" :breadcrumb="[['label'=>'Importar movimientos','url'=>'']]">

<div style="margin-bottom:16px">
  <h2 style="font-size:19px;margin-bottom:4px;font-weight:700">Importar movimientos</h2>
  <p style="color:#52697a;font-size:12.5px;margin:0">
    Sube el extracto de cuenta o de tarjeta y se importará solo lo que no exista ya en movs / movs_detalle.
  </p>
</div>

@if(session('success'))
  <div style="margin-bottom:14px;padding:10px 14px;background:#f0fdf4;border:1px solid #86efac;color:#166534;border-radius:8px;font-size:12.5px">
    {{ session('success') }}
  </div>
@endif

@if($errors->any())
  <div style="margin-bottom:14px;padding:10px 14px;background:#fef2f2;border:1px solid #fca5a5;color:#991b1b;border-radius:8px;font-size:12.5px">
    {{ $errors->first() }}
  </div>
@endif

<div class="rv-card" style="padding:18px;margin-bottom:20px">
  <form method="POST" action="{{ route('rodcar.importar.subir', $project->slug) }}" enctype="multipart/form-data" style="display:flex;gap:12px;align-items:end;flex-wrap:wrap">
    @csrf
    <div>
      <label style="display:block;font-size:11px;color:#7e93a1;margin-bottom:4px">Tipo de origen</label>
      <select name="tipo" required class="rv-select" style="width:220px">
        <option value="kutxa_cuenta">Kutxa — extracto de cuenta</option>
        <option value="kutxa_tarjeta">Kutxa — extracto de tarjeta</option>
        <option value="mediolanum_cuenta">Mediolanum — extracto de cuenta</option>
        <option value="mediolanum_tarjeta">Mediolanum — liquidación de tarjeta (PDF)</option>
      </select>
    </div>
    <div>
      <label style="display:block;font-size:11px;color:#7e93a1;margin-bottom:4px">Cuenta</label>
      <select name="id_movs_cuenta" required class="rv-select" style="width:180px">
        @foreach($cuentas as $c)
          <option value="{{ $c->id }}">{{ $c->nombre }}</option>
        @endforeach
      </select>
    </div>
    <div>
      <label style="display:block;font-size:11px;color:#7e93a1;margin-bottom:4px">Fichero (.xls o .pdf)</label>
      <input type="file" name="fichero" required accept=".xls,.xlsx,.pdf" style="font-size:12.5px">
    </div>
    <button type="submit" class="rv-btn">Analizar e importar</button>
  </form>
</div>

@if($huerfanos->isNotEmpty())
<div style="margin-bottom:10px;font-size:13px;font-weight:700;color:#16232b">Lotes con movimientos de tarjeta sin enlazar</div>
<div class="rv-card" style="margin-bottom:20px">
  <table class="rv-table">
    <thead><tr><th>Fecha</th><th>Cuenta</th><th>Fichero</th><th style="width:120px"></th></tr></thead>
    <tbody>
      @foreach($huerfanos as $h)
      <tr>
        <td>{{ \Carbon\Carbon::parse($h->createdat)->format('d/m/Y') }}</td>
        <td>{{ $h->cuenta_nombre }}</td>
        <td>{{ $h->nombre_archivo }}</td>
        <td><a href="{{ route('rodcar.importar.huerfanos', [$project->slug, $h->id]) }}" class="rv-btn" style="display:inline-block;background:#16232b;text-decoration:none">Vincular</a></td>
      </tr>
      @endforeach
    </tbody>
  </table>
</div>
@endif

<div style="margin-bottom:10px;font-size:13px;font-weight:700;color:#16232b">Últimas importaciones</div>
<div class="rv-card">
  <table class="rv-table">
    <thead><tr><th>Fecha</th><th>Tipo</th><th>Cuenta</th><th>Fichero</th><th class="num">Nuevos</th><th class="num">Duplicados</th><th class="num">Dudosos</th><th class="num">Importe</th></tr></thead>
    <tbody>
      @forelse($lotes as $l)
      <tr>
        <td>{{ \Carbon\Carbon::parse($l->createdat)->format('d/m/Y H:i') }}</td>
        <td>{{ str_replace('_', ' ', $l->tipo_origen) }}</td>
        <td>{{ $l->cuenta_nombre }}</td>
        <td>{{ $l->nombre_archivo }}</td>
        <td class="num">{{ $l->total_nuevas }}</td>
        <td class="num">{{ $l->total_duplicadas }}</td>
        <td class="num">{{ $l->total_dudosas_importadas }}</td>
        <td class="num">{{ number_format($l->total_importe, 2, ',', '.') }} €</td>
      </tr>
      @empty
      <tr><td colspan="8" style="text-align:center;color:#9ca3af;padding:20px">Todavía no se ha importado nada.</td></tr>
      @endforelse
    </tbody>
  </table>
</div>

<style>
.rv-card{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);overflow:hidden}
.rv-table{width:100%;border-collapse:collapse;font-size:12.5px}
.rv-table th{text-align:left;font-size:10.5px;text-transform:uppercase;letter-spacing:.04em;color:#7e93a1;padding:10px 12px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.rv-table td{padding:8px 12px;border-bottom:1px solid #eaf1f6;vertical-align:middle}
.rv-table td.num, .rv-table th.num{text-align:right}
.rv-select{font-size:12px;padding:6px 8px;border:1px solid #dce6ee;border-radius:6px;background:#fff}
.rv-btn{padding:7px 14px;font-size:12px;font-weight:600;background:#f97316;color:#fff;border:none;border-radius:6px;cursor:pointer}
.rv-btn:hover{background:#ea580c}
</style>

</x-app-layout>
