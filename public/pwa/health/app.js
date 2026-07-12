const API = '/api/health';

// ── State ─────────────────────────────────────────────────────────────────────
let token = localStorage.getItem('health_token');
let user  = JSON.parse(localStorage.getItem('health_user') || 'null');
let currentDate = todayStr();
let badFlags = {};
let historyData = [];

// ── Utils ─────────────────────────────────────────────────────────────────────
function todayStr() {
  return new Date().toISOString().slice(0, 10);
}

function fmtDate(str) {
  const [y, m, d] = str.split('-');
  const names = ['ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic'];
  return `${parseInt(d)} ${names[parseInt(m)-1]} ${y}`;
}

function isToday(str) { return str === todayStr(); }

function toast(msg, ms = 2200) {
  const el = document.getElementById('toast');
  el.textContent = msg;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), ms);
}

async function api(method, path, body) {
  const opts = {
    method,
    headers: { 'Content-Type': 'application/json', ...(token ? { Authorization: `Bearer ${token}` } : {}) },
  };
  if (body !== undefined) opts.body = JSON.stringify(body);
  const r = await fetch(API + path, opts);
  const json = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(json.error || json.message || r.statusText);
  return json;
}

// ── Auth ──────────────────────────────────────────────────────────────────────
function showLogin() {
  document.getElementById('login-screen').style.display = 'flex';
  document.getElementById('app-screen').style.display = 'none';
}

function showApp() {
  document.getElementById('login-screen').style.display = 'none';
  document.getElementById('app-screen').style.display = 'block';
  document.getElementById('header-user').textContent = user?.nombre ?? '';
}

document.getElementById('login-btn').addEventListener('click', async () => {
  const email = document.getElementById('login-email').value.trim();
  const pass  = document.getElementById('login-pass').value;
  const errEl = document.getElementById('login-error');
  const btn   = document.getElementById('login-btn');
  errEl.textContent = '';
  btn.disabled = true;
  btn.textContent = 'Entrando...';
  try {
    const data = await api('POST', '/login', { email, password: pass, remember: true });
    token = data.token;
    user  = data.user;
    localStorage.setItem('health_token', token);
    localStorage.setItem('health_user', JSON.stringify(user));
    showApp();
    loadDay(currentDate);
    loadHistory();
  } catch (e) {
    errEl.textContent = e.message;
  } finally {
    btn.disabled = false;
    btn.textContent = 'Entrar';
  }
});

document.getElementById('login-pass').addEventListener('keydown', e => {
  if (e.key === 'Enter') document.getElementById('login-btn').click();
});

document.getElementById('logout-btn').addEventListener('click', async () => {
  try { await api('POST', '/logout'); } catch (_) {}
  localStorage.removeItem('health_token');
  localStorage.removeItem('health_user');
  token = null; user = null;
  showLogin();
});

// ── Date nav ──────────────────────────────────────────────────────────────────
function setDate(str) {
  currentDate = str;
  const label = document.getElementById('date-label');
  label.textContent = isToday(str) ? 'Hoy, ' + fmtDate(str) : fmtDate(str);
  document.getElementById('next-day').disabled = isToday(str);
  loadDay(str);
}

document.getElementById('prev-day').addEventListener('click', () => {
  const d = new Date(currentDate + 'T12:00:00');
  d.setDate(d.getDate() - 1);
  setDate(d.toISOString().slice(0, 10));
});

document.getElementById('next-day').addEventListener('click', () => {
  if (isToday(currentDate)) return;
  const d = new Date(currentDate + 'T12:00:00');
  d.setDate(d.getDate() + 1);
  setDate(d.toISOString().slice(0, 10));
});

document.getElementById('today-btn').addEventListener('click', () => setDate(todayStr()));

// ── Sections (accordion) ──────────────────────────────────────────────────────
document.querySelectorAll('.section-header').forEach(header => {
  header.addEventListener('click', e => {
    if (e.target.classList.contains('bad-btn')) return;
    const section = header.dataset.section;
    const body    = document.getElementById('body-' + section);
    const chevron = header.querySelector('.chevron');
    const open    = body.classList.toggle('open');
    header.classList.toggle('open', open);
    chevron.classList.toggle('open', open);
  });
});

document.querySelectorAll('.bad-btn').forEach(btn => {
  btn.addEventListener('click', e => {
    e.stopPropagation();
    const field = btn.dataset.field;
    badFlags[field] = !badFlags[field];
    btn.classList.toggle('active', badFlags[field]);
  });
});

// ── Load day ──────────────────────────────────────────────────────────────────
async function loadDay(date) {
  try {
    const log = await api('GET', `/log/${date}`);
    // Weight
    document.getElementById('f-weight').value = log.weight_kg ?? '';
    // Meals
    const fields = ['breakfast','mid_morning','lunch','snack','dinner'];
    fields.forEach(f => {
      const el = document.getElementById('f-' + f);
      if (el) el.value = log[f] ?? '';
      const badKey = f + '_bad';
      badFlags[badKey] = !!log[badKey];
      const btn = document.querySelector(`.bad-btn[data-field="${badKey}"]`);
      if (btn) btn.classList.toggle('active', badFlags[badKey]);
    });
    document.getElementById('f-sport').value = log.sport ?? '';
  } catch (e) {
    if (e.message.includes('401')) { localStorage.removeItem('health_token'); showLogin(); }
    else toast('Error al cargar: ' + e.message);
  }
}

