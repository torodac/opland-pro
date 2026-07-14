const API = 'https://app.opland.es/api/vm';
const APP_VERSION = '2026.07.03-1';

// ── Bottom nav (única fuente de verdad, evita 4 bloques duplicados en el HTML) ──
const NAV_ITEMS = [
  { name: 'fichaje', label: 'Fichaje', svg: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>' },
  { name: 'tareas',  label: 'Tareas',  svg: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M9 12l2 2 4-4"/></svg>' },
  { name: 'horario', label: 'Horario', svg: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>' },
  { name: 'perfil',  label: 'Perfil',  svg: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>' },
];

function renderAllNavs() {
  document.querySelectorAll('[data-nav-container]').forEach(nav => {
    const active = nav.dataset.navContainer;
    nav.innerHTML = NAV_ITEMS.map(item => `
      <button class="nav-btn${item.name === active ? ' active' : ''}" data-nav="${item.name}">
        ${item.svg}
        ${item.label}
      </button>`).join('');
  });
  document.querySelectorAll('.nav-btn[data-nav]').forEach(btn => {
    btn.addEventListener('click', () => navTo(btn.dataset.nav));
  });
}
renderAllNavs();

// ── Storage ───────────────────────────────────────────────────────────────────
const store = {
  get: k => { try { return JSON.parse(localStorage.getItem(k)); } catch { return null; } },
  set: (k, v) => localStorage.setItem(k, JSON.stringify(v)),
  del: k => localStorage.removeItem(k),
};

// ── State ─────────────────────────────────────────────────────────────────────
let state = {
  token: store.get('vm_token'),
  user:  store.get('vm_user'),
  asUser: store.get('vm_as_user') || null,
  esSupervisor: store.get('vm_es_supervisor') || false,
  tareas: [],
  fichajeHoy: null,
  detalleActivo: null,
  tareasFecha: new Date().toLocaleDateString('en-CA'),
  propiedades: [],
  filtroUsuarioId: null,
  usuariosSubordinados: [],
};

// ── API helper ────────────────────────────────────────────────────────────────
async function api(method, path, body) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  if (state.token) headers['Authorization'] = 'Bearer ' + state.token;

  const opts = { method, headers };
  if (body && !(body instanceof FormData)) {
    opts.body = JSON.stringify(body);
  } else if (body instanceof FormData) {
    delete headers['Content-Type'];
    opts.body = body;
  }

  if (state.asUser && !path.includes('as_user')) {
    const sep = path.includes('?') ? '&' : '?';
    path = path + sep + 'as_user=' + state.asUser.id;
  }
  const res = await fetch(API + path, opts);
  const json = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(json.error || json.message || 'Error ' + res.status);
  return json;
}

// ── Toast ─────────────────────────────────────────────────────────────────────
let toastTimer;
function toast(msg, dur = 2500) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), dur);
}

// ── Screens ───────────────────────────────────────────────────────────────────
function showScreen(name) {
  document.querySelectorAll('.screen').forEach(s => s.classList.remove('active'));
  document.getElementById(name + '-screen').classList.add('active');
}

function navTo(name) {
  document.querySelectorAll('.nav-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.nav === name);
  });
  if (name !== 'detalle') {
    document.getElementById('btn-ctx-menu').style.display = 'none';
    document.getElementById('ctx-menu').style.display = 'none';
  }
  if (name === 'tareas')   loadTareas();
  if (name === 'fichaje')  loadFichaje();
  if (name === 'horario')  loadHorario();
  if (name === 'perfil')   renderPerfil();
  showScreen(name);
}

// ── Login ─────────────────────────────────────────────────────────────────────
async function doLogin() {
  const email    = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-pass').value;
  const remember = document.getElementById('login-remember').checked;
  const errEl    = document.getElementById('login-error');
  const btn      = document.getElementById('login-btn');

  errEl.style.display = 'none';
  btn.disabled = true;
  btn.textContent = 'Entrando…';

  try {
    const data = await api('POST', '/login', { email, password, remember });
    state.token = data.token;
    state.user  = data.user;
    state.esSupervisor = !!data.es_supervisor;
    store.set('vm_token', data.token);
    store.set('vm_user', data.user);
    store.set('vm_es_supervisor', state.esSupervisor);
    if (data.user?.debe_cambiar_password) {
      mostrarCambioPassword();
    } else {
      afterLogin();
    }
  } catch (e) {
    errEl.textContent = e.message;
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
}

async function doLogout() {
  try { await api('POST', '/logout'); } catch {}
  state.token = null;
  state.user  = null;
  store.del('vm_token');
  store.del('vm_user');
  showScreen('login');
}

function mostrarCambioPassword() {
  document.getElementById('cp-nueva').value = '';
  document.getElementById('cp-confirmar').value = '';
  document.getElementById('cp-error').style.display = 'none';
  showScreen('cambiar-password');
}

document.getElementById('cp-guardar').addEventListener('click', async () => {
  const nueva     = document.getElementById('cp-nueva').value;
  const confirmar = document.getElementById('cp-confirmar').value;
  const errEl     = document.getElementById('cp-error');
  const btn       = document.getElementById('cp-guardar');

  errEl.style.display = 'none';

  if (nueva.length < 8) {
    errEl.textContent = 'La contraseña debe tener al menos 8 caracteres';
    errEl.style.display = 'block'; return;
  }
  if (nueva !== confirmar) {
    errEl.textContent = 'Las contraseñas no coinciden';
    errEl.style.display = 'block'; return;
  }

  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    await api('POST', '/cambiar-password', {
      nueva_password:              nueva,
      nueva_password_confirmation: confirmar,
    });
    state.user.debe_cambiar_password = false;
    store.set('vm_user', state.user);
    afterLogin();
  } catch (e) {
    errEl.textContent = e.message;
    errEl.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Guardar contraseña';
  }
});

function afterLogin() {
  suscribirPush();
  renderPerfil();
  cargarUsuariosSubordinados();
  navTo('fichaje');
}

async function cargarUsuariosSubordinados() {
  if (!state.esSupervisor) return;
  try {
    state.usuariosSubordinados = await api('GET', '/usuarios');
  } catch {}
}

// ── Tareas ────────────────────────────────────────────────────────────────────
async function loadTareas(fecha) {
  if (fecha) state.tareasFecha = fecha;
  const list = document.getElementById('tareas-list');
  list.innerHTML = '<div class="spinner">Cargando…</div>';

  try {
    let url = `/tareas/hoy?fecha=${state.tareasFecha}`;
    if (state.filtroUsuarioId) url += `&usuario_id=${state.filtroUsuarioId}`;
    const data = await api('GET', url);
    state.tareas = data.tareas;
    state.fichajeCerrado = !!data.fichaje_cerrado;
    renderTareas(data);
  } catch (e) {
    if (e.message.includes('401') || e.message.toLowerCase().includes('token')) {
      doLogout(); return;
    }
    list.innerHTML = `<div class="empty-state"><div class="icon">⚠️</div><p>${e.message}</p></div>`;
  }
}

function moverDia(delta) {
  const d = new Date(state.tareasFecha + 'T12:00:00');
  d.setDate(d.getDate() + delta);
  loadTareas(d.toISOString().slice(0, 10));
}

function diaNavHtml(fecha) {
  const hoy    = new Date().toLocaleDateString('en-CA');
  const esHoy  = fecha === hoy;
  const label  = esHoy
    ? 'Hoy'
    : new Date(fecha + 'T12:00:00').toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' });
  return `
    <div style="display:flex;align-items:center;gap:8px;padding:10px 0 4px;justify-content:center">
      <button id="btn-dia-prev" style="background:none;border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;padding:5px 12px;cursor:pointer">‹</button>
      <span style="font-size:14px;font-weight:600;min-width:140px;text-align:center">${label}</span>
      <button id="btn-dia-next" style="background:none;border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:14px;padding:5px 12px;cursor:pointer${esHoy ? ';opacity:.35;pointer-events:none' : ''}">›</button>
      ${!esHoy ? '<button id="btn-dia-hoy" style="background:none;border:1px solid var(--border);border-radius:8px;color:var(--text);font-size:12px;padding:5px 10px;cursor:pointer">Hoy</button>' : ''}
    </div>`;
}

function filtroSupervisorHtml() {
  if (!state.esSupervisor || !state.usuariosSubordinados.length) return '';
  const yo = state.asUser || state.user;
  const opts = [
    `<option value="">Todos</option>`,
    `<option value="${yo.id}"${state.filtroUsuarioId === yo.id ? ' selected' : ''}>Yo (${esc(yo.nombre)})</option>`,
    ...state.usuariosSubordinados
      .filter(u => u.id !== yo.id)
      .sort((a, b) => a.nombre.localeCompare(b.nombre))
      .map(u => `<option value="${u.id}"${state.filtroUsuarioId === u.id ? ' selected' : ''}>${esc(u.nombre)}</option>`),
  ].join('');
  return `
    <div style="padding:4px 0 6px">
      <select id="filtro-usuario" style="width:100%;padding:8px 10px;border:1px solid var(--border);border-radius:8px;background:var(--surface);color:var(--text);font-size:14px">
        ${opts}
      </select>
    </div>`;
}

