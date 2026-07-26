{{--
    Desplegable con búsqueda, igual al usado en las fichas genéricas (partials/field.blade.php, caso 'desplegable').
    Variables: $id (id del input hidden final), $opciones (Collection id=>nombre), $seleccionado (mixed), $onChangeJs (string opcional)
--}}
@php
    $selLabel = ($seleccionado !== null && $seleccionado !== '' && isset($opciones[$seleccionado])) ? $opciones[$seleccionado] : null;
@endphp
<div class="fk-combo" style="position:relative;">
    <button type="button" class="fk-combo-toggle"
            style="width:100%;text-align:left;padding:0.5rem 0.75rem;border:1px solid #e5e7eb;border-radius:0.5rem;background:#fff;font-size:0.75rem;display:flex;justify-content:space-between;align-items:center;gap:6px;cursor:pointer;color:{{ $selLabel ? '#111827' : '#9ca3af' }};">
        <span class="fk-combo-label" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $selLabel ?? '— Selecciona —' }}</span>
        <span style="flex-shrink:0;color:#9ca3af;">▾</span>
    </button>
    <div class="fk-combo-panel" style="display:none;position:absolute;z-index:30;top:calc(100% + 4px);left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:0.5rem;box-shadow:0 6px 16px rgba(0,0,0,.12);">
        <input type="text" class="fk-combo-search" placeholder="Buscar…"
               style="width:100%;box-sizing:border-box;padding:0.5rem 0.75rem;border:none;border-bottom:1px solid #f3f4f6;font-size:0.75rem;outline:none;border-radius:0.5rem 0.5rem 0 0;">
        <ul class="fk-combo-list" style="list-style:none;margin:0;padding:2px 0;max-height:12rem;overflow-y:auto;">
            <li data-id="" style="padding:0.5rem 0.75rem;font-size:0.75rem;cursor:pointer;color:#9ca3af;">— Selecciona —</li>
            @foreach($opciones as $optId => $nombre)
                <li data-id="{{ $optId }}" style="padding:0.5rem 0.75rem;font-size:0.75rem;cursor:pointer;color:#374151;">{{ $nombre }}</li>
            @endforeach
        </ul>
    </div>
    <input type="hidden" id="{{ $id }}" class="fk-combo-value" value="{{ $seleccionado }}"
           @if(!empty($onChangeJs)) onchange="{{ $onChangeJs }}" @endif>
</div>

@once
    <script>
    (function() {
        function initFkCombo(wrap) {
            if (wrap.dataset.fkInit) return;
            wrap.dataset.fkInit = '1';

            const toggle = wrap.querySelector('.fk-combo-toggle');
            const label  = wrap.querySelector('.fk-combo-label');
            const panel  = wrap.querySelector('.fk-combo-panel');
            const search = wrap.querySelector('.fk-combo-search');
            const list   = wrap.querySelector('.fk-combo-list');
            const hidden = wrap.querySelector('.fk-combo-value');
            const items  = Array.from(list.querySelectorAll('li'));

            function filtrar(q) {
                const ql = q.trim().toLowerCase();
                items.forEach(li => {
                    li.style.display = (li.dataset.id === '' || li.textContent.toLowerCase().includes(ql)) ? '' : 'none';
                });
            }

            toggle.addEventListener('click', () => {
                const willOpen = panel.style.display === 'none';
                document.querySelectorAll('.fk-combo-panel').forEach(p => p.style.display = 'none');
                panel.style.display = willOpen ? 'block' : 'none';
                if (willOpen) { search.value = ''; filtrar(''); search.focus(); }
            });

            search.addEventListener('input', () => filtrar(search.value));

            list.addEventListener('mousedown', (e) => {
                const li = e.target.closest('li[data-id]');
                if (!li) return;
                e.preventDefault();
                hidden.value = li.dataset.id;
                label.textContent = li.dataset.id !== '' ? li.textContent : '— Selecciona —';
                label.style.color = li.dataset.id !== '' ? '#111827' : '#9ca3af';
                panel.style.display = 'none';
                hidden.dispatchEvent(new Event('change', { bubbles: true }));
            });

            document.addEventListener('click', (e) => {
                if (!wrap.contains(e.target)) panel.style.display = 'none';
            });
        }

        function initAll() {
            document.querySelectorAll('.fk-combo').forEach(initFkCombo);
        }

        document.addEventListener('DOMContentLoaded', initAll);
        initAll();

        new MutationObserver(() => initAll())
            .observe(document.body, { childList: true, subtree: true });
    })();
    </script>
@endonce
