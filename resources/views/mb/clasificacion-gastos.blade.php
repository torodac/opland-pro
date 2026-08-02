<x-app-layout :project="$project" :breadcrumb="[['label'=>'Clasificación de gastos','url'=>'']]">

<style>
.cg-board{display:grid;grid-template-columns:340px 1fr;gap:24px;align-items:start}
@media (max-width:900px){ .cg-board{grid-template-columns:1fr} }

.cg-col{background:#fff;border:1px solid #dce6ee;border-radius:10px;box-shadow:0 1px 2px rgba(18,63,79,.06);overflow:hidden}
.cg-col-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid #dce6ee;background:#f7fafc}
.cg-col-head h3{font-size:14px;font-weight:700;margin:0;color:#16232b}
.cg-count{font-size:11.5px;color:#7e93a1}

.cg-expense-list{list-style:none;margin:0;padding:8px;max-height:60vh;overflow-y:auto}
.cg-expense{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:10px 10px;margin-bottom:2px;border-radius:8px;cursor:grab;border:1px solid transparent}
.cg-expense:hover{background:#f2f6f8}
.cg-expense:active{cursor:grabbing}
.cg-expense.dragging{opacity:.35}
.cg-expense .desc{font-size:13px;font-weight:500;cursor:pointer;color:#16232b}
.cg-expense .desc:hover{text-decoration:underline;text-underline-offset:2px}
.cg-expense .amount{font-size:11.5px;color:#7e93a1;white-space:nowrap}
.cg-empty-row{padding:20px 12px;text-align:center;color:#7e93a1;font-size:12.5px}

.cg-assigned-log{border-top:1px solid #dce6ee;padding:10px 14px 14px}
.cg-assigned-log h4{font-size:10.5px;letter-spacing:.05em;text-transform:uppercase;color:#7e93a1;margin:0 0 8px;font-weight:700}
.cg-assigned-row{display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:11.5px;padding:5px 0;border-bottom:1px dashed #eaf1f6;color:#7e93a1}
.cg-assigned-row .info{display:flex;flex-direction:column;gap:1px;overflow:hidden}
.cg-assigned-row b{color:#16232b;font-weight:600}
.cg-assigned-row .where{white-space:nowrap}
.cg-undo{border:none;background:transparent;color:#7e93a1;cursor:pointer;font-size:14px;line-height:1;padding:2px 6px;border-radius:5px;flex-shrink:0}
.cg-undo:hover{color:#b5502e;background:#fbf0ea}

.cg-groups-wrap{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;align-content:start}

.cg-group-block{background:#fff;border:1.5px solid #dce6ee;border-left:5px solid var(--cg-accent,#dce6ee);border-radius:12px;box-shadow:0 1px 2px rgba(18,63,79,.06);transition:box-shadow .16s ease,transform .12s ease;align-self:start}
.cg-group-block.expanded{box-shadow:0 12px 32px -10px rgba(28,36,48,.22);transform:translateY(-2px)}
.cg-group-head{display:flex;align-items:center;gap:8px;padding:12px 13px 10px;border-bottom:1px solid #eaf1f6}
.cg-group-name{font-size:13.5px;font-weight:700;flex:1;color:var(--cg-accent,#16232b)}

.cg-accounts-panel{max-height:0;overflow:hidden;transition:max-height .18s ease}
.cg-group-block.expanded .cg-accounts-panel{max-height:3000px}
.cg-accounts-inner{padding:8px}
.cg-account-drop{display:flex;align-items:center;padding:7px 9px;margin-bottom:3px;border-radius:7px;border:1.5px dashed transparent;font-size:12px}
.cg-account-drop .name{color:#16232b}
.cg-account-drop.over{background:#fbf3e2;border-color:#c4881f}

.cg-modal-backdrop{position:fixed;inset:0;background:rgba(28,36,48,.35);display:flex;align-items:center;justify-content:center;z-index:50}
.cg-modal-backdrop.hidden{display:none}
.cg-modal-card{background:#fff;border-radius:12px;box-shadow:0 12px 32px -10px rgba(28,36,48,.22);width:320px;padding:20px}
.cg-modal-card h3{font-size:15.5px;margin:0 0 10px;color:#16232b}
.cg-modal-row{display:flex;justify-content:space-between;gap:10px;font-size:12.5px;padding:6px 0;border-bottom:1px dashed #eaf1f6}
.cg-modal-row span:first-child{color:#7e93a1}
.cg-modal-row span:last-child{text-align:right;color:#16232b}
.cg-modal-close{margin-top:14px;width:100%;border:1px solid #dce6ee;background:#f2f6f8;border-radius:8px;padding:8px 0;font-size:12.5px;font-weight:700;color:#16232b;cursor:pointer}
.cg-modal-close:hover{background:#eaf1f6}
</style>

<div class="cg-board">

  <section class="cg-col">
    <div class="cg-col-head">
      <h3>Gastos por clasificar</h3>
      <span class="cg-count" id="cg-count-expenses">0</span>
    </div>
    <ul class="cg-expense-list" id="cg-expense-list"></ul>
    <div class="cg-assigned-log">
      <h4>Últimos clasificados</h4>
      <div id="cg-assigned-log"></div>
    </div>
  </section>

  <section class="cg-groups-wrap" id="cg-groups-wrap"></section>

</div>

@php
    $expensesData = $pendientes->map(fn ($g) => [
        'id' => $g->id, 'desc' => $g->nombre, 'amount' => (float) $g->importe_neto, 'fecha' => $g->fecha_emision,
    ])->values();

    $groupsData = $grupos->map(function ($g) use ($cuentasPorGrupo) {
        $cuentas = $cuentasPorGrupo->get($g->id) ?? collect();
        return [
            'id' => $g->id,
            'name' => $g->nombre,
            'accounts' => $cuentas->map(fn ($c) => [
                'id' => $c->id, 'code' => $c->cuenta, 'name' => $c->nombre,
            ])->values(),
        ];
    })->sortByDesc(fn ($g) => count($g['accounts']))->values();

    $historyData = $ultimosClasificados->map(fn ($g) => [
        'expenseId' => $g->id, 'desc' => $g->nombre, 'amount' => (float) $g->importe_neto, 'fecha' => $g->fecha_emision,
        'code' => $g->cuenta, 'name' => $g->cuenta_nombre, 'groupName' => $g->agrupacion_nombre ?? 'Sin agrupación',
    ])->values();

    $routeFicha = route('ficha', [$project->slug, 'gastos', '__ID__']);
@endphp
<script>
(function(){
  const CSRF = @json(csrf_token());
  const ROUTE_CLASIFICAR = @json(route('mb.clasificacion-gastos.clasificar', [$project->slug, '__ID__']));
  const ROUTE_DESHACER   = @json(route('mb.clasificacion-gastos.deshacer', [$project->slug, '__ID__']));

  let expenses = {!! $expensesData->toJson(JSON_UNESCAPED_UNICODE) !!};
  let groups = {!! $groupsData->toJson(JSON_UNESCAPED_UNICODE) !!};
  let assignedHistory = {!! $historyData->toJson(JSON_UNESCAPED_UNICODE) !!};

  let draggedId = null;

  function colorFor(id){
    const palette = ['#B5502E','#1F6E5C','#C4881F','#5B6B8C','#7A4FA0'];
    let hash = 0;
    const s = String(id);
    for(const ch of s) hash = (hash * 31 + ch.charCodeAt(0)) % palette.length;
    return palette[Math.abs(hash) % palette.length];
  }

  const money = n => Number(n).toLocaleString('es-ES', { minimumFractionDigits:2, maximumFractionDigits:2 }) + ' €';

  const ROUTE_FICHA = @json($routeFicha);
  function abrirFichaGasto(expenseId){
    window.open(ROUTE_FICHA.replace('__ID__', expenseId), '_blank');
  }

  function renderExpenses(){
    const list = document.getElementById('cg-expense-list');
    document.getElementById('cg-count-expenses').textContent = expenses.length;

    list.innerHTML = expenses.length
      ? expenses.map(e => `
        <li class="cg-expense" draggable="true" data-id="${e.id}">
          <span class="desc" data-id="${e.id}">${e.desc}</span>
          <span class="amount">${money(e.amount)}</span>
        </li>
      `).join('')
      : '<li class="cg-empty-row">Todos los gastos están clasificados</li>';

    list.querySelectorAll('.cg-expense').forEach(el => {
      el.addEventListener('dragstart', e => {
        draggedId = el.dataset.id;
        el.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        e.dataTransfer.setData('text/plain', el.dataset.id);
      });
      el.addEventListener('dragend', () => {
        el.classList.remove('dragging');
        draggedId = null;
        collapseAll();
      });
    });

    list.querySelectorAll('.desc').forEach(el => {
      el.addEventListener('click', () => abrirFichaGasto(parseInt(el.dataset.id)));
    });

    const log = document.getElementById('cg-assigned-log');
    const lastFive = assignedHistory.slice(0, 5);
    log.innerHTML = lastFive.length
      ? lastFive.map(h => `
        <div class="cg-assigned-row">
          <span class="info"><b>${h.desc}</b><span class="where">${h.code ? h.code + ' · ' : ''}${h.name || h.groupName}</span></span>
          <button class="cg-undo" data-id="${h.expenseId}" title="Deshacer clasificación">×</button>
        </div>
      `).join('')
      : '<div class="cg-assigned-row" style="border:none;">Aún no hay gastos clasificados</div>';

    log.querySelectorAll('.cg-undo').forEach(btn => {
      btn.addEventListener('click', () => unassignExpense(parseInt(btn.dataset.id)));
    });
  }

  function renderGroups(){
    const wrap = document.getElementById('cg-groups-wrap');
    wrap.innerHTML = groups.map(g => {
      const accountsHtml = g.accounts.map(a => `
          <div class="cg-account-drop" data-group="${g.id}" data-cuenta="${a.id}" data-name="${a.name}">
            <span class="name">${a.name}</span>
          </div>
      `).join('');

      return `
        <div class="cg-group-block" data-group="${g.id}" style="--cg-accent:${colorFor(g.id)}">
          <div class="cg-group-head"><span class="cg-group-name">${g.name}</span></div>
          <div class="cg-accounts-panel"><div class="cg-accounts-inner">${accountsHtml}</div></div>
        </div>
      `;
    }).join('');

    wrap.querySelectorAll('.cg-group-block').forEach(block => {
      block.addEventListener('dragenter', e => { e.preventDefault(); block.classList.add('expanded'); });
      block.addEventListener('dragover', e => e.preventDefault());
      block.addEventListener('dragleave', e => { if(!block.contains(e.relatedTarget)) block.classList.remove('expanded'); });
      block.addEventListener('drop', e => e.preventDefault());
    });

    wrap.querySelectorAll('.cg-account-drop').forEach(row => {
      row.addEventListener('dragover', e => { e.preventDefault(); e.dataTransfer.dropEffect = 'move'; row.classList.add('over'); });
      row.addEventListener('dragleave', () => row.classList.remove('over'));
      row.addEventListener('drop', e => {
        e.preventDefault();
        e.stopPropagation();
        row.classList.remove('over');
        const id = parseInt(e.dataTransfer.getData('text/plain') || draggedId);
        if(!id) return;
        assignExpense(id, parseInt(row.dataset.group), parseInt(row.dataset.cuenta), row.dataset.name);
      });
    });
  }

  function collapseAll(){
    document.querySelectorAll('.cg-group-block.expanded').forEach(b => b.classList.remove('expanded'));
  }

  async function assignExpense(expenseId, groupId, cuentaId, cuentaName){
    const expense = expenses.find(e => e.id === expenseId);
    const group = groups.find(g => g.id === groupId);
    const account = group ? group.accounts.find(a => a.id === cuentaId) : null;
    if(!expense || !group) return;

    const res = await fetch(ROUTE_CLASIFICAR.replace('__ID__', expenseId), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
      body: JSON.stringify({ id_gastos_cuentas: cuentaId }),
    });
    if(!res.ok){ alert('No se pudo clasificar el gasto.'); return; }

    expenses = expenses.filter(e => e.id !== expenseId);
    assignedHistory = assignedHistory.filter(h => h.expenseId !== expenseId);
    assignedHistory.unshift({
      expenseId, desc: expense.desc, amount: expense.amount, fecha: expense.fecha,
      code: account ? account.code : '', name: cuentaName, groupName: group.name,
    });

    collapseAll();
    renderAll();
  }

  async function unassignExpense(expenseId){
    const historyEntry = assignedHistory.find(h => h.expenseId === expenseId);

    const res = await fetch(ROUTE_DESHACER.replace('__ID__', expenseId), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
    });
    if(!res.ok){ alert('No se pudo deshacer la clasificación.'); return; }

    assignedHistory = assignedHistory.filter(h => h.expenseId !== expenseId);
    if(historyEntry && !expenses.find(e => e.id === expenseId)){
      expenses.push({ id: expenseId, desc: historyEntry.desc, amount: historyEntry.amount, fecha: historyEntry.fecha });
    }

    renderAll();
  }

  function renderAll(){ renderExpenses(); renderGroups(); }
  renderAll();
})();
</script>

</x-app-layout>
