{{--
    Modal de alta de fichaje, reutilizable desde cualquier pantalla (listado por usuario/mes y
    dashboard). Es autocontenida a propósito -- estilos, marcado y JS propios con prefijo fmod--
    porque cada página define sus propias clases de modal y sus propios openModal/closeModal, y
    aquí no podemos dar por hecho ninguno.

    Requiere en la vista que la incluya:
      $project  : el proyecto
      $usuarios : colección de vm_usuarios visibles (id, nombre)

    Uso desde JS:
      abrirFichajeNuevo({ usuario: 12, fecha: '2026-09-01', onGuardado: () => location.reload() })
--}}
<style>
.fmod-overlay { position:fixed; inset:0; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:1000; }
.fmod-overlay.open { display:flex; }
.fmod { background:#fff; border-radius:14px; padding:20px 22px; width:min(420px,calc(100vw - 32px)); max-height:calc(100vh - 64px); overflow-y:auto; box-shadow:0 10px 40px rgba(0,0,0,.18); }
.fmod-title { font-size:15px; font-weight:600; color:#111827; margin:0 0 14px; }
.fmod-row { margin-bottom:12px; }
.fmod-label { font-size:12px; color:#888; margin:0 0 4px; display:block; }
.fmod input, .fmod select { width:100%; box-sizing:border-box; border:0.5px solid rgba(0,0,0,.15); border-radius:6px; padding:7px 10px; font-size:13px; background:#fff; }
.fmod-error { display:none; background:#FCEBEB; color:#A32D2D; border-radius:8px; padding:9px 13px; margin-bottom:12px; font-size:13px; }
.fmod-foot { display:flex; gap:8px; margin-top:18px; }
.fmod-save { flex:1; padding:8px; font-size:13px; font-weight:600; background:#1D4ED8; color:#fff; border:none; border-radius:8px; cursor:pointer; }
.fmod-save:hover { background:#1E40AF; }
.fmod-save:disabled { opacity:.6; cursor:not-allowed; }
.fmod-cancel { padding:8px 14px; font-size:13px; background:#fff; border:1px solid #e5e7eb; border-radius:8px; cursor:pointer; color:#6B7280; }
</style>

<div class="fmod-overlay" id="fmod-overlay">
  <div class="fmod">
    <p class="fmod-title">Nuevo fichaje</p>
    <div class="fmod-error" id="fmod-error"></div>

    <form id="fmod-form">
      @csrf
      <div class="fmod-row">
        <label class="fmod-label">Empleado</label>
        <select name="control_user" id="fmod-usuario" required>
          @foreach($usuarios as $u)
            <option value="{{ $u->id }}">{{ $u->nombre }}</option>
          @endforeach
        </select>
      </div>
      <div class="fmod-row">
        <label class="fmod-label">Fecha</label>
        <input type="date" name="fecha_fichaje" id="fmod-fecha" required>
      </div>
      <div class="fmod-row">
        <label class="fmod-label">Entrada</label>
        <input type="time" name="hora_inicio" id="fmod-hi" required>
      </div>
      <div class="fmod-row">
        <label class="fmod-label">Inicio pausa</label>
        <input type="time" name="pausa_inicio" id="fmod-pi">
      </div>
      <div class="fmod-row">
        <label class="fmod-label">Fin pausa</label>
        <input type="time" name="pausa_fin" id="fmod-pf">
      </div>
      <div class="fmod-row">
        <label class="fmod-label">Salida</label>
        <input type="time" name="hora_fin" id="fmod-hf">
      </div>
      <div class="fmod-row">
        <label class="fmod-label">Observación</label>
        <input type="text" name="observacion" id="fmod-obs" maxlength="1000">
      </div>

      <div class="fmod-foot">
        <button type="submit" class="fmod-save" id="fmod-save">Guardar</button>
        <button type="button" class="fmod-cancel" onclick="cerrarFichajeNuevo()">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
    const URL_STORE = '{{ route('vm.fichaje_form.store', $project->slug) }}';
    let alGuardar = null;

    window.abrirFichajeNuevo = function (opts = {}) {
        const form = document.getElementById('fmod-form');
        form.reset();
        document.getElementById('fmod-error').style.display = 'none';
        if (opts.usuario) document.getElementById('fmod-usuario').value = opts.usuario;
        document.getElementById('fmod-fecha').value = opts.fecha || new Date().toLocaleDateString('en-CA');
        alGuardar = opts.onGuardado || null;
        document.getElementById('fmod-overlay').classList.add('open');
    };

    window.cerrarFichajeNuevo = function () {
        document.getElementById('fmod-overlay').classList.remove('open');
    };

    document.getElementById('fmod-overlay').addEventListener('click', e => {
        if (e.target.id === 'fmod-overlay') cerrarFichajeNuevo();
    });

    document.getElementById('fmod-form').addEventListener('submit', async function (e) {
        e.preventDefault();
        const error = document.getElementById('fmod-error');
        const boton = document.getElementById('fmod-save');
        error.style.display = 'none';
        boton.disabled = true;

        try {
            // fetchConAprobacion gestiona el flujo de informes en aprobación (avisa, pide
            // confirmación y reinicia las firmas si hace falta), igual que el resto de altas.
            const res = await window.fetchConAprobacion(URL_STORE, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: new FormData(this),
            });
            if (!res) return; // cancelado o bloqueado: fetchConAprobacion ya avisó

            const data = await res.json().catch(() => ({}));
            if (!res.ok) {
                error.textContent = data.error || 'No se pudo guardar el fichaje.';
                error.style.display = 'block';
                return;
            }
            if (data.aviso_aprobacion) alert(data.aviso_aprobacion);
            cerrarFichajeNuevo();
            if (alGuardar) alGuardar(data);
        } finally {
            boton.disabled = false;
        }
    });
})();
</script>
