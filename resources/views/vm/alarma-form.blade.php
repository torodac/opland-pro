@php
    $esNuevo = $alarma === null;
    $accion  = $esNuevo
        ? route('vm.alarma.crear', $project->slug)
        : route('vm.alarma.guardar', [$project->slug, $alarma->id]);
    // En la edición los campos sensibles salen vacíos: el formulario nunca muestra el valor
    // actual, porque enseñarlo aquí sería una forma de leerlo sin pasar por el registro de
    // accesos de la ficha.
    $val = fn($campo, $def = '') => old($campo, $esNuevo ? $def : ($alarma->{$campo} ?? ''));
@endphp

<x-app-layout
    :breadcrumb="$esNuevo
        ? [['label' => 'Alarmas', 'url' => route('vm.alarmas', $project->slug)], ['label' => 'Nueva alarma', 'url' => '']]
        : [['label' => 'Alarmas', 'url' => route('vm.alarmas', $project->slug)], ['label' => $alarma->propiedad, 'url' => route('vm.alarma', [$project->slug, $alarma->id])], ['label' => 'Editar', 'url' => '']]"
    :project="$project">

<style>
.al-card{background:#fff;border:0.5px solid rgba(0,0,0,.08);border-radius:12px;padding:1.1rem 1.25rem;margin-bottom:12px}
.dark .al-card{background:#1a1a1a;border-color:rgba(255,255,255,.08)}
.al-title{font-size:11px;font-weight:600;color:#999;text-transform:uppercase;letter-spacing:.06em;margin:0 0 12px}
.al-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}
.al-f{display:flex;flex-direction:column;gap:4px}
.al-f label{font-size:11px;color:#888}
.al-f input,.al-f select,.al-f textarea{font-size:13px;padding:6px 9px;border-radius:6px;
    border:0.5px solid rgba(0,0,0,.15);background:#fff;color:#333;font-family:inherit;width:100%}
.dark .al-f input,.dark .al-f select,.dark .al-f textarea{background:#111;border-color:rgba(255,255,255,.15);color:#ddd}
.al-f input:focus,.al-f select:focus,.al-f textarea:focus{outline:none;border-color:#F97316}
.al-hint{font-size:11px;color:#888;margin:2px 0 0}
.al-btn{font-size:13px;padding:6px 14px;border-radius:6px;cursor:pointer;text-decoration:none;
        border:0.5px solid rgba(0,0,0,.15);background:#fff;color:#333;display:inline-block}
.dark .al-btn{background:#1a1a1a;border-color:rgba(255,255,255,.15);color:#ddd}
.al-btn-ok{border-color:#B7E0C4;background:#F1FAF4;color:#1B7F3B}
.al-err{font-size:12px;color:#B91C1C;background:#FEF2F2;border:0.5px solid #FCA5A5;
        border-radius:8px;padding:.7rem 1rem;margin-bottom:1rem}
.al-check{display:flex;align-items:center;gap:6px;font-size:12px;color:#666;margin-top:6px}
.al-check input{width:auto}
</style>

<div style="padding:0 0 3rem;">

@if($errors->any())
    <div class="al-err">
        @foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach
    </div>
@endif

<form method="POST" action="{{ $accion }}" autocomplete="off">
@csrf

  {{-- ── La alarma y su propiedad ──────────────────────────────────────── --}}
  <div class="al-card">
    <div class="al-title">Alarma</div>
    <div class="al-grid">

      <div class="al-f">
        <label for="id_propiedades">Propiedad *</label>
        @if($esNuevo)
          <select name="id_propiedades" id="id_propiedades" required>
            <option value="">— Elige una propiedad —</option>
            @foreach($propiedades as $p)
              <option value="{{ $p->id }}" @selected((string) old('id_propiedades') === (string) $p->id)>
                {{ $p->nombre }}
              </option>
            @endforeach
          </select>
          <p class="al-hint">Solo las que todavía no tienen alarma: hay una por propiedad.</p>
        @else
          {{-- La propiedad no se cambia: mover la alarma de casa convertiría el historial de
               accesos de una en el de otra. --}}
          <input type="text" value="{{ $alarma->propiedad }}" disabled>
          <p class="al-hint">La propiedad no se puede cambiar. Si está mal, borra la alarma y créala en la correcta.</p>
        @endif
      </div>

      <div class="al-f">
        <label for="empresa">Empresa de seguridad *</label>
        <input type="text" name="empresa" id="empresa" value="{{ $val('empresa') }}" required maxlength="255">
      </div>

      <div class="al-f">
        <label for="tipo_alarma">Tipo de alarma</label>
        <input type="text" name="tipo_alarma" id="tipo_alarma" value="{{ $val('tipo_alarma') }}" maxlength="255">
      </div>

      <div class="al-f">
        <label for="num_contrato">Número de contrato</label>
        <input type="text" name="num_contrato" id="num_contrato" value="{{ $val('num_contrato') }}" maxlength="255">
      </div>

      <div class="al-f">
        <label for="titular">Titular de la alarma</label>
        <input type="text" name="titular" id="titular" value="{{ $val('titular') }}" maxlength="255">
      </div>

      <div class="al-f">
        <label for="titular_doc">DNI o NIE del titular</label>
        <input type="text" name="titular_doc" id="titular_doc" value="{{ $val('titular_doc') }}" maxlength="20">
      </div>

    </div>
  </div>

  {{-- ── Los tres contactos, en orden de llamada ───────────────────────── --}}
  <div class="al-card">
    <div class="al-title">Contactos para incidencias</div>
    <div class="al-grid">
      @foreach([1 => 'Primer', 2 => 'Segundo', 3 => 'Tercer'] as $n => $orden)
        <div>
          <div class="al-f">
            <label for="contacto{{ $n }}_nombre">{{ $orden }} contacto · nombre</label>
            <input type="text" name="contacto{{ $n }}_nombre" id="contacto{{ $n }}_nombre"
                   value="{{ $val("contacto{$n}_nombre") }}" maxlength="255">
          </div>
          <div class="al-f" style="margin-top:8px">
            <label for="contacto{{ $n }}_telefono">{{ $orden }} contacto · teléfono</label>
            <input type="text" name="contacto{{ $n }}_telefono" id="contacto{{ $n }}_telefono"
                   value="{{ $val("contacto{$n}_telefono") }}" maxlength="60">
          </div>
        </div>
      @endforeach
    </div>
  </div>

  {{-- ── Lo sensible ───────────────────────────────────────────────────── --}}
  <div class="al-card">
    <div class="al-title">Credenciales</div>
    <div class="al-grid">

      <div class="al-f">
        <label for="app_usuario">Usuario en la app</label>
        <input type="text" name="app_usuario" id="app_usuario" value="{{ $val('app_usuario') }}" maxlength="255">
      </div>

      @foreach(['palabra_clave' => 'Palabra clave', 'app_password' => 'Contraseña en la app'] as $campo => $etiqueta)
        @php $yaTiene = !$esNuevo && $alarma->{'tiene_' . $campo}; @endphp
        <div class="al-f">
          <label for="{{ $campo }}">{{ $etiqueta }}</label>
          {{-- autocomplete="new-password": sin esto el navegador ofrece las credenciales
               guardadas del propio Opland en estos dos campos. --}}
          <input type="text" name="{{ $campo }}" id="{{ $campo }}" value="" maxlength="255"
                 autocomplete="new-password"
                 placeholder="{{ $yaTiene ? 'Guardada · déjalo vacío para no cambiarla' : '' }}">
          @if($yaTiene)
            <label class="al-check">
              <input type="checkbox" name="borrar_{{ $campo }}" value="1">
              Borrar el valor guardado
            </label>
          @endif
        </div>
      @endforeach

    </div>
    <p class="al-hint" style="margin-top:10px">
      Estos dos datos se guardan cifrados y no vuelven a mostrarse aquí. Para consultarlos, usa
      la ficha: cada consulta queda registrada.
    </p>
  </div>

  <div class="al-card">
    <div class="al-f">
      <label for="observaciones">Observaciones</label>
      <textarea name="observaciones" id="observaciones" rows="3" maxlength="4000">{{ $val('observaciones') }}</textarea>
    </div>
  </div>

  <div style="display:flex;gap:8px">
    <button type="submit" class="al-btn al-btn-ok">{{ $esNuevo ? 'Crear alarma' : 'Guardar cambios' }}</button>
    <a class="al-btn" href="{{ $esNuevo ? route('vm.alarmas', $project->slug) : route('vm.alarma', [$project->slug, $alarma->id]) }}">Cancelar</a>
  </div>

</form>
</div>
</x-app-layout>