function renderTareas(data) {
  const list = document.getElementById('tareas-list');

  let html = diaNavHtml(state.tareasFecha) + filtroSupervisorHtml();

  if (!data.tareas.length) {
    html += `<div class="empty-state"><div class="icon">🌴</div><p>No hay tareas este día</p></div>`;
    list.innerHTML = html;
    bindDiaNav();
    bindFiltroUsuario();
    return;
  }

  html += data.tareas.map(t => cardHtml(t)).join('');

  list.innerHTML = html;
  bindDiaNav();
  bindFiltroUsuario();

  list.querySelectorAll('.tarea-card').forEach(card => {
    card.addEventListener('click', () => {
      const id   = parseInt(card.dataset.id);
      const tipo = card.dataset.tipo;
      const t = state.tareas.find(x => x.id === id && x.tipo === tipo);
      if (t) abrirDetalle(t);
    });
  });
}

function avatarColor(id) {
  const palette = ['#0ea5e9','#22c55e','#f59e0b','#8b5cf6','#ec4899','#14b8a6'];
  return palette[id % palette.length];
}

function avatarsHtml(lista) {
  if (!lista || lista.length <= 1) return '';
  const visible = lista.slice(0, 3);
  const resto   = lista.length - 3;
  const chips   = visible.map((u, i) => {
    const ini   = (u.nombre || '?')[0].toUpperCase();
    const color = avatarColor(u.id);
    return `<span title="${esc(u.nombre)}" style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:${color};color:#fff;font-size:9px;font-weight:700;${i > 0 ? 'margin-left:-5px;' : ''}border:2px solid var(--bg)">${ini}</span>`;
  }).join('');
  const extra = resto > 0
    ? `<span style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:50%;background:var(--surface);color:var(--muted);font-size:9px;font-weight:700;margin-left:-5px;border:2px solid var(--border)">+${resto}</span>`
    : '';
  return `<div style="display:flex;align-items:center;margin-top:5px;padding-left:2px">${chips}${extra}</div>`;
}

const TIPO_META = {
  limpieza:      { icon: '🧹', label: 'Limpieza' },
  mantenimiento: { icon: '🔧', label: 'Mantenimiento' },
  piscina:       { icon: '🏊', label: 'Piscina' },
};

function cardHtml(t) {
  const meta     = TIPO_META[t.tipo] || { icon: '📋', label: t.tipo };
  const icon     = meta.icon;
  const sinImputar = !t.mi_tiempo_total;
  const tiempoChip = sinImputar
    ? (state.fichajeCerrado
        ? `<span class="chip" style="background:#450a0a;border-color:#ef4444;color:#fca5a5">Sin imputar</span>`
        : `<span class="chip amber">Sin imputar</span>`)
    : `<span class="chip green">${t.mi_tiempo_total}</span>`;
  const tipoChip = `<span class="chip blue">${meta.label}</span>`;

  return `
    <div class="tarea-card" data-id="${t.id}" data-tipo="${t.tipo}">
      <div class="tarea-header">
        <span class="tarea-icon">${icon}</span>
        <div class="tarea-info">
          <div class="tarea-nombre">${esc(t.nombre)}</div>
          ${t.descripcion ? `<div class="tarea-descripcion">${esc(t.descripcion)}</div>` : ''}
          <div class="tarea-inmueble">${esc(t.propiedad_nombre)}</div>
          ${avatarsHtml(t.control_user_info)}
        </div>
      </div>
      <div class="tarea-badges">${tiempoChip}${tipoChip}</div>
    </div>`;
}

