<x-app-layout :project="$project">

<div style="max-width:860px;margin:0 auto;padding:1.5rem 1rem;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin:0 0 1.5rem;">
        <h1 style="font-size:1.25rem;font-weight:700;color:#111827;margin:0;">Pérdidas y Ganancias</h1>
        <a href="{{ url('/vm/pyg_valores') }}"
           style="display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:8px;border:1px solid #d1d5db;font-size:12px;color:#374151;text-decoration:none;background:#fff;transition:background .15s;"
           onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background='#fff'"
           title="Ver datos PyG">
            <i class="fa-solid fa-table-list" style="color:#6b7280;"></i> Ver datos
        </a>
    </div>

    {{-- Drop zone --}}
    <div id="pyg-dropzone"
         style="border:2px dashed #d1d5db;border-radius:12px;padding:2rem;margin-bottom:2rem;text-align:center;cursor:pointer;transition:border-color .2s,background .2s;background:#fafafa;"
         onclick="document.getElementById('pyg-file-input').click()">
        <input type="file" id="pyg-file-input" accept=".xlsx,.xls" style="display:none">
        <svg style="width:36px;height:36px;margin:0 auto 10px;color:#9ca3af;" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
        </svg>
        <p style="font-size:13px;font-weight:600;color:#374151;margin:0;">Arrastra el Excel de A3 aquí o haz clic para seleccionar</p>
        <p style="font-size:11px;color:#9ca3af;margin:5px 0 0;">.xlsx · El período se detecta automáticamente</p>
        <div id="pyg-upload-status" style="margin-top:14px;display:none;"></div>
    </div>

    {{-- Períodos importados --}}
    @if(count($periodos) > 0)
    <table style="width:100%;border-collapse:collapse;font-size:13px;">
        <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
                <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Período</th>
                <th style="text-align:right;padding:6px 10px;color:#6b7280;font-weight:600;">Cuentas</th>
                <th style="text-align:left;padding:6px 10px;color:#6b7280;font-weight:600;">Centros de coste</th>
                <th style="padding:6px 10px;"></th>
            </tr>
        </thead>
        <tbody id="pyg-periodos-body">
        @foreach($periodos as $p)
        <tr data-periodo="{{ $p->periodo }}" style="border-bottom:1px solid #f3f4f6;">
            <td style="padding:8px 10px;font-weight:600;color:#111827;">
                {{ \Carbon\Carbon::parse($p->periodo)->translatedFormat('F Y') }}
            </td>
            <td style="padding:8px 10px;text-align:right;color:#374151;">{{ $p->num_cuentas }}</td>
            <td style="padding:8px 10px;color:#6b7280;">
                @php
                    $cecos = is_string($p->cecos)
                        ? array_map('trim', explode(',', trim($p->cecos, '{}')))
                        : (array)$p->cecos;
                @endphp
                {{ implode(', ', $cecos) }}
            </td>
            <td style="padding:8px 10px;text-align:right;">
                <button onclick="deletePeriodo('{{ $p->periodo }}')"
                        style="font-size:11px;color:#dc2626;background:none;border:none;cursor:pointer;padding:2px 6px;border-radius:4px;">✕ Eliminar</button>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @else
    <p style="color:#9ca3af;font-size:13px;text-align:center;margin:2rem 0;">No hay períodos importados todavía.</p>
    @endif

</div>

