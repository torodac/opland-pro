<x-app-layout :breadcrumb="$breadcrumb" :project="$project">

<style>
.form-row{margin-bottom:14px;max-width:360px}
.form-label{font-size:12px;color:#888;margin:0 0 4px;display:block;}
.form-row input,.form-row select{width:100%;box-sizing:border-box;border:0.5px solid rgba(0,0,0,.15);border-radius:6px;padding:7px 10px;font-size:13px;background:#fff;}
.form-check{display:flex;align-items:center;gap:6px;margin-bottom:14px}
.form-error{font-size:12px;color:#b91c1c;margin-top:4px}
</style>

<div class="bg-white rounded-xl border border-gray-200 p-6" style="max-width:480px">

  <div id="form-error" style="display:none;background:#FCEBEB;color:#A32D2D;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px"></div>

  <form id="form-fichaje-nuevo" method="POST" action="{{ route('vm.fichaje_form.store', $project->slug) }}">
    @csrf

    <div class="form-row">
      <label class="form-label">Empleado</label>
      <select name="control_user" required @if($usuarios->count() <= 1) disabled @endif>
        @foreach($usuarios as $u)
          <option value="{{ $u->id }}" @selected($u->id == old('control_user', $controlUserPorDefecto))>{{ $u->nombre }}</option>
        @endforeach
      </select>
      @if($usuarios->count() <= 1)
        <input type="hidden" name="control_user" value="{{ $controlUserPorDefecto }}">
      @endif
    </div>

    <div class="form-row">
      <label class="form-label">Fecha</label>
      <input type="date" name="fecha_fichaje" value="{{ old('fecha_fichaje', $fechaPorDefecto) }}" required>
    </div>

    <div class="form-row">
      <label class="form-label">Entrada</label>
      <input type="time" name="hora_inicio" value="{{ old('hora_inicio') }}">
    </div>

    <div class="form-row">
      <label class="form-label">Inicio pausa</label>
      <input type="time" name="pausa_inicio" value="{{ old('pausa_inicio') }}">
    </div>

    <div class="form-row">
      <label class="form-label">Fin pausa</label>
      <input type="time" name="pausa_fin" value="{{ old('pausa_fin') }}">
    </div>

    <div class="form-row">
      <label class="form-label">Salida</label>
      <input type="time" name="hora_fin" value="{{ old('hora_fin') }}">
    </div>

    <div class="form-check">
      <input type="checkbox" id="festivo" name="festivo" value="1" @checked(old('festivo'))>
      <label for="festivo" style="font-size:13px">Festivo trabajado</label>
    </div>

    <div class="form-check">
      <input type="checkbox" id="fuera_de_turno" name="fuera_de_turno" value="1" @checked(old('fuera_de_turno'))>
      <label for="fuera_de_turno" style="font-size:13px">Fuera de turno</label>
    </div>

    <div class="form-row">
      <label class="form-label">Observación</label>
      <input type="text" name="observacion" value="{{ old('observacion') }}" maxlength="1000">
    </div>

    <div style="display:flex;gap:8px;margin-top:20px">
      <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium rounded-lg transition-colors">
        Guardar
      </button>
      <a href="{{ route('listado', [$project->slug, 'fichaje']) }}"
         class="inline-flex items-center gap-1.5 px-4 py-2 border border-gray-200 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition-colors">
        Cancelar
      </a>
    </div>
  </form>
</div>

<script>
document.getElementById('form-fichaje-nuevo').addEventListener('submit', async function (e) {
    e.preventDefault();
    const errorBox = document.getElementById('form-error');
    errorBox.style.display = 'none';

    const res = await window.fetchConAprobacion(this.action, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
        body: new FormData(this),
    });
    if (!res) return; // cancelado o bloqueado (fetchConAprobacion ya mostró el aviso)

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        errorBox.textContent = data.error || 'No se pudo guardar el fichaje.';
        errorBox.style.display = 'block';
        return;
    }
    if (data.aviso_aprobacion) alert(data.aviso_aprobacion);
    window.location = data.redirect;
});
</script>

</x-app-layout>