// ── Detalle ───────────────────────────────────────────────────────────────────
function imputacionesListHtml(tarea) {
  const limite = new Date(); limite.setDate(limite.getDate() - 2);
  const minFecha = limite.toLocaleDateString('en-CA');
  const imputaciones = tarea.mis_imputaciones || [];

  if (!imputaciones.length) {
    return `<p style="color:var(--muted);font-size:13px;text-align:center;padding:16px 0">Aún no has imputado tiempo en esta tarea</p>`;
  }

  const filas = imputaciones.map(imp => {
    const editable = imp.fecha_imputacion >= minFecha;
    const horas    = sprintfHoras(imp.duracion);
    return `
      <div class="imputacion-row${editable ? ' editable' : ''}" data-id="${imp.id}"
           style="display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid var(--border);${editable ? 'cursor:pointer' : 'opacity:.7'}">
        <div style="width:54px;flex-shrink:0;font-size:13px;color:var(--muted)">${fmtFecha(imp.fecha_imputacion)}</div>
        <div style="flex:1;min-width:0">
          <div style="font-weight:600;font-size:14px">${horas}</div>
          ${imp.observacion ? `<div style="font-size:12px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(imp.observacion)}</div>` : ''}
        </div>
        ${editable ? '<span style="font-size:16px;color:var(--muted)">›</span>' : ''}
      </div>`;
  }).join('');

  return `<div class="fichaje-card" style="padding:0 14px">${filas}</div>`;
}

function sprintfHoras(minutos) {
  const h = Math.floor(minutos / 60);
  const m = minutos % 60;
  return `${h}h ${m}min`;
}

function equipoListHtml(tarea) {
  const lista = tarea.control_user_info || [];
  if (!lista.length) return '';

  const yoId = (state.asUser || state.user)?.id;
  const filas = lista.map(u => {
    const tiempoStr = u.tiempo_minutos > 0 ? sprintfHoras(u.tiempo_minutos) : null;
    return `
    <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
      <span style="font-size:14px${u.id === yoId ? ';font-weight:600' : ''}">${u.id === yoId ? 'Yo' : esc(u.nombre)}</span>
      <span style="font-size:13px;color:${tiempoStr ? 'var(--text)' : 'var(--muted)'}">${tiempoStr || 'Sin imputar'}</span>
    </div>`;
  }).join('');

  return `
    <div class="detail-section">
      <div class="detail-label">Equipo</div>
      <div class="fichaje-card" style="padding:0 14px">${filas}</div>
    </div>`;
}

function fotosFichaHtml(tarea) {
  const fotos = tarea.fotos_detalle || [];
  const thumbs = fotos.map(foto => `
    <div style="position:relative;flex-shrink:0">
      <img src="https://app.opland.es/storage/${foto.path}" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--border);display:block">
      <button type="button" class="btn-borrar-foto-ficha" data-id="${foto.id}"
        style="position:absolute;top:-6px;right:-6px;width:22px;height:22px;border-radius:50%;background:var(--red);border:none;color:#fff;font-size:14px;cursor:pointer;padding:0;line-height:22px;text-align:center">×</button>
    </div>`).join('');

  return `
    <div class="detail-section">
      <div class="detail-label">Fotos</div>
      ${thumbs ? `<div class="foto-preview" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px">${thumbs}</div>` : ''}
      <div style="display:flex;gap:8px">
        <button type="button" id="btn-ficha-camara" class="btn btn-outline" style="flex:1;width:auto;height:40px;margin-top:0;display:flex;align-items:center;justify-content:center;font-size:13px;padding:0">📷 Cámara</button>
        <button type="button" id="btn-ficha-fototeca" class="btn btn-outline" style="flex:1;width:auto;height:40px;margin-top:0;display:flex;align-items:center;justify-content:center;font-size:13px;padding:0">🖼 Fototeca</button>
      </div>
      <input type="file" id="ficha-foto-input-camara" accept="image/*" capture="environment" style="display:none">
      <input type="file" id="ficha-foto-input-fototeca" accept="image/*" style="display:none" multiple>
    </div>`;
}

function abrirDetalle(tarea) {
  state.detalleActivo = tarea;
  document.getElementById('detalle-titulo').textContent = tarea.propiedad_nombre;

  const content = document.getElementById('detalle-content');

  const mapsUrl = (tarea.lat && tarea.lng)
    ? `https://maps.google.com/?q=${tarea.lat},${tarea.lng}`
    : tarea.direccion ? `https://maps.google.com/?q=${encodeURIComponent(tarea.direccion + ' ' + (tarea.ciudad || ''))}` : null;

  content.innerHTML = `
    <div class="detail-section">
      <div class="detail-label">Tarea</div>
      <div class="detail-value" style="font-weight:600;font-size:17px">${esc(tarea.nombre)}</div>
    </div>
    ${tarea.descripcion ? `<div class="detail-section"><div class="detail-label">Descripción</div><div class="detail-value">${esc(tarea.descripcion)}</div></div>` : ''}
    <div class="detail-section">
      <div class="detail-label">Propiedad</div>
      <div class="detail-value">${esc(tarea.propiedad_nombre)}</div>
      ${tarea.direccion ? `<div class="detail-value" style="color:var(--muted);font-size:13px;margin-top:2px">${esc(tarea.direccion)}${tarea.ciudad ? ', ' + esc(tarea.ciudad) : ''}</div>` : ''}
      ${mapsUrl ? `<a href="${mapsUrl}" target="_blank" style="display:inline-block;margin-top:6px;font-size:13px;color:var(--accent)">📍 Abrir en Maps</a>` : ''}
    </div>
    ${equipoListHtml(tarea)}
    ${tarea.puede_imputar !== false
      ? `<button class="btn btn-green" id="btn-imputar-detalle">Imputar tiempo</button>`
      : `<button class="btn btn-outline" disabled style="opacity:.4;cursor:default;pointer-events:none" id="btn-imputar-detalle">Solo supervisión</button>`}
    ${fotosFichaHtml(tarea)}
    <div class="detail-label" style="margin-top:18px;margin-bottom:6px">Mis imputaciones</div>
    ${imputacionesListHtml(tarea)}
  `;

  if (tarea.puede_imputar !== false) {
    content.querySelector('#btn-imputar-detalle').addEventListener('click', () => abrirModalImputar(null));
  }

  content.querySelectorAll('.imputacion-row.editable').forEach(row => {
    row.addEventListener('click', () => {
      const imp = (tarea.mis_imputaciones || []).find(i => i.id === parseInt(row.dataset.id));
      if (imp) abrirModalImputar(imp);
    });
  });

  content.querySelector('#btn-ficha-camara').addEventListener('click', () => {
    content.querySelector('#ficha-foto-input-camara').click();
  });
  content.querySelector('#btn-ficha-fototeca').addEventListener('click', () => {
    content.querySelector('#ficha-foto-input-fototeca').click();
  });
  content.querySelector('#ficha-foto-input-camara').addEventListener('change', handleFichaFotoInput);
  content.querySelector('#ficha-foto-input-fototeca').addEventListener('change', handleFichaFotoInput);
  content.querySelectorAll('.btn-borrar-foto-ficha').forEach(btn => {
    btn.addEventListener('click', () => borrarFotoFicha(parseInt(btn.dataset.id)));
  });

  // Botón flotante ⋯ — solo para limpieza y mantenimiento
  const btnCtx = document.getElementById('btn-ctx-menu');
  const ctxMenu = document.getElementById('ctx-menu');
  const tipoDestino = tarea.tipo === 'limpieza' ? 'Mantenimiento' : tarea.tipo === 'mantenimiento' ? 'Limpieza' : null;
  if (tipoDestino) {
    document.getElementById('ctx-reportar-tipo').textContent = tipoDestino;
    btnCtx.style.display = 'flex';
  } else {
    btnCtx.style.display = 'none';
  }
  ctxMenu.style.display = 'none';

  showScreen('detalle');
}

function bindFiltroUsuario() {
  document.getElementById('filtro-usuario')?.addEventListener('change', e => {
    state.filtroUsuarioId = e.target.value ? parseInt(e.target.value) : null;
    loadTareas();
  });
}

function bindDiaNav() {
  document.getElementById('btn-dia-prev')?.addEventListener('click', () => moverDia(-1));
  document.getElementById('btn-dia-next')?.addEventListener('click', () => moverDia(+1));
  document.getElementById('btn-dia-hoy')?.addEventListener('click',  () => loadTareas(new Date().toLocaleDateString('en-CA')));
}

document.getElementById('detalle-back').addEventListener('click', () => {
  navTo('tareas');
});

// ── Modal Imputar tiempo ──────────────────────────────────────────────────────
function abrirModalImputar(imputacion) {
  state.imputacionEditando = imputacion;

  const hoy    = new Date().toLocaleDateString('en-CA');
  const limite = new Date(); limite.setDate(limite.getDate() - 2);
  const minFecha = limite.toLocaleDateString('en-CA');

  const fechaInput = document.getElementById('modal-fecha-imputacion');
  fechaInput.min = minFecha;
  fechaInput.max = hoy;
  fechaInput.value = imputacion ? imputacion.fecha_imputacion : hoy;

  document.getElementById('modal-tiempo').value = imputacion ? sprintfHM(imputacion.duracion) : '';
  document.getElementById('modal-comentario').value = imputacion ? (imputacion.observacion || '') : '';
  document.getElementById('modal-error').style.display = 'none';
  document.getElementById('modal-imputar-titulo').textContent = imputacion ? 'Editar imputación' : 'Imputar tiempo';
  document.getElementById('modal-confirmar').textContent = 'Guardar';

  document.getElementById('completar-overlay').classList.add('open');
}

function sprintfHM(minutos) {
  const h = Math.floor(minutos / 60);
  const m = minutos % 60;
  return `${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`;
}

function cerrarModal() {
  document.getElementById('completar-overlay').classList.remove('open');
}

function prepareImage(file) {
  // El servidor redimensiona — solo nos aseguramos de que tenga extensión .jpg
  const name = (file.name.replace(/\.[^.]+$/, '') || 'foto') + '.jpg';
  return new File([file], name, { type: file.type || 'image/jpeg' });
}

// ── Fotos de la ficha de tarea ───────────────────────────────────────────────
async function recargarDetalle() {
  const actual = state.detalleActivo;
  let url = `/tareas/hoy?fecha=${state.tareasFecha}`;
  if (state.filtroUsuarioId) url += `&usuario_id=${state.filtroUsuarioId}`;
  const data = await api('GET', url);
  state.tareas = data.tareas;
  state.fichajeCerrado = !!data.fichaje_cerrado;
  const actualizada = state.tareas.find(t => t.id === actual.id && t.tipo === actual.tipo);
  if (actualizada) abrirDetalle(actualizada);
}

async function subirFotoFicha(file) {
  const tarea = state.detalleActivo;
  const fd = new FormData();
  fd.append('foto', prepareImage(file));
  try {
    await api('POST', `/tareas/${tarea.tipo}/${tarea.id}/foto`, fd);
    await recargarDetalle();
  } catch (e) {
    toast('Error: ' + e.message, 4000);
  }
}

async function borrarFotoFicha(fotoId) {
  try {
    await api('DELETE', `/fotos/${fotoId}`);
    await recargarDetalle();
  } catch (e) {
    toast('Error: ' + e.message, 4000);
  }
}

async function handleFichaFotoInput(e) {
  const files = Array.from(e.target.files || []);
  e.target.value = '';
  for (const file of files) {
    await subirFotoFicha(file);
  }
}

document.getElementById('modal-cancelar').addEventListener('click', cerrarModal);
document.getElementById('completar-overlay').addEventListener('click', e => {
  if (e.target === e.currentTarget) cerrarModal();
});

async function guardarImputacion() {
  const tarea      = state.detalleActivo;
  const editando   = state.imputacionEditando;
  const tiempo     = document.getElementById('modal-tiempo').value;
  const fechaImputacion = document.getElementById('modal-fecha-imputacion').value;
  const observacion = document.getElementById('modal-comentario').value.trim();
  const errEl      = document.getElementById('modal-error');
  const btn        = document.getElementById('modal-confirmar');

  errEl.style.display = 'none';

  if (!tiempo || !fechaImputacion) {
    errEl.textContent = 'Tiempo y fecha son obligatorios';
    errEl.style.display = 'block';
    return;
  }

  btn.disabled = true;
  btn.textContent = 'Guardando…';

  try {
    if (editando) {
      await api('PATCH', `/tareas/${tarea.tipo}/${tarea.id}/imputaciones/${editando.id}`, {
        tiempo,
        fecha_imputacion: fechaImputacion,
        observacion: observacion || undefined,
      });
    } else {
      const pos = await getPosition();
      await api('POST', `/tareas/${tarea.tipo}/${tarea.id}/imputar`, {
        tiempo,
        fecha_imputacion: fechaImputacion,
        observacion: observacion || undefined,
        ...(pos ? { lat: pos.lat, lng: pos.lng } : {}),
      });
    }

    cerrarModal();
    toast(editando ? '✓ Imputación actualizada' : '✓ Tiempo imputado');
    await recargarDetalle();
  } catch (e) {
    errEl.textContent = e.message;
    errEl.style.display = 'block';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Guardar';
  }
}

document.getElementById('modal-confirmar').addEventListener('click', guardarImputacion);

// ── Fichaje ───────────────────────────────────────────────────────────────────
async function loadFichaje() {
  const content = document.getElementById('fichaje-content');
  content.innerHTML = '<div class="spinner">Cargando…</div>';

  try {
    const data = await api('GET', '/fichaje/hoy');
    state.fichajeHoy  = data.fichaje;
    state.tareasMin   = data.tareas_min ?? 0;
    state.ausencias   = data.ausencias  || [];
    state.horarios    = data.horarios   || [];
    state.pendientesPorDia = data.pendientes_por_dia || {};
    state.imputadoPorDia   = data.imputado_por_dia  || {};
    if (data.horas_contrato !== undefined && state.user) {
      state.user.horas_contrato = data.horas_contrato;
      store.set('vm_user', state.user);
      renderPerfil();
    }
    const horasContrato = state.user?.horas_contrato ?? null;
    renderFichaje(data.fichaje, data.mes || [], horasContrato, state.tareasMin);
  } catch (e) {
    content.innerHTML = `<div class="empty-state"><div class="icon">⚠️</div><p>${e.message}</p></div>`;
  }
}

function horaCol(label, manual, edited = false) {
  const manualStr = manual ? manual.slice(0, 5) : '--:--';
  return `
    <div class="jornada-col">
      <div class="hora">${manualStr}${edited ? '<span style="font-size:10px;color:var(--amber);margin-left:3px">✎</span>' : ''}</div>
      <div class="lbl">${label}</div>
    </div>`;
}

function pausaMinutos(ini, fin) {
  if (!ini || !fin) return null;
  const [h1, m1] = ini.split(':').map(Number);
  const [h2, m2] = fin.split(':').map(Number);
  return (h2 * 60 + m2) - (h1 * 60 + m1);
}

function editFormHtml(f, fecha) {
  const inp = (id, val) =>
    `<input type="time" id="${id}" value="${val ? val.slice(0,5) : ''}"
      style="display:block;width:65%;margin-top:4px;padding:7px 8px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text);font-size:15px">`;
  return `
    <div class="fichaje-edit-form" data-fecha="${fecha}" style="display:none;border-top:1px solid var(--border);margin-top:12px;padding-top:12px">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px">
        <label style="font-size:12px;color:var(--muted)">Entrada
          ${inp('edit-hora-inicio-' + fecha, f?.hora_inicio)}
        </label>
        <label style="font-size:12px;color:var(--muted)">Salida
          ${inp('edit-hora-fin-' + fecha, f?.hora_fin)}
        </label>
        <label style="font-size:12px;color:var(--muted)">Inicio pausa
          ${inp('edit-pausa-inicio-' + fecha, f?.pausa_inicio)}
        </label>
        <label style="font-size:12px;color:var(--muted)">Fin pausa
          ${inp('edit-pausa-fin-' + fecha, f?.pausa_fin)}
        </label>
      </div>
      <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:12px">Observación
        <textarea id="edit-observacion-${fecha}" rows="2"
          style="display:block;width:100%;margin-top:4px;padding:7px 8px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text);font-size:15px;font-family:inherit;resize:vertical">${esc(f?.observacion || '')}</textarea>
      </label>
      <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:12px">Trayecto
        <textarea id="edit-trayecto-${fecha}" rows="2"
          style="display:block;width:100%;margin-top:4px;padding:7px 8px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text);font-size:15px;font-family:inherit;resize:vertical">${esc(f?.trayecto || '')}</textarea>
      </label>
      <label style="font-size:12px;color:var(--muted);display:block;margin-bottom:12px">Kilómetros
        <input type="number" id="edit-km-${fecha}" inputmode="decimal" step="0.01" min="0"
          value="${f?.km ?? ''}"
          style="display:block;width:100%;margin-top:4px;padding:7px 8px;border:1px solid var(--border);border-radius:var(--radius);background:var(--surface);color:var(--text);font-size:15px">
      </label>
      <div id="edit-error-${fecha}" class="error-msg" style="display:none"></div>
      <div style="display:flex;gap:10px">
        <button class="btn btn-outline btn-edit-cancelar" style="flex:1;font-size:14px;padding:10px">Cancelar</button>
        <button class="btn btn-green   btn-edit-guardar"  style="flex:1;font-size:14px;padding:10px">Guardar</button>
      </div>
    </div>`;
}

function validarHorarioFichaje(inicio, fin, pausaIni, pausaFin) {
  const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };

  if (inicio && fin && toMin(fin) < toMin(inicio)) {
    return 'La salida no puede ser anterior a la entrada';
  }
  if (pausaIni && pausaFin && toMin(pausaFin) < toMin(pausaIni)) {
    return 'El fin de pausa no puede ser anterior al inicio de pausa';
  }
  if (pausaIni && inicio && toMin(pausaIni) < toMin(inicio)) {
    return 'El inicio de pausa no puede ser anterior a la entrada';
  }
  if (pausaIni && fin && toMin(pausaIni) > toMin(fin)) {
    return 'El inicio de pausa no puede ser posterior a la salida';
  }
  if (pausaFin && inicio && toMin(pausaFin) < toMin(inicio)) {
    return 'El fin de pausa no puede ser anterior a la entrada';
  }
  if (pausaFin && fin && toMin(pausaFin) > toMin(fin)) {
    return 'El fin de pausa no puede ser posterior a la salida';
  }
  return null;
}

