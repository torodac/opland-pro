@php($esFianza = $titulo === 'Fianza')
<section class="section-card">
  <div class="sec-title">{{ $titulo }}</div>

  @if($esFianza)
    <div class="rounded-lg bg-amber-50 text-amber-800 text-xs px-3 py-2" style="margin-bottom:12px">
      Cambiar la decisión aquí no crea tareas SSCC ni envía correos. Para decidir, usa Dev. fianzas.
    </div>
    <form id="form-fianza" method="POST" action="{{ route('vm.reserva_form.fianza', [$project->slug, $reserva->id]) }}">
      @csrf
  @endif

  <div class="reserva-grid">
    @foreach($filas as $fila)
      <div class="field-row">
        <div class="field-lbl" style="display:flex;align-items:center;gap:6px;">
          @if(!empty($fila['icono']))
            @include('vm.partials.icono', ['nombre' => $fila['icono']])
          @endif
          {{ $fila['etiqueta'] }}
        </div>
        <div class="field-val">
          @if($fila['editable'])
            <span class="v-field">{{ $fila['valor'] }}</span>
            @switch($fila['campo'])
              @case('estado_fianza')
                <select name="estado_fianza" class="e-field f-input" style="display:none">
                  <option value="">— Selecciona —</option>
                  @foreach($estados as $e)
                    <option value="{{ $e }}" @selected(old('estado_fianza', $reserva->estado_fianza) === $e)>{{ $e }}</option>
                  @endforeach
                </select>
                @break
              @case('fianza_retencion')
                <input type="number" step="0.01" min="0" name="fianza_retencion"
                       value="{{ old('fianza_retencion', $reserva->fianza_retencion) }}"
                       class="e-field f-input" style="display:none">
                @break
              @case('fianza_motivo_retencion')
                <input type="text" maxlength="500" name="fianza_motivo_retencion"
                       value="{{ old('fianza_motivo_retencion', $reserva->fianza_motivo_retencion) }}"
                       class="e-field f-input" style="display:none">
                @break
              @case('fianza_usuario')
                <select name="fianza_usuario" class="e-field f-input" style="display:none">
                  <option value="">— Ninguno —</option>
                  @foreach($usuarios as $u)
                    <option value="{{ $u->id }}" @selected((string) old('fianza_usuario', $reserva->fianza_usuario) === (string) $u->id)>{{ $u->name }}</option>
                  @endforeach
                </select>
                @break
              @case('fianza_fecha')
                <input type="datetime-local" name="fianza_fecha"
                       value="{{ old('fianza_fecha', $reserva->fianza_fecha ? \Carbon\Carbon::parse($reserva->fianza_fecha)->format('Y-m-d\TH:i') : '') }}"
                       class="e-field f-input" style="display:none">
                @break
            @endswitch
          @else
            <span style="display:inline-flex;align-items:center;gap:6px;max-width:100%;">
              @if(!empty($fila['logo']))
                <img src="{{ $fila['logo'] }}" alt="{{ $fila['valor'] }}" title="{{ $fila['valor'] }}"
                     style="height:20px;width:auto;max-width:56px;object-fit:contain;flex-shrink:0">
              @endif
              <span style="min-width:0;overflow-wrap:anywhere;{{ $fila['valor'] === '—' ? 'color:#bbb;font-style:italic;' : '' }}">{{ $fila['valor'] }}</span>
            </span>
          @endif
        </div>
      </div>
    @endforeach
  </div>

  @if($esFianza)
    </form>
  @endif
</section>
