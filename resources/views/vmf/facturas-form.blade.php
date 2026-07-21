<x-app-layout :project="$project">

<div style="max-width:700px;margin:0 auto;padding:1.5rem 1rem;">

    <h1 style="font-size:1.25rem;font-weight:700;color:#111827;margin:0 0 3px;">Subir facturas</h1>
    <p style="font-size:12.5px;color:#9ca3af;margin:0 0 20px;">Arrastra uno o varios documentos (PDF o imagen) — Claude extraerá los datos automáticamente en segundo plano.</p>

    <div id="dropzone"
         style="border:2px dashed #d1d5db;border-radius:12px;padding:44px 20px;text-align:center;cursor:pointer;transition:border-color .15s,background .15s;">
        <i class="fas fa-cloud-upload-alt" style="font-size:2rem;color:#9ca3af;"></i>
        <p style="margin:10px 0 4px;font-size:13.5px;color:#374151;font-weight:500;">Suelta aquí tus facturas</p>
        <p style="margin:0;font-size:11.5px;color:#9ca3af;">o haz clic para seleccionar archivos — PDF, PNG, JPG</p>
        <input type="file" id="fileInput" multiple accept=".pdf,.png,.jpg,.jpeg,.webp" style="display:none;">
    </div>

    <div id="listaArchivos" style="margin-top:20px;display:flex;flex-direction:column;gap:8px;"></div>

</div>

<script>
(function () {
    var dropzone   = document.getElementById('dropzone');
    var fileInput  = document.getElementById('fileInput');
    var lista      = document.getElementById('listaArchivos');
    var csrfToken  = '{{ csrf_token() }}';
    var uploadUrl  = @json(route('listado.upload-doc', [$project->slug, 'facturas']));
    var fichaBase  = @json(url($project->slug . '/facturas'));

    dropzone.addEventListener('click', function () { fileInput.click(); });
    dropzone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropzone.style.borderColor = '#f97316';
        dropzone.style.background  = '#fff7ed';
    });
    dropzone.addEventListener('dragleave', function () {
        dropzone.style.borderColor = '#d1d5db';
        dropzone.style.background  = '';
    });
    dropzone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropzone.style.borderColor = '#d1d5db';
        dropzone.style.background  = '';
        subirArchivos(e.dataTransfer.files);
    });
    fileInput.addEventListener('change', function () {
        subirArchivos(fileInput.files);
        fileInput.value = '';
    });

    function subirArchivos(files) {
        Array.from(files).forEach(subirArchivo);
    }

    function subirArchivo(file) {
        var item = document.createElement('div');
        item.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 14px;border:1px solid #e5e7eb;border-radius:8px;font-size:12.5px;background:#fff;';
        item.innerHTML =
            '<i class="fas fa-file-alt" style="color:#9ca3af;"></i>' +
            '<span style="flex:1;color:#374151;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + file.name + '</span>' +
            '<span class="estado" style="color:#9ca3af;white-space:nowrap;">Subiendo…</span>';
        lista.prepend(item);
        var estado = item.querySelector('.estado');

        var fd = new FormData();
        fd.append('file', file);

        fetch(uploadUrl, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: fd,
        })
        .then(function (r) { return r.json().then(function (data) { return { ok: r.ok, data: data }; }); })
        .then(function (res) {
            if (res.ok && res.data.ok) {
                estado.textContent = 'Enviado a Claude ✓';
                estado.style.color = '#16a34a';
                var link = document.createElement('a');
                link.href = fichaBase + '/' + res.data.id;
                link.textContent = 'Ver ficha';
                link.style.cssText = 'color:#f97316;font-weight:500;text-decoration:none;white-space:nowrap;';
                item.appendChild(link);
            } else {
                estado.textContent = 'Error';
                estado.style.color = '#dc2626';
            }
        })
        .catch(function () {
            estado.textContent = 'Error de red';
            estado.style.color = '#dc2626';
        });
    }
})();
</script>

</x-app-layout>