function fichajeHoyHtml(f, horasContrato = null, tareasMin = 0) {
  const hoy    = new Date().toLocaleDateString('en-CA');
  const hoyStr = new Date().toLocaleDateString('es-ES', { weekday: 'long', day: 'numeric', month: 'long' });

  if (!f) {
    return {
      html: `
        <p style="color:var(--muted);font-size:13px;margin-bottom:16px;text-transform:capitalize">${hoyStr}</p>
        <div class="fichaje-card">
          <div class="empty-state" style="padding:16px 0">
            <div class="icon">🕐</div>
            <p style="margin-bottom:16px">No has fichado hoy</p>
          </div>
          <button class="btn btn-green" id="btn-entrada">Fichar entrada</button>
        </div>`,
      bind: () => document.getElementById('btn-entrada').addEventListener('click', () => fichajeAccion('entrada')),
    };
  }

  const enPausa    = f.pausa_inicio && !f.pausa_fin;
  const pausaDone  = f.pausa_inicio && f.pausa_fin;
  const cerrado    = !!f.hora_fin;
  const fichadoStr = cerrado ? calcHoras(f.hora_inicio, f.hora_fin, f.pausa_inicio, f.pausa_fin, horasContrato) : null;
  const tareasStr  = tareasMin > 0 ? minsToStr(tareasMin) : null;
  const editado    = (campo, auto) => campo && auto && campo.slice(0,5) !== auto.slice(0,5);

  let botones = '';
  if (!cerrado) {
    if (!enPausa && !pausaDone) {
      botones = `<button class="btn btn-amber" id="btn-pausa">Iniciar pausa</button>
                 <button class="btn btn-red" id="btn-salida" style="margin-top:10px">Fichar salida</button>`;
    } else if (enPausa) {
      botones = `<button class="btn btn-amber" id="btn-pausa">Fin de pausa</button>`;
    } else {
      botones = `<button class="btn btn-red" id="btn-salida">Fichar salida</button>`;
    }
  }

  const minPausa = pausaDone ? pausaMinutos(f.pausa_inicio, f.pausa_fin) : null;
  const pausaStr = minPausa !== null ? `<div class="jornada-col">
    <div class="hora" style="font-size:18px">${minPausa}m</div>
    <div class="lbl">Pausa</div>
  </div>` : (enPausa ? `<div class="jornada-col">
    <div class="hora" style="font-size:18px">…</div>
    <div class="lbl">Pausa activa</div>
  </div>` : '');

  return {
    html: `
      <p style="color:var(--muted);font-size:13px;margin-bottom:16px;text-transform:capitalize">${hoyStr}</p>
      <div class="fichaje-card">
        <div class="jornada-row">
          ${horaCol('Entrada', f.hora_inicio, editado(f.hora_inicio, f.hora_ini_auto))}
          ${pausaStr}
          ${f.hora_inicio ? horaCol('Salida', f.hora_fin, editado(f.hora_fin, f.hora_fin_auto)) : ''}
        </div>
        ${fichadoStr ? `<div style="text-align:center;color:var(--green);font-weight:600;margin-bottom:4px">${fichadoStr} fichado</div>` : ''}
        ${tareasStr  ? `<div style="text-align:center;color:var(--muted);font-size:13px;margin-bottom:16px">${tareasStr} trabajado en tareas</div>` : (fichadoStr ? '<div style="margin-bottom:16px"></div>' : '')}
        ${botones}
        ${cerrado ? '<div style="text-align:center;color:var(--muted);font-size:14px;padding-top:8px">Jornada cerrada</div>' : ''}
        <button id="btn-editar-fichaje" style="background:none;border:none;color:var(--muted);font-size:12px;padding:10px 0 0;cursor:pointer;text-decoration:underline;display:block;width:100%;text-align:center">
          Editar tiempos manualmente
        </button>
        ${editFormHtml(f, hoy)}
      </div>`,
    bind: () => {
      document.getElementById('btn-pausa')?.addEventListener('click',  () => fichajeAccion('pausa'));
      document.getElementById('btn-salida')?.addEventListener('click', () => fichajeAccion('salida'));
      document.getElementById('btn-editar-fichaje').addEventListener('click', () => {
        const form = document.querySelector('.fichaje-edit-form[data-fecha="' + hoy + '"]');
        form.style.display = form.style.display === 'none' ? 'block' : 'none';
      });
      bindEditForm(hoy);
    },
  };
}

function bindEditForm(fecha, esCrear = false) {
  const form = document.querySelector(`.fichaje-edit-form[data-fecha="${fecha}"]`);
  if (!form) return;
  form.querySelector('.btn-edit-cancelar').addEventListener('click', () => {
    form.style.display = 'none';
  });
  form.querySelector('.btn-edit-guardar').addEventListener('click', async () => {
    const btn   = form.querySelector('.btn-edit-guardar');
    const errEl = document.getElementById('edit-error-' + fecha);
    errEl.style.display = 'none';

    const horaInicio  = document.getElementById('edit-hora-inicio-'  + fecha).value || null;
    const horaFin     = document.getElementById('edit-hora-fin-'     + fecha).value || null;
    const pausaInicio = document.getElementById('edit-pausa-inicio-' + fecha).value || null;
    const pausaFin    = document.getElementById('edit-pausa-fin-'    + fecha).value || null;
    const observacion = document.getElementById('edit-observacion-'  + fecha).value.trim() || null;
    const trayecto    = document.getElementById('edit-trayecto-'     + fecha).value.trim() || null;
    const kmVal       = document.getElementById('edit-km-'           + fecha).value;
    const km          = kmVal !== '' ? kmVal : null;

    const error = validarHorarioFichaje(horaInicio, horaFin, pausaInicio, pausaFin);
    if (error) {
      errEl.textContent = error;
      errEl.style.display = 'block';
      return;
    }

    btn.disabled = true; btn.textContent = 'Guardando…';
    try {
      const payload = {
        fecha,
        hora_inicio:  horaInicio,
        hora_fin:     horaFin,
        pausa_inicio: pausaInicio,
        pausa_fin:    pausaFin,
        observacion,
        trayecto,
        km,
      };
      if (esCrear) {
        await api('POST', '/fichaje/crear', payload);
      } else {
        await api('PATCH', '/fichaje/editar', payload);
      }
      await recargarFichaje();
      toast('Guardado');
    } catch (e) {
      errEl.textContent = e.message;
      errEl.style.display = 'block';
      btn.disabled = false; btn.textContent = 'Guardar';
    }
  });
}

async function recargarFichaje() {
  const data = await api('GET', '/fichaje/hoy');
  state.fichajeHoy = data.fichaje;
  state.ausencias  = data.ausencias || [];
  state.horarios   = data.horarios  || [];
  state.pendientesPorDia = data.pendientes_por_dia || {};
  state.imputadoPorDia   = data.imputado_por_dia  || {};
  renderFichaje(data.fichaje, data.mes || [], state.user?.horas_contrato ?? data.horas_contrato, state.tareasMin ?? 0);
}

