// PWA de estudio (proyecto `estudio`). JS vanilla, sin dependencias.
// Datos: /api/estudio/*. Token Bearer guardado en localStorage (solo para no pedir login cada vez).
(function () {
  'use strict';

  const API = '/api/estudio';
  const KEY = 'estudio_token';
  const ESTADOS = ['Pendiente', 'En curso', 'Estudiado'];
  const SIGUIENTE = { 'Pendiente': 'En curso', 'En curso': 'Estudiado', 'Estudiado': 'Pendiente' };
  const COLORES = ['#4f46e5', '#0891b2', '#16a34a', '#d97706', '#dc2626', '#9333ea', '#db2777'];

  const S = {
    token: null,
    user: null,
    asignaturas: [],
    cron: null,        // cronómetro en marcha { id, id_tarea, tarea, inicio }
    tab: 'estudio',    // estudio | informes
    informe: 'diario', // diario | semanal | asignaturas
    datos: null,       // datos del informe activo
    abiertas: { asig: new Set(), tema: new Set() },
  };

  const app = document.getElementById('app');

  // ── Utilidades ───────────────────────────────────────────────────────

  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  const pad = (n) => String(n).padStart(2, '0');

  const hoy = () => {
    const d = new Date();
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  };

  const fmtMin = (m) => {
    m = Math.round(Number(m) || 0);
    const h = Math.floor(m / 60), r = m % 60;
    if (!h) return r + ' min';
    return h + ' h ' + pad(r) + ' min';
  };

  const fmtFecha = (iso) => {
    const [y, m, d] = iso.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-ES', { weekday: 'short', day: 'numeric', month: 'short' });
  };

  const fmtElapsed = (inicio) => {
    const s = Math.max(0, Math.floor((Date.now() - new Date(inicio).getTime()) / 1000));
    return pad(Math.floor(s / 3600)) + ':' + pad(Math.floor((s % 3600) / 60)) + ':' + pad(s % 60);
  };

  const colorDe = (id) => COLORES[Math.abs(Number(id)) % COLORES.length];

  const badgeEstado = (estado) => {
    const cls = estado === 'Estudiado' ? 'b-est' : estado === 'En curso' ? 'b-curso' : 'b-pend';
    return `<span class="badge ${cls}">${esc(estado)}</span>`;
  };

  function toast(msg) {
    const old = document.querySelector('.toast');
    if (old) old.remove();
    const el = document.createElement('div');
    el.className = 'toast';
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2800);
  }

  // ── Persistencia del token ───────────────────────────────────────────

  function guardarToken(t) { try { localStorage.setItem(KEY, t); } catch (e) { /* modo privado: sin persistir */ } }
  function leerToken() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
  function borrarToken() { try { localStorage.removeItem(KEY); } catch (e) { /* ignorar */ } }

  // ── API ──────────────────────────────────────────────────────────────

  async function api(method, path, body) {
    const headers = { Accept: 'application/json' };
    if (body !== undefined) headers['Content-Type'] = 'application/json';
    if (S.token) headers.Authorization = 'Bearer ' + S.token;

    const res = await fetch(API + path, {
      method,
      headers,
      body: body !== undefined ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));

    if (res.status === 401 && path !== '/login') {
      logout(true);
      throw new Error('Tu sesión ha caducado. Vuelve a entrar.');
    }
    if (!res.ok) {
      throw new Error(data.error || data.message || 'Error ' + res.status);
    }
    return data;
  }

  async function cargarArbol() {
    const d = await api('GET', '/arbol');
    S.asignaturas = d.asignaturas;
    S.cron = d.cronometro;
  }

  async function cargarInforme() {
    if (S.informe === 'diario') S.datos = await api('GET', '/informes/diario?desde=' + hace(13) + '&hasta=' + hoy());
    if (S.informe === 'semanal') S.datos = await api('GET', '/informes/semanal?semanas=8');
    if (S.informe === 'asignaturas') S.datos = await api('GET', '/informes/asignaturas?desde=' + hace(29) + '&hasta=' + hoy());
  }

  function hace(dias) {
    const d = new Date();
    d.setDate(d.getDate() - dias);
    return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
  }

  // Ejecuta una acción, refresca los datos y pinta. Los errores salen como aviso.
  async function accion(fn) {
    try {
      await fn();
      await cargarArbol();
    } catch (e) {
      toast(e.message);
    }
    render();
  }

  // ── Sesión ───────────────────────────────────────────────────────────

  async function login(email, password, remember) {
    const res = await api('POST', '/login', { email, password, remember });
    S.token = res.token;
    S.user = res.user;
    guardarToken(res.token);
    await cargarArbol();
  }

  function logout(silencioso) {
    if (S.token && !silencioso) {
      fetch(API + '/logout', { method: 'POST', headers: { Authorization: 'Bearer ' + S.token, Accept: 'application/json' } }).catch(() => {});
    }
    borrarToken();
    S.token = null;
    S.user = null;
    S.asignaturas = [];
    S.cron = null;
    S.datos = null;
    render();
  }

  // ── Render ───────────────────────────────────────────────────────────

  function render() {
    if (!S.token) return renderLogin();
    app.innerHTML = `
      <div class="row" style="margin-bottom:8px">
        <div class="grow"><h1 style="margin:0">Estudio</h1><div class="muted">${esc(S.user?.nombre || '')}</div></div>
        <button class="btn ghost small" data-a="logout">Salir</button>
      </div>
      ${S.cron ? timerHtml() : ''}
      ${S.tab === 'estudio' ? vistaEstudio() : vistaInformes()}
      <nav class="nav">
        <button data-a="tab" data-v="estudio" class="${S.tab === 'estudio' ? 'on' : ''}">📚 Asignaturas</button>
        <button data-a="tab" data-v="informes" class="${S.tab === 'informes' ? 'on' : ''}">📊 Informes</button>
      </nav>`;
  }

  function renderLogin() {
    app.innerHTML = `
      <form class="login card" id="login-form">
        <h1>Estudio</h1>
        <label for="email">Email</label>
        <input id="email" name="email" type="email" autocomplete="username" required>
        <label for="password">Contraseña</label>
        <input id="password" name="password" type="password" autocomplete="current-password" required>
        <label class="row" style="margin-top:12px;color:var(--text)">
          <input type="checkbox" name="remember" style="width:auto"> Mantener sesión 30 días
        </label>
        <button class="btn full" style="margin-top:14px" type="submit">Entrar</button>
      </form>`;
  }

  function timerHtml() {
    return `
      <div class="timer">
        <div class="row">
          <div class="grow">
            <div class="muted">Estudiando</div>
            <strong>${esc(S.cron.tarea)}</strong>
            <div id="timer-el" style="font-size:24px;font-variant-numeric:tabular-nums">${fmtElapsed(S.cron.inicio)}</div>
          </div>
          <button class="btn" style="background:#fff;color:var(--brand)" data-a="detener">Detener</button>
        </div>
      </div>`;
  }

  function vistaEstudio() {
    const cabecera = `
      <div class="section-title">
        <h2>Mis asignaturas</h2>
        <button class="btn small" data-a="nueva-asig">+ Asignatura</button>
      </div>`;

    if (!S.asignaturas.length) {
      return cabecera + `<div class="card muted">Aún no tienes asignaturas. Añade la primera con «+ Asignatura».</div>`;
    }

    return cabecera + S.asignaturas.map((a) => {
      const abierta = S.abiertas.asig.has(a.id);
      return `
        <div class="card">
          <div class="row">
            <span class="dot" style="background:${esc(a.color || colorDe(a.id))}"></span>
            <button class="grow" style="text-align:left" data-a="toggle-asig" data-id="${a.id}">
              <strong>${esc(a.nombre)}</strong>
              <div class="muted">${esc(a.curso)} · ${fmtMin(a.minutos)}</div>
            </button>
            <button class="btn ghost small" data-a="editar-asig" data-id="${a.id}">✎</button>
            <button class="btn danger small" data-a="borrar-asig" data-id="${a.id}">🗑</button>
          </div>
          ${abierta ? temasHtml(a) : ''}
        </div>`;
    }).join('');
  }

  function temasHtml(a) {
    const temas = a.temas.map((t) => {
      const abierto = S.abiertas.tema.has(t.id);
      return `
        <div class="group">
          <div class="row">
            <button class="grow" style="text-align:left" data-a="toggle-tema" data-id="${t.id}">
              ${esc(t.nombre)} <span class="muted">· ${fmtMin(t.minutos)}</span>
            </button>
            ${badgeEstado(t.estado)}
            <button class="btn ghost small" data-a="editar-tema" data-id="${t.id}">✎</button>
            <button class="btn danger small" data-a="borrar-tema" data-id="${t.id}">🗑</button>
          </div>
          ${abierto ? tareasHtml(t) : ''}
        </div>`;
    }).join('');

    return `
      <div class="indent" style="margin-top:8px">
        ${temas || '<div class="muted">Sin temas todavía.</div>'}
        <button class="btn ghost small full" style="margin-top:8px" data-a="nuevo-tema" data-id="${a.id}">+ Tema</button>
      </div>`;
  }

  function tareasHtml(t) {
    const filas = t.tareas.map((k) => {
      const enMarcha = S.cron && S.cron.id_tarea === k.id;
      const estimado = k.tiempo_estimado_min ? ' / ' + fmtMin(k.tiempo_estimado_min) : '';
      return `
        <div class="row" style="padding:6px 0">
          <button class="badge ${k.estado === 'Estudiado' ? 'b-est' : k.estado === 'En curso' ? 'b-curso' : 'b-pend'}"
                  data-a="estado-tarea" data-id="${k.id}" data-estado="${esc(k.estado)}" title="Cambiar estado">${esc(k.estado)}</button>
          <div class="grow" style="min-width:0">
            <div>${esc(k.nombre)}</div>
            <div class="muted"><button data-a="estimado-tarea" data-id="${k.id}" data-val="${k.tiempo_estimado_min || ''}" style="text-decoration:underline">${fmtMin(k.minutos)}${estimado || ' · estimar'}</button></div>
          </div>
          <button class="btn ${enMarcha ? 'danger' : ''} small" data-a="${enMarcha ? 'detener' : 'play'}" data-id="${k.id}">${enMarcha ? '■' : '▶'}</button>
          <button class="btn ghost small" data-a="manual" data-id="${k.id}" title="Añadir minutos">+min</button>
          <button class="btn danger small" data-a="borrar-tarea" data-id="${k.id}">🗑</button>
        </div>`;
    }).join('');

    return `
      <div class="indent" style="margin-top:6px">
        ${filas || '<div class="muted">Sin tareas. Añade una para poder estudiarla.</div>'}
        <button class="btn ghost small full" style="margin-top:6px" data-a="nueva-tarea" data-id="${t.id}">+ Tarea</button>
      </div>`;
  }

  function vistaInformes() {
    const tabs = [['diario', 'Diario'], ['semanal', 'Semanal'], ['asignaturas', 'Por asignatura']]
      .map(([v, l]) => `<button class="btn ${S.informe === v ? '' : 'ghost'} small" data-a="informe" data-v="${v}">${l}</button>`)
      .join(' ');

    let cuerpo = '<div class="muted">Cargando…</div>';
    if (S.datos) {
      if (S.informe === 'diario') cuerpo = informeDiarioHtml(S.datos);
      if (S.informe === 'semanal') cuerpo = informeSemanalHtml(S.datos);
      if (S.informe === 'asignaturas') cuerpo = informeAsignaturasHtml(S.datos);
    }

    return `<h1>Informes de estudio</h1><div class="row" style="margin-bottom:12px;flex-wrap:wrap">${tabs}</div>${cuerpo}`;
  }

  function informeDiarioHtml(d) {
    const max = Math.max(60, ...d.dias.map((x) => x.minutos));
    const filas = d.dias.map((x) => `
      <div>
        <div class="day">
          <span class="muted">${esc(fmtFecha(x.fecha))}</span>
          <div class="bar"><span style="width:${Math.round((x.minutos / max) * 100)}%"></span></div>
          <strong style="text-align:right">${x.minutos} m</strong>
        </div>
        ${x.asignaturas.length ? `<div class="muted" style="padding:0 0 6px 92px">${x.asignaturas.map((a) => esc(a.nombre) + ' ' + a.minutos + ' m').join(' · ')}</div>` : ''}
      </div>`).join('');
    return `
      <div class="card"><div class="muted">Total ${esc(d.desde)} → ${esc(d.hasta)}</div><strong style="font-size:20px">${fmtMin(d.total_minutos)}</strong></div>
      <div class="card">${filas}</div>`;
  }

  function informeSemanalHtml(d) {
    const max = Math.max(60, ...d.semanas.map((x) => x.minutos));
    return `<div class="card">${d.semanas.map((s) => `
      <div class="day" style="grid-template-columns:110px 1fr 56px">
        <span class="muted">${esc(fmtFecha(s.inicio))}</span>
        <div><div class="bar"><span style="width:${Math.round((s.minutos / max) * 100)}%"></span></div>
        <div class="muted">${s.dias_estudiados} días con estudio</div></div>
        <strong style="text-align:right">${fmtMin(s.minutos)}</strong>
      </div>`).join('')}</div>`;
  }

  function informeAsignaturasHtml(d) {
    if (!d.asignaturas.length) return '<div class="card muted">Sin asignaturas todavía.</div>';
    const max = Math.max(60, ...d.asignaturas.map((a) => a.minutos));
    return `<div class="card">${d.asignaturas.map((a) => `
      <div style="padding:8px 0">
        <div class="row"><strong class="grow">${esc(a.nombre)}</strong><span>${fmtMin(a.minutos)}</span></div>
        <div class="bar" style="margin:6px 0"><span style="width:${Math.round((a.minutos / max) * 100)}%"></span></div>
        <div class="muted">${a.temas_estudiados} de ${a.temas_total} temas estudiados · ${esc(a.curso)}</div>
      </div>`).join('')}</div>`;
  }

  // ── Acciones (delegación de eventos) ─────────────────────────────────

  const num = (el) => Number(el.dataset.id);

  const acciones = {
    logout: () => logout(false),

    tab: async (el) => {
      S.tab = el.dataset.v;
      S.datos = null;
      render();
      if (S.tab === 'informes') {
        try { await cargarInforme(); } catch (e) { toast(e.message); }
        render();
      }
    },

    informe: async (el) => {
      S.informe = el.dataset.v;
      S.datos = null;
      render();
      try { await cargarInforme(); } catch (e) { toast(e.message); }
      render();
    },

    'toggle-asig': (el) => { toggle(S.abiertas.asig, num(el)); render(); },
    'toggle-tema': (el) => { toggle(S.abiertas.tema, num(el)); render(); },

    'nueva-asig': () => accion(async () => {
      const nombre = (prompt('Nombre de la asignatura') || '').trim();
      if (!nombre) return;
      const curso = (prompt('Curso', '2º ESO') || '').trim();
      if (!curso) return;
      await api('POST', '/asignaturas', { nombre, curso });
    }),

    'editar-asig': (el) => accion(async () => {
      const a = S.asignaturas.find((x) => x.id === num(el));
      const nombre = (prompt('Nombre', a?.nombre || '') || '').trim();
      if (!nombre) return;
      await api('PATCH', '/asignaturas/' + a.id, { nombre });
    }),

    'borrar-asig': (el) => accion(async () => {
      if (!confirm('¿Borrar esta asignatura con sus temas y tareas?')) return;
      await api('DELETE', '/asignaturas/' + num(el));
    }),

    'nuevo-tema': (el) => accion(async () => {
      const nombre = (prompt('Nombre del tema') || '').trim();
      if (!nombre) return;
      S.abiertas.asig.add(num(el));
      await api('POST', '/temas', { id_asignatura: num(el), nombre });
    }),

    'editar-tema': (el) => accion(async () => {
      const nombre = (prompt('Nombre del tema') || '').trim();
      if (!nombre) return;
      await api('PATCH', '/temas/' + num(el), { nombre });
    }),

    'borrar-tema': (el) => accion(async () => {
      if (!confirm('¿Borrar este tema con sus tareas?')) return;
      await api('DELETE', '/temas/' + num(el));
    }),

    'nueva-tarea': (el) => accion(async () => {
      const nombre = (prompt('Nombre de la tarea (p. ej. «Leer pág. 40-45»)') || '').trim();
      if (!nombre) return;
      S.abiertas.tema.add(num(el));
      await api('POST', '/tareas', { id_tema: num(el), nombre });
    }),

    'estado-tarea': (el) => accion(async () => {
      const nuevo = SIGUIENTE[el.dataset.estado] || ESTADOS[0];
      await api('PATCH', '/tareas/' + num(el), { estado: nuevo });
    }),

    'estimado-tarea': (el) => accion(async () => {
      const v = prompt('Minutos estimados (vacío para quitar)', el.dataset.val || '');
      if (v === null) return;
      const minutos = v.trim() === '' ? null : Math.max(0, parseInt(v, 10) || 0);
      await api('PATCH', '/tareas/' + num(el), { tiempo_estimado_min: minutos });
    }),

    'borrar-tarea': (el) => accion(async () => {
      if (!confirm('¿Borrar esta tarea?')) return;
      await api('DELETE', '/tareas/' + num(el));
    }),

    play: (el) => accion(async () => {
      const res = await api('POST', '/cronometro/iniciar', { id_tarea: num(el) });
      S.cron = res.cronometro;
    }),

    detener: () => accion(async () => {
      const res = await api('POST', '/cronometro/detener');
      S.cron = null;
      if (res.sesion) toast('Registrado: ' + res.sesion.minutos + ' min');
    }),

    manual: (el) => accion(async () => {
      const v = prompt('¿Cuántos minutos has estudiado hoy?', '30');
      const minutos = parseInt(v || '', 10);
      if (!minutos || minutos < 1) return;
      await api('POST', '/sesiones', { id_tarea: num(el), fecha: hoy(), minutos });
    }),
  };

  function toggle(set, id) {
    if (set.has(id)) set.delete(id); else set.add(id);
  }

  document.addEventListener('click', (e) => {
    const el = e.target.closest('[data-a]');
    if (!el || !acciones[el.dataset.a]) return;
    acciones[el.dataset.a](el);
  });

  document.addEventListener('submit', async (e) => {
    if (e.target.id !== 'login-form') return;
    e.preventDefault();
    const f = e.target;
    try {
      await login(f.email.value.trim(), f.password.value, f.remember.checked);
      render();
    } catch (err) {
      toast(err.message);
    }
  });

  // Refresca solo el contador en marcha (sin repintar toda la pantalla)
  setInterval(() => {
    const el = document.getElementById('timer-el');
    if (el && S.cron) el.textContent = fmtElapsed(S.cron.inicio);
  }, 1000);

  // ── Arranque ─────────────────────────────────────────────────────────

  async function iniciar() {
    S.token = leerToken();
    if (!S.token) return render();
    try {
      await cargarArbol();
      const me = await api('GET', '/me');
      S.user = me;
      render();
    } catch (e) {
      logout(true);
    }
  }

  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('/estudio/sw.js', { scope: '/estudio/' }).catch(() => {});
  }

  iniciar();
})();