{{-- Popup de mapeo --}}
<div id="pyg-map-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:1000;align-items:flex-start;justify-content:center;overflow-y:auto;padding:2rem 1rem;">
    <div style="background:#fff;border-radius:14px;padding:1.75rem;width:100%;max-width:600px;margin:auto;box-shadow:0 20px 60px rgba(0,0,0,.2);">
        <h2 style="font-size:1rem;font-weight:700;color:#111827;margin:0 0 .4rem;">Columnas sin mapear</h2>
        <p style="font-size:12px;color:#6b7280;margin:0 0 1.25rem;line-height:1.5;">
            Indica qué es cada columna del Excel que no hemos podido identificar.
        </p>

        <div id="pyg-map-rows"></div>

        <div style="display:flex;gap:.75rem;justify-content:flex-end;margin-top:1.5rem;padding-top:1rem;border-top:1px solid #e5e7eb;">
            <button onclick="cancelMapping()"
                    style="font-size:13px;padding:7px 18px;border:1px solid #d1d5db;border-radius:8px;background:#fff;cursor:pointer;color:#374151;">
                Cancelar
            </button>
            <button onclick="saveMappingsOnly()"
                    style="font-size:13px;padding:7px 18px;border:1px solid #6b7280;border-radius:8px;background:#f9fafb;cursor:pointer;color:#374151;">
                Guardar mapeos y cerrar
            </button>
            <button onclick="confirmMapping()"
                    style="font-size:13px;padding:7px 20px;border:none;border-radius:8px;background:#f97316;color:#fff;cursor:pointer;font-weight:600;">
                Continuar importación
            </button>
        </div>
    </div>
</div>