const TIPO_LABELS = {
  turno:        'Turno',
  descanso:     'Descanso',
  vacaciones:   'Vacaciones',
  baja:         'Baja',
  comp_festivo: 'Comp. festivo',
  comp_horas:   'Comp. horas',
  asuntos:      'Asuntos propios',
  absentismo:   'Absentismo',
};

function tipoBadgeClass(tipo) {
  const t = (tipo || '').toLowerCase();
  if (t === 'turno')                          return 'hbadge-turno';
  if (t === 'descanso')                       return 'hbadge-descanso';
  if (t.includes('vacac'))                    return 'hbadge-vacaciones';
  if (t === 'baja')                           return 'hbadge-baja';
  if (t.includes('absent'))                   return 'hbadge-absentismo';
  if (t.includes('asunto'))                   return 'hbadge-asuntos';
  if (t.startsWith('comp') || t.includes('festiv')) return 'hbadge-compensacion';
  return 'hbadge-descanso';
}

function tipoLabel(tipo) {
  return TIPO_LABELS[tipo] ?? (tipo ? tipo.charAt(0).toUpperCase() + tipo.slice(1) : '');
}

function diffCounter(hora_inicio, hora_fin, pausa_inicio, pausa_fin, imputadoMin) {
  if (!hora_inicio || !hora_fin) return '';
  const toMin = h => { const [hh, mm] = h.split(':').map(Number); return hh * 60 + mm; };
  const bruto     = toMin(hora_fin.slice(0,5)) - toMin(hora_inicio.slice(0,5));
  const pausa     = (pausa_inicio && pausa_fin) ? pausaMinutos(pausa_inicio, pausa_fin) : 0;
  const efectivas = bruto - pausa;
  const diff      = efectivas - (imputadoMin ?? 0);
  const color     = diff >= 0 ? 'var(--green, #16a34a)' : 'var(--red, #dc2626)';
  const signo     = diff >= 0 ? '+' : '';
  return `<span style="font-size:13px;font-weight:600;color:${color}">${signo}${minsToStr(Math.abs(diff))}</span>`;
}

function fichajeHistoricoHtml(mes, hoy) {
  const diasSemana = ['dom','lun','mar','mié','jue','vie','sáb'];
  const hoyDate    = new Date(hoy);
  const d1         = new Date(hoyDate); d1.setDate(d1.getDate() - 1);
  const d2         = new Date(hoyDate); d2.setDate(d2.getDate() - 2);
  const ayer       = d1.toISOString().slice(0, 10);
  const antesAyer  = d2.toISOString().slice(0, 10);

  const ausencias = state.ausencias || [];
  const horarios  = state.horarios  || [];

  const fichajeMap = {};
  mes.filter(f => f.fecha_fichaje !== hoy).forEach(f => { fichajeMap[f.fecha_fichaje] = f; });

  // Fechas con fichaje + ausencias + horarios especiales del mes
  const mesInicio = mes.length ? mes[mes.length - 1].fecha_fichaje : antesAyer;
  const fechas = new Set(Object.keys(fichajeMap));
  ausencias.forEach(a => {
    for (let d = new Date(a.fecha_inicio + 'T12:00:00'); d.toISOString().slice(0,10) <= hoy; d.setDate(d.getDate() + 1)) {
      const ds = d.toISOString().slice(0, 10);
      if (ds !== hoy && ds >= mesInicio && ds <= a.fecha_fin) fechas.add(ds);
    }
  });
  horarios.filter(h => h.tipo !== 'turno').forEach(h => { if (h.fecha !== hoy) fechas.add(h.fecha); });

  function badgesParaFecha(fecha) {
    const bs = [];
    ausencias.forEach(a => { if (fecha >= a.fecha_inicio && fecha <= a.fecha_fin) bs.push(a.tipo); });
    horarios.filter(h => h.fecha === fecha && h.tipo !== 'turno').forEach(h => bs.push(h.tipo));
    return bs;
  }

  const sorted = [...fechas].sort((a, b) => b.localeCompare(a));
  const mes_nombre = new Date().toLocaleDateString('es-ES', { month: 'long', year: 'numeric' });

  const filas = sorted.map(fecha => {
    const f        = fichajeMap[fecha];
    const [, m, d] = fecha.split('-');
    const fechaStr = `${parseInt(d)}/${parseInt(m)}`;
    const diaSem   = diasSemana[new Date(fecha + 'T12:00:00').getDay()];
    const editable = fecha >= antesAyer;
    const badges   = badgesParaFecha(fecha);
    const badgeHtml = badges.map(b =>
      `<span class="hbadge ${tipoBadgeClass(b)}">${esc(b)}</span>`
    ).join('');
    // Fila en rojo si coexisten fichaje y (ausencia o descanso)
    const conflicto = f && badges.length > 0;
    const rowColor  = conflicto ? 'color:var(--red)' : '';

    if (!f) {
      return `
        <div style="display:flex;align-items:center;padding:10px 0;border-bottom:1px solid var(--border);gap:12px;opacity:.7">
          <div style="width:48px;text-align:center;flex-shrink:0">
            <div style="font-size:15px;font-weight:600">${fechaStr}</div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase">${diaSem}</div>
          </div>
          <div style="flex:1;font-size:13px;display:flex;flex-wrap:wrap;gap:4px;align-items:center">${badgeHtml || '<span style="color:var(--muted)">–</span>'}</div>
        </div>`;
    }

    const minP      = (f.pausa_inicio && f.pausa_fin) ? pausaMinutos(f.pausa_inicio, f.pausa_fin) : null;
    const pausaInfo = minP !== null ? `<span style="font-size:11px;color:var(--muted)">(pausa ${minP}m)</span>` : '';
    const imputadoMin = (state.imputadoPorDia || {})[fecha] ?? 0;
    const counter   = diffCounter(f.hora_inicio, f.hora_fin, f.pausa_inicio, f.pausa_fin, imputadoMin);
    const numPendientes = (state.pendientesPorDia || {})[fecha] || 0;
    const pendientesBadge = numPendientes > 0
      ? `<span title="${numPendientes} tarea(s) pendiente(s) de ese día" style="display:inline-flex;align-items:center;justify-content:center;min-width:18px;height:18px;border-radius:50%;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:0 4px">${numPendientes}</span>`
      : '';

    return `
      <div class="hist-row${editable ? ' hist-editable' : ''}" data-fecha="${fecha}"
           style="padding:10px 0;border-bottom:1px solid var(--border);${rowColor}">
        <div style="display:flex;align-items:center;gap:12px${editable ? ';cursor:pointer' : ''}">
          <div style="width:48px;text-align:center;flex-shrink:0">
            <div style="font-size:15px;font-weight:600">${fechaStr}</div>
            <div style="font-size:11px;color:var(--muted);text-transform:uppercase">${diaSem}</div>
          </div>
          <div style="flex:1;display:flex;flex-wrap:wrap;gap:6px;align-items:center;font-size:13px">
            <span>${f.hora_inicio ? f.hora_inicio.slice(0,5) : '--'} → ${f.hora_fin ? f.hora_fin.slice(0,5) : '--'}</span>
            ${pausaInfo}
            ${badgeHtml}
          </div>
          <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
            ${pendientesBadge}
            ${counter}
            ${editable ? '<span style="font-size:16px;color:var(--muted);margin-left:2px">›</span>' : ''}
          </div>
        </div>
        ${editable ? editFormHtml(f, fecha) : ''}
      </div>`;
  }).join('');

  return `
    <div style="display:flex;align-items:center;margin:20px 0 8px">
      <p style="flex:1;color:var(--muted);font-size:12px;text-transform:uppercase;letter-spacing:.05em;text-transform:capitalize;margin:0">${mes_nombre}</p>
      <button id="btn-abrir-nuevo-fichaje"
        style="background:none;border:1px solid var(--border);border-radius:50%;width:26px;height:26px;font-size:16px;color:var(--muted);cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1">+</button>
    </div>
    <div class="fichaje-card" style="padding:0 14px">${filas || '<div style="padding:16px 0;text-align:center;color:var(--muted);font-size:13px">Sin registros este mes</div>'}</div>`;
}

function renderFichaje(f, mes = [], horasContrato = null, tareasMin = 0) {
  const content = document.getElementById('fichaje-content');
  const hoy     = new Date().toLocaleDateString('en-CA');
  const { html, bind } = fichajeHoyHtml(f, horasContrato, tareasMin);
  content.innerHTML = html + fichajeHistoricoHtml(mes, hoy);
  bind();

  // Botón + del título → abre modal
  document.getElementById('btn-abrir-nuevo-fichaje')?.addEventListener('click', () => abrirModalNuevoFichaje(mes, hoy));

  // Filas editables del histórico
  document.querySelectorAll('.hist-editable').forEach(row => {
    const fecha = row.dataset.fecha;
    row.querySelector('div').addEventListener('click', () => {
      const form = row.querySelector('.fichaje-edit-form');
      if (!form) return;
      const abierto = form.style.display !== 'none';
      form.style.display = abierto ? 'none' : 'block';
      if (!abierto) bindEditForm(fecha, false);
    });
  });
}

