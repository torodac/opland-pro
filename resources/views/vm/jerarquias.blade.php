<x-app-layout :breadcrumb="$breadcrumb" :project="$project">
<style>
  .jer-root{
    color-scheme: light;
    --bg-page:       #f9f9f7;
    --surface:       #fcfcfb;
    --surface-2:     #f3f2ee;
    --text-primary:  #0b0b0b;
    --text-secondary:#52514e;
    --text-muted:    #898781;
    --grid:          #e1e0d9;
    --border:        rgba(11,11,11,0.10);
    --accent:        #eb6834;
    --accent-soft:   rgba(235,104,52,0.10);
    --warn:          #fab219;
  }
  @media (prefers-color-scheme: dark) {
    :root:where(:not([data-theme="light"])) .jer-root{
      color-scheme: dark;
      --bg-page:       #0d0d0d;
      --surface:       #1a1a19;
      --surface-2:     #232322;
      --text-primary:  #ffffff;
      --text-secondary:#c3c2b7;
      --text-muted:    #898781;
      --grid:          #2c2c2a;
      --border:        rgba(255,255,255,0.10);
      --accent:        #d95926;
      --accent-soft:   rgba(217,89,38,0.18);
      --warn:          #fab219;
    }
  }
  :root[data-theme="dark"] .jer-root{
    color-scheme: dark;
    --bg-page:       #0d0d0d;
    --surface:       #1a1a19;
    --surface-2:     #232322;
    --text-primary:  #ffffff;
    --text-secondary:#c3c2b7;
    --text-muted:    #898781;
    --grid:          #2c2c2a;
    --border:        rgba(255,255,255,0.10);
    --accent:        #d95926;
    --accent-soft:   rgba(217,89,38,0.18);
    --warn:          #fab219;
  }

  .jer-root{
    background: var(--bg-page);
    color: var(--text-primary);
    padding: 16px;
    font-size: 13px;
    line-height: 1.4;
  }

  /* ── Pestañas ─────────────────────────────────────────────── */
  .jer-tabs{
    display:flex; align-items:center; gap:6px; flex-wrap:wrap;
    border-bottom:1px solid var(--grid);
    padding-bottom:8px; margin-bottom:16px;
  }
  .jer-tab{
    appearance:none; border:1px solid var(--border); background:var(--surface);
    color:var(--text-secondary); cursor:pointer;
    padding:7px 14px; border-radius:7px; font-size:13px; font-weight:600;
  }
  .jer-tab:hover{ background:var(--surface-2); color:var(--text-primary); }
  .jer-tab[aria-selected="true"]{
    background:var(--accent-soft); border-color:var(--accent); color:var(--accent);
  }
  .jer-tab-desc{
    flex:1 1 100%; color:var(--text-muted); font-size:12px; margin-top:2px;
  }
  .jer-acciones{ margin-left:auto; display:flex; gap:6px; }
  .jer-acc{
    appearance:none; border:1px solid var(--border); background:transparent;
    color:var(--text-muted); cursor:pointer;
    padding:6px 10px; border-radius:6px; font-size:12px;
  }
  .jer-acc:hover{ background:var(--surface-2); color:var(--text-primary); }

  /* ── Disposición: árbol + columna de sueltos ──────────────── */
  .jer-cols{ display:flex; gap:20px; align-items:flex-start; flex-wrap:wrap; }
  .jer-main{ flex:1 1 560px; min-width:0; overflow-x:auto; }
  .jer-aside{ flex:0 0 250px; }
  @media (max-width: 900px){ .jer-aside{ flex:1 1 100%; } }

  .jer-seccion{
    font-size:11px; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
    color:var(--text-muted); margin:0 0 8px;
  }
  .jer-aside .jer-nota{
    color:var(--text-muted); font-size:12px; margin:0 0 10px;
  }
  .jer-aside-lista{ display:flex; flex-direction:column; gap:8px; }

  /* ── Árbol ────────────────────────────────────────────────── */
  .jer-tree, .jer-tree ul{ list-style:none; margin:0; padding:0; }
  .jer-tree ul{
    margin-left:24px; padding-left:20px;
    border-left:1px solid var(--grid);
  }
  .jer-tree li{ position:relative; padding:5px 0; }
  .jer-tree ul > li::before{
    content:''; position:absolute; left:-20px; top:24px;
    width:16px; border-top:1px solid var(--grid);
  }
  /* El último hijo recorta la línea vertical justo a la altura de su conector. */
  .jer-tree ul > li:last-child::after{
    content:''; position:absolute; left:-21px; top:24px; bottom:0;
    border-left:1px solid var(--bg-page);
  }

  .jer-tree details > summary{
    list-style:none; cursor:pointer;
    display:flex; align-items:flex-start; gap:6px;
  }
  .jer-tree details > summary::-webkit-details-marker{ display:none; }
  .jer-chevron{
    color:var(--text-muted); font-size:11px; line-height:1;
    margin-top:9px; transition:transform .12s ease; flex:0 0 auto;
  }
  .jer-tree details[open] > summary .jer-chevron{ transform:rotate(90deg); }
  .jer-hoja{ display:flex; padding-left:17px; }

  /* ── Tarjeta ──────────────────────────────────────────────── */
  .jer-card{
    background:var(--surface); border:1px solid var(--border);
    border-radius:8px; padding:8px 10px; min-width:190px; max-width:290px;
  }
  .jer-card-h{ display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
  .jer-pill{
    background:var(--surface-2); color:var(--text-secondary);
    border-radius:4px; padding:1px 6px;
    font-size:11px; font-weight:700; font-variant-numeric:tabular-nums;
  }
  .jer-titulo{ font-weight:600; color:var(--text-primary); }
  .jer-count{
    margin-left:auto; color:var(--accent); background:var(--accent-soft);
    border-radius:99px; padding:1px 7px; font-size:11px; font-weight:700;
    font-variant-numeric:tabular-nums;
  }
  .jer-ciclo{
    color:#0b0b0b; background:var(--warn);
    border-radius:4px; padding:1px 6px; font-size:10px; font-weight:700;
  }
  .jer-sub{ color:var(--text-secondary); font-size:12px; margin-top:3px; }
  .jer-personas{
    list-style:none; margin:6px 0 0; padding:6px 0 0;
    border-top:1px solid var(--grid);
  }
  .jer-personas li{ color:var(--text-secondary); font-size:12px; padding:1px 0; }
  .jer-vacio{
    margin-top:6px; padding-top:6px; border-top:1px solid var(--grid);
    color:var(--text-muted); font-size:12px; font-style:italic;
  }
</style>

<div class="jer-root">

  <div class="jer-tabs">
    <button class="jer-tab" role="tab" aria-selected="true"  data-panel="roles">Roles</button>
    <button class="jer-tab" role="tab" aria-selected="false" data-panel="aprueba">Aprueba informe</button>
    <div class="jer-acciones">
      <button class="jer-acc" data-todos="1">Expandir todo</button>
      <button class="jer-acc" data-todos="0">Contraer todo</button>
    </div>
    <p class="jer-tab-desc" id="jer-desc"></p>
  </div>

  {{-- ── Pestaña 1: jerarquía de roles (vm_roles.roles_supervisados) ── --}}
  <div class="jer-cols" data-panel="roles">
    <div class="jer-main">
      <p class="jer-seccion">Jerarquía</p>
      @forelse($arbol_roles as $nodo)
        <ul class="jer-tree">
          @include('vm.partials.jerarquia-nodo', ['nodo' => $nodo])
        </ul>
      @empty
        <p class="jer-nota">Ningún rol supervisa a otro.</p>
      @endforelse
    </div>
    <div class="jer-aside">
      <p class="jer-seccion">Sin jerarquía ({{ count($sueltos_roles) }})</p>
      <p class="jer-nota">Roles que ni supervisan a otro rol ni son supervisados por ninguno.</p>
      <div class="jer-aside-lista">
        @foreach($sueltos_roles as $nodo)
          @include('vm.partials.jerarquia-tarjeta', ['nodo' => $nodo, 'hijos' => 0])
        @endforeach
      </div>
    </div>
  </div>

  {{-- ── Pestaña 2: jerarquía de aprobación (vm_usuarios.id_aprueba_informe) ── --}}
  <div class="jer-cols" data-panel="aprueba" hidden>
    <div class="jer-main">
      <p class="jer-seccion">Jerarquía</p>
      @forelse($arbol_aprueba as $nodo)
        <ul class="jer-tree">
          @include('vm.partials.jerarquia-nodo', ['nodo' => $nodo])
        </ul>
      @empty
        <p class="jer-nota">Ningún usuario tiene asignado quién aprueba su informe.</p>
      @endforelse
    </div>
    <div class="jer-aside">
      <p class="jer-seccion">Sin jerarquía ({{ count($sueltos_aprueba) }})</p>
      <p class="jer-nota">Personas que no tienen a nadie que les apruebe el informe y a las que tampoco aprueba nadie.</p>
      <div class="jer-aside-lista">
        @foreach($sueltos_aprueba as $nodo)
          @include('vm.partials.jerarquia-tarjeta', ['nodo' => $nodo, 'hijos' => 0])
        @endforeach
      </div>
    </div>
  </div>

</div>

<script>
(function () {
  const root   = document.querySelector('.jer-root');
  const tabs   = root.querySelectorAll('.jer-tab');
  const desc   = root.querySelector('#jer-desc');
  const paneles = root.querySelectorAll('.jer-cols[data-panel]');

  const textos = {
    roles:   'Relación entre roles, definida en el campo "roles supervisados" de cada rol. Es la que decide hoy a quién ve cada usuario en el informe mensual, fichajes y listados.',
    aprueba: 'Relación entre personas, definida en el campo "Aprueba informe" de la ficha de cada usuario. Pensada para el circuito de firma del informe mensual.',
  };

  function activar(nombre) {
    tabs.forEach(t => t.setAttribute('aria-selected', String(t.dataset.panel === nombre)));
    paneles.forEach(p => { p.hidden = p.dataset.panel !== nombre; });
    desc.textContent = textos[nombre] || '';
  }

  tabs.forEach(t => t.addEventListener('click', () => activar(t.dataset.panel)));

  // Expandir/contraer afecta solo a la pestaña visible.
  root.querySelectorAll('.jer-acc').forEach(btn => {
    btn.addEventListener('click', () => {
      const abrir = btn.dataset.todos === '1';
      root.querySelectorAll('.jer-cols:not([hidden]) details')
          .forEach(d => { d.open = abrir; });
    });
  });

  activar('roles');
})();
</script>
</x-app-layout>