<script>
(function(){
    const CSRF    = '{{ csrf_token() }}';
    const URL_IMP = '{{ route("vm.pyg.import", $project->slug) }}';
    const URL_DEL = '{{ route("vm.pyg.delete", [$project->slug, "__periodo__"]) }}';

    const zone    = document.getElementById('pyg-dropzone');
    const input   = document.getElementById('pyg-file-input');
    const status  = document.getElementById('pyg-upload-status');
    const overlay = document.getElementById('pyg-map-overlay');

    let pendingTmpId = null;

    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor='#f97316'; zone.style.background='#fff7ed'; });
    zone.addEventListener('dragleave', () => { zone.style.borderColor='#d1d5db'; zone.style.background='#fafafa'; });
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.style.borderColor='#d1d5db'; zone.style.background='#fafafa';
        if (e.dataTransfer.files.length) uploadFile(e.dataTransfer.files[0]);
    });
    input.addEventListener('change', () => { if (input.files.length) uploadFile(input.files[0]); input.value=''; });

    function uploadFile(file) {
        setStatus('<span style="color:#9ca3af;">Procesando ' + esc(file.name) + '…</span>');
        const fd = new FormData();
        fd.append('file', file);
        fd.append('_token', CSRF);
        sendImport(fd);
    }

    function sendImport(body) {
        const isJson = !(body instanceof FormData);
        const opts = isJson
            ? { method:'POST', body: JSON.stringify(body), headers:{ 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept':'application/json' } }
            : { method:'POST', body };
        fetch(URL_IMP, opts)
            .then(r => r.json())
            .then(data => {
                if (data.needs_mapping) {
                    pendingTmpId = data.tmp_id;
                    showMappingPopup(data.unknown_codes, data.propiedades);
                } else if (data.ok) {
                    const sust = data.sustituido ? ' (período sustituido)' : '';
                    setStatus('<span style="color:#16a34a;font-weight:600;">✓ ' + esc(data.periodo) + ' · ' + data.cuentas + ' cuentas nuevas · ' + data.valores + ' valores' + sust + '</span>');
                    setTimeout(() => location.reload(), 1800);
                } else {
                    setStatus('<span style="color:#dc2626;">✗ ' + esc(data.error ?? 'Error desconocido') + '</span>');
                }
            })
            .catch(() => setStatus('<span style="color:#dc2626;">✗ Error de red</span>'));
    }

    // --- Popup ---
    let propiedadesList = [];

    function showMappingPopup(unknownCodes, propiedades) {
        propiedadesList = propiedades;
        const container = document.getElementById('pyg-map-rows');
        container.innerHTML = '';

        unknownCodes.forEach((code, idx) => {
            const block = document.createElement('div');
            block.dataset.code = code;
            block.style.cssText = 'border:1px solid #e5e7eb;border-radius:10px;padding:1rem;margin-bottom:.75rem;';

            block.innerHTML = `
                <div style="font-size:13px;font-weight:700;color:#111827;margin-bottom:.75rem;">${esc(code)}</div>
                <div style="display:flex;flex-direction:column;gap:.5rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;font-size:13px;cursor:pointer;">
                        <input type="radio" name="tipo_${idx}" value="propiedad" checked onchange="toggleTipo(this,'${esc(code)}')">
                        <span>Propiedad existente en Opland</span>
                    </label>
                    <div id="sel_${idx}" style="display:block;padding-left:1.5rem;">
                        <select data-for="${esc(code)}" style="width:100%;padding:5px 8px;border:1px solid #d1d5db;border-radius:6px;font-size:12px;">
                            <option value="">— selecciona una propiedad —</option>
                            ${propiedades.map(p => `<option value="${p.id}">${esc(p.nombre)}${p.a3_code ? ' [' + esc(p.a3_code) + ']' : ''}</option>`).join('')}
                        </select>
                    </div>
                    <label style="display:flex;align-items:center;gap:.5rem;font-size:13px;cursor:pointer;">
                        <input type="radio" name="tipo_${idx}" value="ceco" onchange="toggleTipo(this,'${esc(code)}')">
                        <span>Centro de coste sin propiedad (ej: SANTI, GASTOS GENE)</span>
                    </label>
                </div>`;
            container.appendChild(block);
        });

        overlay.style.display = 'flex';
    }

    window.toggleTipo = function(radio, code) {
        // Buscar el select asociado dentro del mismo bloque
        const block = radio.closest('[data-code]');
        const selDiv = block.querySelector('[id^="sel_"]');
        if (selDiv) selDiv.style.display = radio.value === 'propiedad' ? 'block' : 'none';
    };

    window.cancelMapping = function() {
        overlay.style.display = 'none';
        pendingTmpId = null;
        setStatus('<span style="color:#9ca3af;">Importación cancelada.</span>');
    };

    function collectMappings() {
        const mappings = [];
        document.querySelectorAll('#pyg-map-rows [data-code]').forEach(block => {
            const code    = block.dataset.code;
            const checked = block.querySelector('input[type=radio]:checked');
            if (!checked) return;
            const type = checked.value;
            if (type === 'propiedad') {
                const sel = block.querySelector('select[data-for]');
                const id  = sel ? parseInt(sel.value) || null : null;
                if (!id) return;
                mappings.push({ code, type, id });
            } else {
                mappings.push({ code, type });
            }
        });
        return mappings;
    }

    window.saveMappingsOnly = function() {
        const mappings = collectMappings();
        overlay.style.display = 'none';
        if (!mappings.length) { pendingTmpId = null; setStatus('<span style="color:#9ca3af;">Ningún mapeo completado.</span>'); return; }
        setStatus('<span style="color:#9ca3af;">Guardando mapeos…</span>');
        fetch(URL_IMP, {
            method: 'POST',
            body: JSON.stringify({ _token: CSRF, tmp_id: pendingTmpId, mappings, save_only: true }),
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            pendingTmpId = null;
            if (data.ok) setStatus('<span style="color:#16a34a;font-weight:600;">✓ Mapeos guardados. Vuelve a subir el Excel para completar la importación.</span>');
            else setStatus('<span style="color:#dc2626;">✗ ' + esc(data.error ?? 'Error') + '</span>');
        })
        .catch(() => setStatus('<span style="color:#dc2626;">✗ Error de red</span>'));
    };

    window.confirmMapping = function() {
        const mappings = collectMappings();

        overlay.style.display = 'none';
        setStatus('<span style="color:#9ca3af;">Aplicando mapeos e importando…</span>');
        sendImport({ _token: CSRF, tmp_id: pendingTmpId, mappings });
    };

    // --- Eliminar período ---
    window.deletePeriodo = function(periodo) {
        if (!confirm('¿Eliminar todos los datos del período ' + periodo + '?')) return;
        fetch(URL_DEL.replace('__periodo__', periodo), {
            method:'DELETE',
            headers:{ 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' }
        })
        .then(r => r.json())
        .then(data => {
            if (data.ok) {
                const row = document.querySelector('[data-periodo="' + periodo + '"]');
                if (row) row.remove();
            }
        });
    };

    function setStatus(html) { status.style.display='block'; status.innerHTML=html; }
    function esc(s) { return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
})();
</script>

</x-app-layout>