function abrirModalNuevoFichaje(mes, hoy) {
  const hoyDate   = new Date(hoy);
  const d2        = new Date(hoyDate); d2.setDate(d2.getDate() - 2);
  const antesAyer = d2.toISOString().slice(0, 10);
  const ayer      = new Date(hoyDate); ayer.setDate(ayer.getDate() - 1);
  const ayerStr   = ayer.toISOString().slice(0, 10);

  const fechasExistentes = new Set(mes.map(f => f.fecha_fichaje));

  const input = document.getElementById('nf-fecha');
  input.min   = antesAyer;
  input.max   = ayerStr;
  input.value = ayerStr;
  ['nf-hora-inicio','nf-hora-fin','nf-pausa-inicio','nf-pausa-fin','nf-observacion','nf-trayecto','nf-km'].forEach(id => {
    document.getElementById(id).value = '';
  });
  document.getElementById('nf-fecha-error').style.display = 'none';

  document.getElementById('modal-nuevo-fichaje').classList.add('open');

  const guardar = document.getElementById('nf-guardar');
  const nuevoGuardar = guardar.cloneNode(true);
  guardar.parentNode.replaceChild(nuevoGuardar, guardar);

  nuevoGuardar.addEventListener('click', async () => {
    const fecha     = document.getElementById('nf-fecha').value;
    const errEl     = document.getElementById('nf-fecha-error');
    errEl.style.display = 'none';

    if (!fecha || fecha < antesAyer || fecha >= hoy) {
      errEl.textContent = 'Fecha fuera de rango (solo los 2 días anteriores)';
      errEl.style.display = 'block'; return;
    }
    if (fechasExistentes.has(fecha)) {
      errEl.textContent = 'Ya existe un fichaje para ese día';
      errEl.style.display = 'block'; return;
    }

    const horaInicio  = document.getElementById('nf-hora-inicio').value  || null;
    const horaFin     = document.getElementById('nf-hora-fin').value      || null;
    const pausaInicio = document.getElementById('nf-pausa-inicio').value  || null;
    const pausaFin    = document.getElementById('nf-pausa-fin').value     || null;
    const observacion = document.getElementById('nf-observacion').value.trim() || null;
    const trayecto    = document.getElementById('nf-trayecto').value.trim()    || null;
    const kmValNuevo  = document.getElementById('nf-km').value;
    const km          = kmValNuevo !== '' ? kmValNuevo : null;

    const horarioError = validarHorarioFichaje(horaInicio, horaFin, pausaInicio, pausaFin);
    if (horarioError) {
      errEl.textContent = horarioError;
      errEl.style.display = 'block'; return;
    }

    nuevoGuardar.disabled = true; nuevoGuardar.textContent = 'Guardando…';
    try {
      await api('POST', '/fichaje/crear', {
        fecha,
        hora_inicio:  horaInicio,
        hora_fin:     horaFin,
        pausa_inicio: pausaInicio,
        pausa_fin:    pausaFin,
        observacion,
        trayecto,
        km,
      });
      document.getElementById('modal-nuevo-fichaje').classList.remove('open');
      await recargarFichaje();
      toast('Fichaje creado');
    } catch (e) {
      errEl.textContent = e.message;
      errEl.style.display = 'block';
      nuevoGuardar.disabled = false; nuevoGuardar.textContent = 'Guardar';
    }
  });
}

document.getElementById('nf-cancelar').addEventListener('click', () => {
  document.getElementById('modal-nuevo-fichaje').classList.remove('open');
});
document.getElementById('modal-nuevo-fichaje').addEventListener('click', e => {
  if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
});

// ── Tarea espejo ──────────────────────────────────────────────────────────────
let reportarFotos = []; // array de File

function cerrarMenuCtx() {
  document.getElementById('ctx-menu').style.display = 'none';
}

document.getElementById('btn-ctx-menu').addEventListener('click', e => {
  e.stopPropagation();
  const menu = document.getElementById('ctx-menu');
  menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
});

document.getElementById('ctx-reportar').addEventListener('click', () => {
  cerrarMenuCtx();
  abrirModalReportar();
});

document.addEventListener('click', e => {
  if (!document.getElementById('ctx-menu').contains(e.target) &&
      e.target !== document.getElementById('btn-ctx-menu')) {
    cerrarMenuCtx();
  }
});

function abrirModalReportar() {
  const tarea = state.detalleActivo;
  const tipoDestino = tarea.tipo === 'limpieza' ? 'Mantenimiento' : 'Limpieza';
  document.getElementById('modal-reportar-titulo').textContent = 'Reportar a ' + tipoDestino;
  document.getElementById('reportar-descripcion').value = '';
  document.getElementById('reportar-foto-preview').innerHTML = '';
  document.getElementById('reportar-foto-error').style.display  = 'none';
  document.getElementById('reportar-desc-error').style.display  = 'none';
  document.getElementById('reportar-enviar').disabled = false;
  document.getElementById('reportar-enviar').textContent = 'Enviar';
  reportarFotos = [];
  document.getElementById('reportar-file-camara').value   = '';
  document.getElementById('reportar-file-fototeca').value = '';
  document.getElementById('modal-reportar').classList.add('open');
}

function renderReportarFotos() {
  const preview = document.getElementById('reportar-foto-preview');
  preview.innerHTML = reportarFotos.map((file, i) => {
    const url = URL.createObjectURL(file);
    return `<div style="position:relative;flex-shrink:0">
      <img src="${url}" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid var(--border);display:block">
      <button class="btn-reportar-foto-borrar" data-i="${i}" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;border-radius:50%;background:var(--red);border:none;color:#fff;font-size:13px;cursor:pointer;line-height:1;padding:0">×</button>
    </div>`;
  }).join('');
  preview.style.display = reportarFotos.length ? 'flex' : 'none';
  preview.querySelectorAll('.btn-reportar-foto-borrar').forEach(btn => {
    btn.addEventListener('click', () => {
      reportarFotos.splice(parseInt(btn.dataset.i), 1);
      renderReportarFotos();
    });
  });
  if (reportarFotos.length) document.getElementById('reportar-foto-error').style.display = 'none';
}

function addReportarFotos(files) {
  reportarFotos.push(...Array.from(files));
  renderReportarFotos();
}

document.getElementById('reportar-btn-camara').addEventListener('click', () => {
  document.getElementById('reportar-file-camara').click();
});
document.getElementById('reportar-btn-fototeca').addEventListener('click', () => {
  document.getElementById('reportar-file-fototeca').click();
});
document.getElementById('reportar-file-camara').addEventListener('change', e => { addReportarFotos(e.target.files); e.target.value = ''; });
document.getElementById('reportar-file-fototeca').addEventListener('change', e => { addReportarFotos(e.target.files); e.target.value = ''; });

document.getElementById('reportar-cancelar').addEventListener('click', () => {
  document.getElementById('modal-reportar').classList.remove('open');
});
document.getElementById('modal-reportar').addEventListener('click', e => {
  if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
});

document.getElementById('reportar-enviar').addEventListener('click', async () => {
  const desc  = document.getElementById('reportar-descripcion').value.trim();
  const descErr  = document.getElementById('reportar-desc-error');
  const fotoErr  = document.getElementById('reportar-foto-error');
  descErr.style.display = 'none';
  fotoErr.style.display = 'none';

  let valid = true;
  if (!desc)                  { descErr.style.display = 'block'; valid = false; }
  if (!reportarFotos.length)  { fotoErr.style.display = 'block'; valid = false; }
  if (!valid) return;

  const btn = document.getElementById('reportar-enviar');
  btn.disabled = true; btn.textContent = 'Enviando…';

  try {
    const tarea = state.detalleActivo;
    const fd = new FormData();
    fd.append('descripcion', desc);
    reportarFotos.forEach(f => fd.append('fotos[]', prepareImage(f)));
    await api('POST', `/tareas/${tarea.tipo}/${tarea.id}/reportar`, fd);
    document.getElementById('modal-reportar').classList.remove('open');
    toast('Reporte enviado');
  } catch (e) {
    toast('Error: ' + e.message, 4000);
    btn.disabled = false; btn.textContent = 'Enviar';
  }
});

async function fichajeAccion(accion) {
  const btnId = accion === 'entrada' ? 'btn-entrada' : accion === 'pausa' ? 'btn-pausa' : 'btn-salida';
  const btn = document.getElementById(btnId);
  if (btn) { btn.disabled = true; btn.textContent = '…'; }

  try {
    await api('POST', `/fichaje/${accion}`);
    const data = await api('GET', '/fichaje/hoy');
    state.fichajeHoy    = data.fichaje;
    state.horasContrato = data.horas_contrato ?? state.horasContrato ?? null;
    state.tareasMin     = data.tareas_min ?? 0;
    state.pendientesPorDia = data.pendientes_por_dia || {};
    state.imputadoPorDia   = data.imputado_por_dia  || {};
    renderFichaje(data.fichaje, data.mes || [], state.horasContrato, state.tareasMin);
    toast(accion === 'entrada' ? '✓ Entrada fichada' : accion === 'salida' ? '✓ Salida fichada' : '✓ Pausa registrada');
  } catch (e) {
    toast(e.message);
    if (btn) { btn.disabled = false; }
  }
}

function calcHoras(ini, fin, pausaD, pausaH, horasContrato = null) {
  const toMin = t => { const [h, m] = t.split(':').map(Number); return h * 60 + m; };
  let mins = toMin(fin) - toMin(ini);
  let pausaMin = 0;
  if (pausaD && pausaH) pausaMin = toMin(pausaH) - toMin(pausaD);
  const franquicia = horasContrato !== null ? (horasContrato >= 40 ? 30 : 15) : 0;
  const descuento = Math.max(0, pausaMin - franquicia);
  mins -= descuento;
  if (mins < 0) return null;
  return `${Math.floor(mins / 60)}h ${mins % 60}min`;
}

function minsToStr(mins) {
  if (mins <= 0) return '0h 0min';
  return `${Math.floor(mins / 60)}h ${mins % 60}min`;
}

