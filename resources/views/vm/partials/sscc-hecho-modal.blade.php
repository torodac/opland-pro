{{-- Modal del botón Hecho, compartida por la ficha SSCC y el dashboard. Pide al servidor lo que va a
     pasar (correo al huésped, fotos adjuntas) y, al confirmar, ejecuta la acción de quien la abre. --}}
<div id="modal-hecho" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.45);z-index:1000;align-items:center;justify-content:center;padding:16px;">
  <div role="dialog" aria-modal="true" aria-labelledby="mh-titulo"
       style="background:#fff;color:#111;border-radius:12px;max-width:640px;width:100%;max-height:90vh;overflow:auto;padding:20px 22px;box-shadow:0 10px 30px rgba(0,0,0,.2);">
    <h2 id="mh-titulo" style="font-size:17px;font-weight:600;margin:0 0 10px;">Marcar como hecha</h2>

    <p style="font-size:13px;color:#9B1C1C;margin:0 0 12px;"><strong>Una vez hecha, el comentario ya no se podrá editar.</strong></p>

    <p id="mh-envio" style="font-size:13px;margin:0 0 12px;"></p>

    <div id="mh-correo" style="display:none;margin-bottom:12px;">
      <div id="mh-destino" style="font-size:12px;color:#666;margin-bottom:6px;"></div>
      <div id="mh-html" style="border:1px solid #e5e7eb;border-radius:8px;background:#f4f6f8;padding:8px;"></div>
    </div>

    <div id="mh-fotos" style="display:none;margin-bottom:12px;">
      <div style="font-size:12px;color:#666;margin-bottom:6px;">Fotos adjuntas</div>
      <div id="mh-fotos-lista" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
    </div>

    <div style="display:flex;justify-content:flex-end;gap:8px;margin-top:6px;">
      <button type="button" onclick="cerrarModalHecho()"
              style="padding:8px 14px;font-size:13px;border:1px solid #d1d5db;border-radius:8px;background:#fff;color:#374151;cursor:pointer;">Cancelar</button>
      <button type="button" id="mh-confirmar" onclick="confirmarModalHecho()"
              style="padding:8px 14px;font-size:13px;font-weight:600;border:1px solid #16a34a;border-radius:8px;background:#16a34a;color:#fff;cursor:pointer;">Marcar como hecha</button>
    </div>
  </div>
</div>

<script>
  let accionModalHecho = null;

  async function abrirModalHecho(urlPrevia, alConfirmar) {
    const r = await fetch(urlPrevia, { headers: { 'Accept': 'application/json' } });
    const p = await r.json().catch(() => null);
    if (!r.ok || !p) { alert((p && p.message) || 'No se pudo preparar la previsualización.'); return; }

    document.getElementById('mh-envio').textContent = p.envia
      ? 'Al marcarla, se enviará el correo de devolución al huésped.'
      : (p.motivo || 'Al marcarla, no se envía correo al huésped.');

    const correo = document.getElementById('mh-correo');
    correo.style.display = p.envia ? '' : 'none';
    if (p.envia) {
      document.getElementById('mh-destino').textContent = 'Correo en castellano · para ' + p.destinatario
        + (p.redirigido_a ? ' · en pruebas se redirige a ' + p.redirigido_a : '');
      document.getElementById('mh-html').innerHTML =
        '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;background:#fff;">' + p.html + '</table>';
    }

    const lista = document.getElementById('mh-fotos-lista');
    lista.innerHTML = '';
    p.fotos.forEach(f => {
      const figura = document.createElement('figure');
      figura.style.cssText = 'margin:0;width:110px;';
      const img = document.createElement('img');
      img.src = f.url;
      img.alt = f.concepto || 'Foto';
      img.loading = 'lazy';
      img.style.cssText = 'width:110px;height:110px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;display:block;';
      const pie = document.createElement('figcaption');
      pie.style.cssText = 'font-size:11px;color:#555;margin-top:4px;';
      pie.textContent = (f.concepto || '') + (f.importe ? ' · ' + f.importe.toFixed(2).replace('.', ',') + ' €' : '');
      figura.append(img, pie);
      lista.append(figura);
    });
    document.getElementById('mh-fotos').style.display = (p.envia && p.fotos.length) ? '' : 'none';

    accionModalHecho = alConfirmar;
    document.getElementById('modal-hecho').style.display = 'flex';
  }

  function cerrarModalHecho() {
    document.getElementById('modal-hecho').style.display = 'none';
    accionModalHecho = null;
  }

  async function confirmarModalHecho() {
    const accion = accionModalHecho;
    cerrarModalHecho();
    if (accion) await accion();
  }

  document.addEventListener('keydown', e => { if (e.key === 'Escape') cerrarModalHecho(); });
</script>