// ── Save ──────────────────────────────────────────────────────────────────────
document.getElementById('save-btn').addEventListener('click', async () => {
  const btn     = document.getElementById('save-btn');
  const savedEl = document.getElementById('saved-msg');
  btn.disabled  = true;
  btn.textContent = 'Guardando...';

  const body = {
    weight_kg:       document.getElementById('f-weight').value || null,
    breakfast:       document.getElementById('f-breakfast').value || null,
    breakfast_bad:   badFlags.breakfast_bad   ?? false,
    mid_morning:     document.getElementById('f-mid_morning').value || null,
    mid_morning_bad: badFlags.mid_morning_bad ?? false,
    lunch:           document.getElementById('f-lunch').value || null,
    lunch_bad:       badFlags.lunch_bad       ?? false,
    snack:           document.getElementById('f-snack').value || null,
    snack_bad:       badFlags.snack_bad       ?? false,
    dinner:          document.getElementById('f-dinner').value || null,
    dinner_bad:      badFlags.dinner_bad      ?? false,
    sport:           document.getElementById('f-sport').value || null,
  };

  try {
    await api('PUT', `/log/${currentDate}`, body);
    savedEl.classList.add('show');
    setTimeout(() => savedEl.classList.remove('show'), 2000);
    loadHistory();
  } catch (e) {
    toast('Error al guardar: ' + e.message);
  } finally {
    btn.disabled = false;
    btn.textContent = 'Guardar';
  }
});

// ── Weight chart ──────────────────────────────────────────────────────────────
async function loadHistory() {
  try {
    historyData = await api('GET', '/weight/history');
    renderChart();
  } catch (_) {}
}

function renderChart() {
  const svg    = document.getElementById('weight-chart');
  const W = 560, H = 140, PAD = { top: 16, bottom: 32, left: 8, right: 8 };
  const innerW = W - PAD.left - PAD.right;
  const innerH = H - PAD.top - PAD.bottom;

  // Build 15-day window ending today
  const days = [];
  for (let i = 14; i >= 0; i--) {
    const d = new Date();
    d.setDate(d.getDate() - i);
    days.push(d.toISOString().slice(0, 10));
  }

  const byDate = {};
  historyData.forEach(r => { byDate[r.date] = r; });

  const weights = days.map(d => byDate[d]?.weight ?? null);
  const valid   = weights.filter(v => v !== null);

  let paths = '';
  let dots  = '';
  let icons = '';
  let labels = '';

  if (valid.length > 0) {
    const minW  = Math.min(...valid) - 1;
    const maxW  = Math.max(...valid) + 1;
    const range = maxW - minW || 1;

    const toX = i  => PAD.left + (i / 14) * innerW;
    const toY = v  => PAD.top  + (1 - (v - minW) / range) * innerH;

    // Line path through non-null points
    const segments = [];
    let seg = [];
    weights.forEach((w, i) => {
      if (w !== null) {
        seg.push([toX(i), toY(w)]);
      } else {
        if (seg.length) { segments.push(seg); seg = []; }
      }
    });
    if (seg.length) segments.push(seg);

    segments.forEach(s => {
      const d = s.map((p, j) => `${j === 0 ? 'M' : 'L'}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(' ');
      paths += `<path d="${d}" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"/>`;
    });

    // Dots + weight labels
    weights.forEach((w, i) => {
      if (w === null) return;
      const x = toX(i), y = toY(w);
      const isCurrent = days[i] === currentDate;
      dots += `<circle cx="${x.toFixed(1)}" cy="${y.toFixed(1)}" r="${isCurrent ? 6 : 4}"
        fill="${isCurrent ? 'var(--accent)' : 'var(--surface)'}"
        stroke="var(--accent)" stroke-width="2"/>`;
      dots += `<text x="${x.toFixed(1)}" y="${(y - 9).toFixed(1)}" text-anchor="middle"
        font-size="9" fill="var(--muted)" font-family="-apple-system,sans-serif">${w.toFixed(1)}</text>`;
    });

    // Sport & bad icons below X axis
    days.forEach((date, i) => {
      const row = byDate[date];
      if (!row) return;
      const x = toX(i);
      const yBase = H - PAD.bottom + 6;
      if (row.has_sport) icons += `<text x="${x.toFixed(1)}" y="${yBase}" text-anchor="middle" font-size="10">🏃</text>`;
      if (row.any_bad)   icons += `<text x="${(x + (row.has_sport ? 10 : 0)).toFixed(1)}" y="${yBase}" text-anchor="middle" font-size="10">😈</text>`;
    });

    // Min/max labels on Y axis
    labels += `<text x="${(PAD.left).toFixed(1)}" y="${(PAD.top + 10).toFixed(1)}" font-size="9" fill="var(--muted)" font-family="-apple-system,sans-serif">${maxW.toFixed(1)}</text>`;
    labels += `<text x="${(PAD.left).toFixed(1)}" y="${(PAD.top + innerH).toFixed(1)}" font-size="9" fill="var(--muted)" font-family="-apple-system,sans-serif">${minW.toFixed(1)}</text>`;
  } else {
    paths = `<text x="${W/2}" y="${H/2}" text-anchor="middle" font-size="13" fill="var(--muted)" font-family="-apple-system,sans-serif">Sin datos de peso aún</text>`;
  }

  svg.innerHTML = paths + labels + dots + icons;
}

// ── Init ──────────────────────────────────────────────────────────────────────
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/pwa/health/sw.js').catch(() => {});
}

if (token && user) {
  showApp();
  setDate(currentDate);
  loadHistory();
} else {
  showLogin();
}