// ── Perfil ────────────────────────────────────────────────────────────────────
function renderPerfil() {
  const u = state.user;
  if (!u) return;

  document.getElementById('app-version').textContent = 'Versión ' + APP_VERSION;

  const inicial = (u.nombre || '?')[0].toUpperCase();
  const contrato = u.horas_contrato ? `${u.horas_contrato}h/semana` : '';
  document.getElementById('perfil-info').innerHTML = `
    <div class="user-avatar">${inicial}</div>
    <div>
      <div class="user-name">${esc(u.nombre)}</div>
      <div class="user-rol">${esc(u.rol || '')} · ${esc(u.mail)}</div>
      ${contrato ? `<div style="font-size:12px;color:var(--muted);margin-top:2px">Contrato: ${contrato}</div>` : ''}
    </div>`;

  // Bloque impersonación
  const banner = document.getElementById('impersonando-banner');
  const block  = document.getElementById('impersonar-block');

  if (state.asUser) {
    banner.style.display = 'block';
    block.style.display  = 'none';
    document.getElementById('impersonando-nombre').textContent = 'Viendo como: ' + state.asUser.nombre;
  } else if (u.is_admin) {
    banner.style.display = 'none';
    block.style.display  = 'block';
    cargarUsuariosImpersonar();
  } else {
    banner.style.display = 'none';
    block.style.display  = 'none';
  }
}

async function cargarUsuariosImpersonar() {
  try {
    const usuarios = await api('GET', '/usuarios');
    const sel = document.getElementById('impersonar-select');
    sel.innerHTML = usuarios
      .filter(u => u.id !== state.user.id)
      .map(u => `<option value="${u.id}" data-nombre="${esc(u.nombre)}">${esc(u.nombre)}</option>`)
      .join('');
  } catch {}
}

document.getElementById('btn-impersonar').addEventListener('click', () => {
  const sel    = document.getElementById('impersonar-select');
  const option = sel.options[sel.selectedIndex];
  if (!option) return;
  state.asUser = { id: parseInt(sel.value), nombre: option.dataset.nombre };
  store.set('vm_as_user', state.asUser);
  renderPerfil();
  loadTareas();
  toast('Viendo como: ' + state.asUser.nombre);
});

document.getElementById('btn-dejar-impersonar').addEventListener('click', () => {
  state.asUser = null;
  store.del('vm_as_user');
  renderPerfil();
  loadTareas();
  toast('Volviendo a tu usuario');
});

document.getElementById('logout-btn').addEventListener('click', () => {
  if (confirm('¿Cerrar sesión?')) doLogout();
});

document.getElementById('btn-actualizar-app').addEventListener('click', async () => {
  const btn = document.getElementById('btn-actualizar-app');
  btn.disabled = true;
  btn.textContent = 'Actualizando…';

  // Pedir permiso de notificaciones antes del reload para no perder el diálogo
  if ('Notification' in window && Notification.permission === 'default') {
    try { await Notification.requestPermission(); } catch {}
  }

  try {
    const keys = await caches.keys();
    await Promise.all(keys.map(k => caches.delete(k)));
    const regs = await navigator.serviceWorker.getRegistrations();
    await Promise.all(regs.map(r => r.unregister()));
  } catch {}
  setTimeout(() => window.location.reload(true), 400);
});

document.getElementById('btn-push-test').addEventListener('click', probarSuscripcionPush);

// ── Horario ───────────────────────────────────────────────────────────────────
let horarioSemana = null; // ISO week string actual en vista agenda

function isoWeek(date) {
  const d = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const day = d.getUTCDay() || 7;
  d.setUTCDate(d.getUTCDate() + 4 - day);
  const yearStart = new Date(Date.UTC(d.getUTCFullYear(), 0, 1));
  const week = Math.ceil((((d - yearStart) / 86400000) + 1) / 7);
  return `${d.getUTCFullYear()}-W${String(week).padStart(2, '0')}`;
}

function semanaLabel(desde, hasta) {
  const fmt = d => new Date(d + 'T12:00:00').toLocaleDateString('es-ES', { day: 'numeric', month: 'short' });
  return `${fmt(desde)} – ${fmt(hasta)}`;
}

function moverSemana(semana, delta) {
  const [y, w] = semana.split('-W').map(Number);
  const d = new Date(Date.UTC(y, 0, 1));
  d.setUTCDate(d.getUTCDate() + (w - 1) * 7 + delta * 7);
  return isoWeek(new Date(d));
}

async function loadHorario(semana) {
  if (!semana) semana = horarioSemana || isoWeek(new Date());
  horarioSemana = semana;

  const content = document.getElementById('horario-content');
  content.innerHTML = '<div class="spinner">Cargando…</div>';

  try {
    const data = await api('GET', `/agenda?semana=${semana}`);
    renderHorario(data);
  } catch (e) {
    content.innerHTML = `<div class="empty-state"><div class="icon">⚠️</div><p>${e.message}</p></div>`;
  }
}

function renderHorario(data) {
  const content  = document.getElementById('horario-content');
  const hoyStr   = new Date().toLocaleDateString('en-CA');
  const diasSem  = ['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'];
  const diasMap  = {};
  (data.dias || []).forEach(d => { diasMap[d.fecha] = d; });

  // Generar los 7 días de la semana
  let diasHtml = '';
  for (let i = 0; i < 7; i++) {
    const fecha = (() => {
      const d = new Date(data.desde + 'T12:00:00');
      d.setDate(d.getDate() + i);
      return d.toISOString().slice(0, 10);
    })();
    const esHoy = fecha === hoyStr;
    const dia   = diasMap[fecha];
    const [, m, dd] = fecha.split('-');
    const fechaStr = `${diasSem[new Date(fecha + 'T12:00:00').getDay()]} ${parseInt(dd)}/${parseInt(m)}`;

    let contenido = '';
    if (!dia) {
      contenido = `<span style="color:var(--muted);font-size:13px">Sin horario asignado</span>`;
    } else if (dia.tipo === 'turno') {
      const ini = dia.hora_inicio ? dia.hora_inicio.slice(0,5) : '--:--';
      const fin = dia.hora_fin    ? dia.hora_fin.slice(0,5)    : '--:--';
      contenido = `<span class="hbadge hbadge-turno" style="font-size:13px;padding:4px 10px">${ini} – ${fin}</span>`;
    } else {
      const cls = tipoBadgeClass(dia.tipo);
      contenido = `<span class="hbadge ${cls}" style="font-size:13px;padding:4px 10px">${esc(tipoLabel(dia.tipo))}</span>`;
    }

    diasHtml += `
      <div class="agenda-dia${esHoy ? ' hoy' : ''}">
        <div class="agenda-dia-header">
          <span class="agenda-dia-fecha">${fechaStr}</span>
          ${esHoy ? '<span class="agenda-dia-hoy-badge">Hoy</span>' : ''}
        </div>
        ${contenido}
      </div>`;
  }

  const esHoySemana = data.semana === isoWeek(new Date());
  content.innerHTML = `
    <div class="agenda-semana-nav">
      <button id="btn-sem-prev">‹</button>
      <span class="semana-label">${semanaLabel(data.desde, data.hasta)}</span>
      <button id="btn-sem-next">›</button>
      <button id="btn-sem-hoy" style="font-size:12px;padding:5px 10px${esHoySemana ? ';opacity:.35;pointer-events:none' : ''}">Hoy</button>
    </div>
    ${diasHtml}
    <div style="margin-top:16px">
      <button class="btn btn-outline" id="btn-ver-equipo">Ver todos</button>
    </div>`;

  document.getElementById('btn-sem-prev').addEventListener('click', () => loadHorario(moverSemana(horarioSemana, -1)));
  document.getElementById('btn-sem-next').addEventListener('click', () => loadHorario(moverSemana(horarioSemana, +1)));
  document.getElementById('btn-sem-hoy').addEventListener('click',  () => loadHorario(isoWeek(new Date())));
  document.getElementById('btn-ver-equipo').addEventListener('click', () => loadHorarioEquipo());
}

// Horario equipo
let equipoSemana = null;

async function loadHorarioEquipo(semana) {
  if (!semana) semana = equipoSemana || isoWeek(new Date());
  equipoSemana = semana;

  showScreen('horario-equipo');
  const content = document.getElementById('horario-equipo-content');
  content.innerHTML = '<div class="spinner">Cargando…</div>';

  try {
    const data = await api('GET', `/horario-equipo?semana=${semana}`);
    renderHorarioEquipo(data);
  } catch (e) {
    content.innerHTML = `<div class="empty-state"><div class="icon">⚠️</div><p>${e.message}</p></div>`;
  }
}

function renderHorarioEquipo(data) {
  const content  = document.getElementById('horario-equipo-content');
  const hoyStr   = new Date().toLocaleDateString('en-CA');
  const diasCortos = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

  // Cabecera de fechas
  const fechas = [];
  for (let i = 0; i < 7; i++) {
    const d = new Date(data.desde + 'T12:00:00');
    d.setDate(d.getDate() + i);
    fechas.push(d.toISOString().slice(0, 10));
  }

  const thFechas = fechas.map((f, i) => {
    const [, m, dd] = f.split('-');
    const esHoy = f === hoyStr;
    return `<th class="${esHoy ? 'col-hoy' : ''}">${diasCortos[i]}<br><span style="font-weight:400">${parseInt(dd)}/${parseInt(m)}</span></th>`;
  }).join('');

  let gruposHtml = '';
  for (const [rolNombre, usuarios] of Object.entries(data.grupos || {})) {
    gruposHtml += `<tr><td colspan="8" style="padding:14px 4px 6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--muted)">${esc(rolNombre)}</td></tr>`;
    for (const u of usuarios) {
      const celdas = fechas.map(f => {
        const dia = u.dias[f];
        const esHoy = f === hoyStr;
        if (!dia) return `<td class="${esHoy ? 'col-hoy' : ''}"><span style="color:var(--border)">—</span></td>`;
        if (dia.tipo === 'turno') {
          const ini = dia.hora_inicio ? dia.hora_inicio.slice(0,5) : '--';
          const fin = dia.hora_fin    ? dia.hora_fin.slice(0,5)    : '--';
          return `<td class="${esHoy ? 'col-hoy' : ''}"><span class="hbadge hbadge-turno">${ini}–${fin}</span></td>`;
        }
        const cls = tipoBadgeClass(dia.tipo);
        return `<td class="${esHoy ? 'col-hoy' : ''}"><span class="hbadge ${cls}">${esc(tipoLabel(dia.tipo))}</span></td>`;
      }).join('');
      gruposHtml += `<tr><td class="col-usuario">${esc(u.nombre)}</td>${celdas}</tr>`;
    }
  }

  const esHoyEquipo = data.semana === isoWeek(new Date());
  content.innerHTML = `
    <div class="agenda-semana-nav">
      <button id="btn-equipo-prev">‹</button>
      <span class="semana-label">${semanaLabel(data.desde, data.hasta)}</span>
      <button id="btn-equipo-next">›</button>
      <button id="btn-equipo-hoy" style="font-size:12px;padding:5px 10px${esHoyEquipo ? ';opacity:.35;pointer-events:none' : ''}">Hoy</button>
    </div>
    <div class="equipo-table-wrap">
      <table class="equipo-table">
        <thead><tr><th class="col-usuario">Usuario</th>${thFechas}</tr></thead>
        <tbody>${gruposHtml}</tbody>
      </table>
    </div>`;

  document.getElementById('btn-equipo-prev').addEventListener('click', () => loadHorarioEquipo(moverSemana(equipoSemana, -1)));
  document.getElementById('btn-equipo-next').addEventListener('click', () => loadHorarioEquipo(moverSemana(equipoSemana, +1)));
  document.getElementById('btn-equipo-hoy').addEventListener('click',  () => loadHorarioEquipo(isoWeek(new Date())));
}

document.getElementById('btn-volver-horario').addEventListener('click', () => {
  showScreen('horario');
});

document.getElementById('login-btn').addEventListener('click', doLogin);
document.getElementById('login-pass').addEventListener('keydown', e => {
  if (e.key === 'Enter') doLogin();
});

// ── Geolocalización ───────────────────────────────────────────────────────────
function getPosition() {
  return new Promise(resolve => {
    if (!navigator.geolocation) { resolve(null); return; }
    const timer = setTimeout(() => resolve(null), 5000);
    navigator.geolocation.getCurrentPosition(
      pos => { clearTimeout(timer); resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude }); },
      ()  => { clearTimeout(timer); resolve(null); }
    );
  });
}

// ── Utils ─────────────────────────────────────────────────────────────────────
function esc(s) {
  return String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function fmtFecha(d) {
  if (!d) return '—';
  const [y, m, day] = d.split('-');
  return `${day}/${m}/${y.slice(2)}`;
}

// ── Service Worker + Push ─────────────────────────────────────────────────────
async function suscribirPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) return;
  if (!state.token) return;

  try {
    // Pedir permiso explícitamente si aún no se ha concedido
    if (Notification.permission === 'denied') return;
    if (Notification.permission !== 'granted') {
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') return;
    }

    const reg = await navigator.serviceWorker.ready;
    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
      const keyData = await api('GET', '/vapid-public-key');
      const appServerKey = urlBase64ToUint8Array(keyData.key);
      sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: appServerKey });
    }
    const subJson = sub.toJSON();
    await api('POST', '/push/subscribe', {
      endpoint: subJson.endpoint,
      p256dh:   subJson.keys.p256dh,
      auth:     subJson.keys.auth,
    });
  } catch (e) {
    console.warn('Push subscription error:', e);
    toast('⚠️ Notificaciones: ' + (e.message || 'error desconocido'), 5000);
  }
}

async function probarSuscripcionPush() {
  const btn = document.getElementById('btn-push-test');
  if (btn) { btn.disabled = true; btn.textContent = 'Conectando…'; }

  try {
    if (!('serviceWorker' in navigator)) throw new Error('Service Worker no soportado');
    if (!('PushManager' in window))      throw new Error('Push no soportado en este navegador');
    if (!('Notification' in window))     throw new Error('Notificaciones no soportadas');

    if (Notification.permission === 'denied') throw new Error('Permiso denegado — actívalo en ajustes del sitio');

    if (Notification.permission !== 'granted') {
      const perm = await Notification.requestPermission();
      if (perm !== 'granted') throw new Error('Permiso rechazado');
    }

    const reg = await Promise.race([
      navigator.serviceWorker.ready,
      new Promise((_, rej) => setTimeout(() => rej(new Error('SW timeout')), 8000)),
    ]);

    let sub = await reg.pushManager.getSubscription();
    if (!sub) {
      const keyData = await api('GET', '/vapid-public-key');
      sub = await reg.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(keyData.key),
      });
    }

    const subJson = sub.toJSON();
    await api('POST', '/push/subscribe', {
      endpoint: subJson.endpoint,
      p256dh:   subJson.keys.p256dh,
      auth:     subJson.keys.auth,
    });

    if (btn) btn.textContent = '✓ Activadas';
  } catch (e) {
    toast('Notificaciones: ' + (e.message || 'error'), 5000);
    if (btn) { btn.disabled = false; btn.textContent = 'Activar notificaciones'; }
  }
}

function urlBase64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(base64);
  return Uint8Array.from([...raw].map(c => c.charCodeAt(0)));
}

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/pwa/sw.js').catch(() => {});
  navigator.serviceWorker.addEventListener('message', e => {
    if (e.data?.type === 'navigate' && e.data.url?.includes('#fichaje')) {
      navTo('fichaje');
    }
  });
}

// ── Nueva tarea ───────────────────────────────────────────────────────────────
async function cargarPropiedades() {
  if (state.propiedades.length) return;
  try {
    state.propiedades = await api('GET', '/propiedades');
  } catch {}
}

async function abrirModalNuevaTarea() {
  await cargarPropiedades();

  const hoy     = new Date().toLocaleDateString('en-CA');
  const limite  = new Date(); limite.setDate(limite.getDate() - 2);
  const minFecha = limite.toLocaleDateString('en-CA');

  const sel = document.getElementById('nt-propiedad');
  sel.innerHTML = state.propiedades.map(p =>
    `<option value="${p.id}">${esc(p.nombre)}</option>`
  ).join('');

  document.getElementById('nt-nombre').value  = '';
  document.getElementById('nt-tiempo').value  = '';
  document.getElementById('nt-fecha').value   = state.tareasFecha <= hoy && state.tareasFecha >= minFecha
    ? state.tareasFecha : hoy;
  document.getElementById('nt-fecha').min     = minFecha;
  document.getElementById('nt-fecha').max     = hoy;
  document.getElementById('nt-error').style.display = 'none';
  document.getElementById('nt-guardar').disabled    = false;
  document.getElementById('nt-guardar').textContent = 'Guardar';

  document.getElementById('modal-nueva-tarea').classList.add('open');
}

document.getElementById('btn-nueva-tarea').addEventListener('click', abrirModalNuevaTarea);

document.getElementById('nt-cancelar').addEventListener('click', () => {
  document.getElementById('modal-nueva-tarea').classList.remove('open');
});
document.getElementById('modal-nueva-tarea').addEventListener('click', e => {
  if (e.target === e.currentTarget) e.currentTarget.classList.remove('open');
});

document.getElementById('nt-guardar').addEventListener('click', async () => {
  const errEl     = document.getElementById('nt-error');
  const btn       = document.getElementById('nt-guardar');
  const propiedad = document.getElementById('nt-propiedad').value;
  const fecha     = document.getElementById('nt-fecha').value;
  const tiempo    = document.getElementById('nt-tiempo').value;
  const nombre    = document.getElementById('nt-nombre').value.trim();

  errEl.style.display = 'none';

  if (!propiedad || !fecha || !tiempo || !nombre) {
    errEl.textContent = 'Propiedad, tarea, fecha y tiempo son obligatorios';
    errEl.style.display = 'block';
    return;
  }

  btn.disabled = true; btn.textContent = 'Guardando…';
  try {
    await api('POST', '/tareas/crear', {
      id_propiedades:     parseInt(propiedad),
      fecha_finalizacion: fecha,
      tiempo,
      nombre,
    });
    document.getElementById('modal-nueva-tarea').classList.remove('open');
    toast('✓ Tarea creada');
    await loadTareas(fecha);
  } catch (e) {
    errEl.textContent = e.message;
    errEl.style.display = 'block';
    btn.disabled = false; btn.textContent = 'Guardar';
  }
});

// ── Boot ──────────────────────────────────────────────────────────────────────
if (state.token && state.user) {
  if (state.user.debe_cambiar_password) {
    mostrarCambioPassword();
  } else {
    navTo('fichaje');
    suscribirPush();
  }
  // Refrescar user para obtener is_admin y es_supervisor actualizados
  api('GET', '/me').then(fresh => {
    state.user = { ...state.user, ...fresh };
    store.set('vm_user', state.user);
    if (fresh.es_supervisor !== undefined) {
      state.esSupervisor = !!fresh.es_supervisor;
      store.set('vm_es_supervisor', state.esSupervisor);
    }
    renderPerfil();
    cargarUsuariosSubordinados();
  }).catch(() => renderPerfil());
} else {
  showScreen('login');
}
