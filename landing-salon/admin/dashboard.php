<?php session_start(); if (!($_SESSION['tetoca_admin'] ?? false)) { header('Location: index.php'); exit; } ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TeToca — Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter',sans-serif; background:#fdf6f0; color:#333; }
header { background:#fff; border-bottom:1px solid #e8ddd6; padding:16px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
header h1 { font-size:20px; color:#b76e79; }
header a { color:#999; text-decoration:none; font-size:14px; transition:.2s; }
header a:hover { color:#b76e79; }
.tabs { display:flex; gap:2px; background:#fff; border-bottom:1px solid #e8ddd6; padding:0 24px; overflow-x:auto; position:sticky; top:57px; z-index:99; }
.tab-btn { padding:12px 20px; font-size:14px; cursor:pointer; border:none; background:transparent; color:#888; font-family:inherit; white-space:nowrap; transition:.2s; border-bottom:2px solid transparent; }
.tab-btn:hover { color:#b76e79; background:#fdf6f0; }
.tab-btn.active { color:#b76e79; border-bottom-color:#b76e79; font-weight:600; }
.tab-content { display:none; }
.tab-content.active { display:block; }
.container { max-width:1000px; margin:0 auto; padding:24px; }

/* Stats cards */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.04); text-align:center; }
.stat-card .num { font-size:32px; font-weight:700; color:#b76e79; }
.stat-card .label { font-size:13px; color:#999; margin-top:4px; }

/* Cards */
.card { background:#fff; border-radius:16px; padding:24px; margin-bottom:24px; box-shadow:0 4px 16px rgba(0,0,0,.06); }
.card h2 { font-size:18px; margin-bottom:16px; color:#555; }

/* Table */
table { width:100%; border-collapse:collapse; }
th,td { text-align:left; padding:12px 8px; border-bottom:1px solid #f0ebe7; font-size:14px; }
th { color:#999; font-weight:500; text-transform:uppercase; font-size:12px; letter-spacing:.5px; }

/* Buttons */
.btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:10px; font-size:13px; cursor:pointer; border:none; text-decoration:none; transition:.2s; font-family:inherit; }
.btn-primary { background:#b76e79; color:#fff; }
.btn-primary:hover { background:#a05f69; }
.btn-ghost { background:transparent; color:#666; border:1.5px solid #e8ddd6; }
.btn-ghost:hover { border-color:#b76e79; color:#b76e79; }
.btn-danger { background:#fef2f2; color:#e74c3c; }
.btn-danger:hover { background:#fee2e2; }
.btn-sm { padding:5px 10px; font-size:12px; }
.btn-xs { padding:3px 8px; font-size:11px; border-radius:6px; }
.actions { display:flex; gap:6px; flex-wrap:wrap; }

/* Forms */
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
.form-row.three { grid-template-columns:1fr 1fr 1fr; }
label { display:block; font-size:13px; color:#666; margin-bottom:4px; }
input,textarea,select { width:100%; padding:10px 12px; border:1.5px solid #e8ddd6; border-radius:10px; font-size:14px; outline:none; font-family:inherit; transition:.2s; }
input:focus,textarea:focus { border-color:#b76e79; box-shadow:0 0 0 3px rgba(183,110,121,.15); }
textarea { resize:vertical; min-height:60px; }
.form-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:8px; }
.hidden { display:none !important; }

/* Toast */
.toast { position:fixed; bottom:24px; right:24px; background:#333; color:#fff; padding:12px 20px; border-radius:12px; font-size:14px; opacity:0; transition:.3s; z-index:9999; pointer-events:none; }
.toast.show { opacity:1; }

/* Servicios styles */
.precio { font-weight:600; color:#b76e79; }
.duracion { color:#888; }

/* Horarios styles */
.break-row { display:flex; gap:4px; align-items:center; margin-bottom:4px; }
.break-row input { width:80px; padding:6px 8px; font-size:12px; }
.breaks-container input:disabled { opacity:.4; }
#form-horarios td { vertical-align:top; }
#form-horarios input[type="checkbox"] { width:20px; height:20px; cursor:pointer; }
#form-horarios input[type="time"] { width:110px; padding:6px 8px; font-size:13px; }
.wa-loading { color:#999; font-size:14px; padding:32px; }
.wa-error { color:#e74c3c; font-size:14px; padding:32px; }
.wa-retry { color:#999; font-size:12px; margin-top:8px; }

/* Calendar */
.cal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.cal-header h3 { font-size:18px; color:#555; }
.cal-nav { display:flex; gap:8px; }
.cal-nav button { background:#fff; border:1.5px solid #e8ddd6; border-radius:10px; padding:8px 14px; cursor:pointer; font-size:14px; color:#555; transition:.2s; font-family:inherit; }
.cal-nav button:hover { border-color:#b76e79; color:#b76e79; }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; background:#f0ebe7; border-radius:12px; overflow:hidden; }
.cal-cell { background:#fff; min-height:100px; padding:4px; font-size:12px; cursor:pointer; transition:.2s; overflow:hidden; }
.cal-cell:hover { background:#fdf6f0; }
.cal-cell.other-month { background:#faf8f6; color:#ccc; }
.cal-cell.today { background:#f5e1e4; }
.cal-cell .day-num { font-weight:600; font-size:13px; padding:2px 4px; color:#666; }
.cal-cell.today .day-num { color:#b76e79; }
.cal-cell .cal-appt { padding:2px 4px; margin:1px 0; border-radius:4px; font-size:11px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#fff; transition:.15s; }
.cal-cell .cal-appt:hover { opacity:.8; transform:scale(1.02); }
.cal-cell .cal-appt.-confirmed { background:#b76e79; }
.cal-cell .cal-appt.cancelled { background:#e8ddd6; color:#999; text-decoration:line-through; }
.cal-cell .cal-appt .cal-time { font-weight:500; }
.cal-weekday { background:#fff; padding:8px 4px; text-align:center; font-size:12px; font-weight:600; color:#999; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #f0ebe7; }

/* Modal */
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:9998; padding:24px; backdrop-filter:blur(2px); }
.modal-overlay.show { display:flex; }
.modal { background:#fff; border-radius:20px; padding:32px; width:100%; max-width:460px; max-height:90vh; overflow-y:auto; box-shadow:0 16px 48px rgba(0,0,0,.15); position:relative; animation:modalIn .25s ease; }
@keyframes modalIn { from{opacity:0;transform:scale(.95) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
.modal-close { position:absolute; top:16px; right:16px; background:transparent; border:none; font-size:22px; cursor:pointer; color:#999; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:.2s; }
.modal-close:hover { background:#f0ebe7; color:#333; }
.modal h2 { font-size:20px; color:#333; margin-bottom:20px; }
.modal h3 { font-size:15px; color:#555; margin-bottom:8px; }
.modal .detail-row { display:flex; gap:8px; margin-bottom:10px; font-size:14px; }
.modal .detail-row .icon { width:20px; color:#b76e79; flex-shrink:0; }
.modal .detail-row .val { color:#333; }
.modal .status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; }
.status-badge.confirmed { background:#f5e1e4; color:#b76e79; }
.status-badge.cancelled { background:#f0ebe7; color:#999; }
.modal-actions { display:flex; gap:8px; margin-top:20px; flex-wrap:wrap; }

/* Reschedule */
.reschedule-section { margin-top:16px; padding-top:16px; border-top:1px solid #f0ebe7; }
.reschedule-section h3 { margin-bottom:12px; }
.slot-options { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.slot-btn { padding:8px 16px; border:1.5px solid #e8ddd6; border-radius:10px; background:#fff; cursor:pointer; font-size:13px; font-family:inherit; transition:.2s; color:#555; }
.slot-btn:hover { border-color:#b76e79; color:#b76e79; }
.slot-btn.selected { background:#b76e79; color:#fff; border-color:#b76e79; }

/* Turnos list */
.search-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.search-bar input { flex:1; min-width:180px; }
.search-bar select { width:auto; min-width:140px; }
.turno-row td { vertical-align:middle; }
.turno-row .cliente { font-weight:500; }
.turno-row .servicio-info { font-size:13px; color:#666; }
.empty-state { text-align:center; padding:48px 24px; color:#bbb; }
.empty-state .icon { font-size:48px; margin-bottom:8px; }

/* Responsive */
@media(max-width:700px) {
  .stats-grid { grid-template-columns:1fr 1fr; }
  .form-row,.form-row.three { grid-template-columns:1fr; }
  .tabs { padding:0 8px; }
  .tab-btn { padding:10px 14px; font-size:13px; }
  .container { padding:16px; }
  .modal { padding:24px; }
  .cal-grid { gap:2px; }
  .cal-cell { min-height:70px; font-size:11px; }
  .cal-cell .cal-appt { font-size:10px; padding:1px 2px; }
}
</style>
</head>
<body>
<header>
    <h1 id="header-title">TeToca · Panel</h1>
    <a href="logout.php">Cerrar sesión</a>
</header>

<div class="tabs" id="tabNav">
    <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
    <button class="tab-btn" data-tab="servicios">Servicios</button>
    <button class="tab-btn" data-tab="horarios">Horarios</button>
    <button class="tab-btn" data-tab="calendario">Calendario</button>
    <button class="tab-btn" data-tab="turnos">Turnos</button>
    <button class="tab-btn" data-tab="whatsapp">WhatsApp</button>
</div>

<div class="container">

<!-- TAB: Dashboard -->
<div id="tab-dashboard" class="tab-content active">
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card"><div class="num" id="statHoy">-</div><div class="label">Turnos hoy</div></div>
        <div class="stat-card"><div class="num" id="statSemana">-</div><div class="label">Turnos esta semana</div></div>
        <div class="stat-card"><div class="num" id="statMes">-</div><div class="label">Turnos este mes</div></div>
        <div class="stat-card"><div class="num" id="statIngresos">-</div><div class="label">Ingresos del mes</div></div>
    </div>
    <div class="card">
        <h2>Próximos turnos (7 días)</h2>
        <div id="proximos-container"><div class="empty-state">Cargando...</div></div>
    </div>
</div>

<!-- TAB: Servicios -->
<div id="tab-servicios" class="tab-content">
    <div class="card">
        <h2>Agregar servicio</h2>
        <form id="form-servicio">
            <div class="form-row three">
                <div><label>Nombre</label><input name="name" required></div>
                <div><label>Precio (ARS)</label><input name="price" type="number" min="0" step="1" required></div>
                <div><label>Duración (min)</label><input name="duration" type="number" min="5" step="5" required></div>
            </div>
            <div class="form-row"><div><label>Descripción</label><textarea name="description"></textarea></div></div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost hidden" id="btn-cancelar" onclick="cancelarEdicion()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar servicio</button>
            </div>
        </form>
    </div>
    <div class="card">
        <h2>Servicios actuales</h2>
        <div id="loading">Cargando...</div>
        <table id="tabla-servicios" class="hidden">
            <thead><tr><th>Nombre</th><th>Descripción</th><th>Duración</th><th>Precio</th><th></th></tr></thead>
            <tbody id="tbody-servicios"></tbody>
        </table>
        <div id="empty" class="hidden" style="text-align:center;padding:32px;color:#999;">No hay servicios todavía</div>
    </div>
</div>

<!-- TAB: Horarios -->
<div id="tab-horarios" class="tab-content">
    <div class="card">
        <h2>Mis horarios</h2>
        <p style="color:#999;font-size:13px;margin-bottom:16px;">Configurá tu disponibilidad semanal. Los días desactivados no mostrarán horarios.</p>
        <div id="wp-loading" style="text-align:center;padding:24px;color:#999;">Cargando horarios...</div>
        <form id="form-horarios" class="hidden">
            <table><thead><tr><th>Día</th><th>Activo</th><th>Desde</th><th>Hasta</th><th>Descanso</th></tr></thead>
                <tbody id="wp-tbody"></tbody>
            </table>
            <div class="form-actions" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary" id="btn-guardar-horarios">Guardar horarios</button>
            </div>
        </form>
    </div>
</div>

<!-- TAB: Calendario -->
<div id="tab-calendario" class="tab-content">
    <div class="card">
        <div class="cal-header">
            <h3 id="calTitle">Mes</h3>
            <div class="cal-nav">
                <button onclick="navegarCal(-1)">←</button>
                <button onclick="irHoy()">Hoy</button>
                <button onclick="navegarCal(1)">→</button>
            </div>
        </div>
        <div id="calContainer" style="text-align:center;padding:32px;color:#999;">Cargando calendario...</div>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
        <span style="font-size:13px;color:#999;">Referencia:</span>
        <span style="display:flex;align-items:center;gap:4px;font-size:12px;"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#b76e79;"></span> Confirmado</span>
        <span style="display:flex;align-items:center;gap:4px;font-size:12px;"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#e8ddd6;"></span> Cancelado</span>
    </div>
</div>

<!-- TAB: Turnos -->
<div id="tab-turnos" class="tab-content">
    <div class="card">
        <h2>Gestión de turnos</h2>
        <div class="search-bar">
            <input type="text" id="searchTurno" placeholder="Buscar por cliente..." oninput="filtrarTurnos()">
            <select id="filtroEstado" onchange="filtrarTurnos()">
                <option value="">Todos los estados</option>
                <option value="confirmed">Confirmados</option>
                <option value="cancelled">Cancelados</option>
            </select>
        </div>
        <div id="turnosContainer"><div class="empty-state">Cargando turnos...</div></div>
    </div>
</div>

<!-- TAB: WhatsApp -->
<div id="tab-whatsapp" class="tab-content">
    <div class="card" style="text-align:center;max-width:480px;margin:0 auto;">
        <h2 style="margin-bottom:4px;">📱 Conexión WhatsApp</h2>
        <p style="color:#999;font-size:13px;margin-bottom:24px;">Escaneá el código QR con WhatsApp Business para recibir y responder mensajes automáticamente.</p>
        <div id="whatsapp-status">
            <div class="wa-loading">⏳ Conectando...</div>
        </div>
        <div id="whatsapp-qr-container" style="display:none;">
            <img id="whatsapp-qr-img" src="" alt="QR WhatsApp" style="max-width:300px;width:100%;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);">
            <p style="color:#888;font-size:13px;margin-top:12px;">📱 Abrí WhatsApp en tu celu → Dispositivos vinculados → Vincular</p>
        </div>
        <div id="whatsapp-connected" style="display:none;">
            <div style="font-size:64px;margin-bottom:12px;">✅</div>
            <h3 style="color:#4caf50;">WhatsApp conectado</h3>
            <p style="color:#888;font-size:14px;">Los mensajes de confirmación y recordatorios se enviarán automáticamente.</p>
        </div>
    </div>
</div>

</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <button class="modal-close" onclick="cerrarModal()">✕</button>
        <div id="modalBody"></div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
const API = '../api/admin-servicios.php';
const WP_API = '../api/horarios-admin.php';
const TURNOS_API = '../api/turnos-admin.php';

const DAYS = [
    { key:'monday', label:'Lunes' }, { key:'tuesday', label:'Martes' },
    { key:'wednesday', label:'Miércoles' }, { key:'thursday', label:'Jueves' },
    { key:'friday', label:'Viernes' }, { key:'saturday', label:'Sábado' },
    { key:'sunday', label:'Domingo' },
];
const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const DAY_LABELS = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

let allAppointments = [];
let allServices = [];
let calYear, calMonth;
let selectedAppt = null;
let editingService = null;

// ===== TABS =====
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        btn.classList.add('active');
        const tab = btn.dataset.tab;
        document.getElementById('tab-' + tab).classList.add('active');
        document.getElementById('header-title').textContent = 'TeToca · ' + btn.textContent;
        if (tab === 'dashboard') cargarDashboard();
        if (tab === 'calendario') renderCalendario();
        if (tab === 'turnos') renderTurnos();
    });
});

// ===== TOAST =====
function mostrarToast(msg) { const t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 2500); }

// ===== MODAL =====
function abrirModal(html) { document.getElementById('modalBody').innerHTML = html; document.getElementById('modalOverlay').classList.add('show'); }
function cerrarModal() { document.getElementById('modalOverlay').classList.remove('show'); }
document.getElementById('modalOverlay').addEventListener('click', e => { if (e.target === e.currentTarget) cerrarModal(); });

// ===== DASHBOARD =====
async function cargarDashboard() {
    const res = await fetch(`${TURNOS_API}?month=${new Date().getFullYear()}-${String(new Date().getMonth()+1).padStart(2,'0')}`);
    const appts = await res.json();
    const now = new Date();
    const today = now.toISOString().slice(0,10);
    const todayAppts = appts.filter(a => a.start.slice(0,10) === today);
    document.getElementById('statHoy').textContent = todayAppts.length;

    const weekStart = new Date(now); weekStart.setDate(now.getDate() - now.getDay() + 1);
    const weekEnd = new Date(weekStart); weekEnd.setDate(weekStart.getDate() + 6);
    const weekAppts = appts.filter(a => { const d = a.start.slice(0,10); return d >= weekStart.toISOString().slice(0,10) && d <= weekEnd.toISOString().slice(0,10); });
    document.getElementById('statSemana').textContent = weekAppts.length;

    const monthAppts = appts.filter(a => a.start.slice(0,7) === today.slice(0,7));
    document.getElementById('statMes').textContent = monthAppts.length;
    const ingresos = monthAppts.reduce((sum, a) => sum + (a.service ? a.service.price : 0), 0);
    document.getElementById('statIngresos').textContent = '$' + ingresos.toLocaleString('es-AR');

    const proximos = appts.filter(a => { const d = a.start.slice(0,10); return d >= today && d <= new Date(now.getTime()+7*86400000).toISOString().slice(0,10) && a.status !== 'cancelled'; });
    proximos.sort((a,b) => a.start.localeCompare(b.start));
    const container = document.getElementById('proximos-container');
    if (!proximos.length) { container.innerHTML = '<div class="empty-state">No hay turnos próximos</div>'; return; }
    let html = '<table><thead><tr><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Servicio</th><th>Precio</th></tr></thead><tbody>';
    proximos.slice(0,10).forEach(a => {
        const d = a.start.slice(0,10).split('-').reverse().join('/');
        const h = a.start.slice(11,16);
        const c = a.customer ? a.customer.firstName + ' ' + a.customer.lastName : '—';
        const s = a.service ? a.service.name : '—';
        const p = a.service ? '$' + Number(a.service.price).toLocaleString('es-AR') : '—';
        html += `<tr><td>${d}</td><td><strong>${h}</strong></td><td>${c}</td><td style="color:#888;font-size:13px;">${s}</td><td class="precio">${p}</td></tr>`;
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

// ===== SERVICIOS CRUD =====
async function cargarServicios() {
    const res = await fetch(API); const data = await res.json();
    document.getElementById('loading').classList.add('hidden');
    const tbody = document.getElementById('tbody-servicios'); tbody.innerHTML = '';
    if (!Array.isArray(data) || data.length === 0) {
        document.getElementById('empty').classList.remove('hidden'); document.getElementById('tabla-servicios').classList.add('hidden'); return;
    }
    document.getElementById('empty').classList.add('hidden'); document.getElementById('tabla-servicios').classList.remove('hidden');
    allServices = data;
    data.forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><strong>${s.name}</strong></td><td style="color:#888;font-size:13px;">${s.description||'-'}</td><td class="duracion">${s.duration} min</td><td class="precio">$${Number(s.price).toLocaleString('es-AR')}</td><td class="actions"><button class="btn btn-ghost btn-sm" onclick="editarServicio(${s.id})">Editar</button> <button class="btn btn-danger btn-sm" onclick="eliminarServicio(${s.id})">Eliminar</button></td>`;
        tbody.appendChild(tr);
    });
}

document.getElementById('form-servicio').addEventListener('submit', async e => {
    e.preventDefault();
    const fd = new FormData(e.target); const data = Object.fromEntries(fd);
    data.price = parseFloat(data.price); data.duration = parseInt(data.duration);
    let url = API; let method = 'POST';
    if (editingService) { url += '?id=' + editingService; method = 'PUT'; }
    const res = await fetch(url, { method, headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) });
    if (!res.ok) { const err = await res.json(); mostrarToast('Error: ' + (err.error||'desconocido')); return; }
    mostrarToast(editingService ? 'Servicio actualizado' : 'Servicio creado');
    cancelarEdicion(); cargarServicios();
});

async function editarServicio(id) {
    const res = await fetch(API); const servicios = await res.json(); const s = servicios.find(x => x.id === id);
    if (!s) return;
    editingService = id;
    const form = document.getElementById('form-servicio');
    form.querySelector('[name="name"]').value = s.name; form.querySelector('[name="price"]').value = s.price;
    form.querySelector('[name="duration"]').value = s.duration; form.querySelector('[name="description"]').value = s.description||'';
    form.querySelector('[type="submit"]').textContent = 'Actualizar servicio';
    document.getElementById('btn-cancelar').classList.remove('hidden');
    form.scrollIntoView({behavior:'smooth'});
}

function cancelarEdicion() {
    editingService = null; document.getElementById('form-servicio').reset();
    document.getElementById('form-servicio').querySelector('[type="submit"]').textContent = 'Guardar servicio';
    document.getElementById('btn-cancelar').classList.add('hidden');
}

async function eliminarServicio(id) {
    if (!confirm('¿Eliminar este servicio?')) return;
    const res = await fetch(API+'?id='+id, {method:'DELETE'});
    if (!res.ok) { const err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Servicio eliminado'); cargarServicios();
}

// ===== HORARIOS =====
async function cargarHorarios() {
    const res = await fetch(WP_API); const data = await res.json();
    document.getElementById('wp-loading').classList.add('hidden'); document.getElementById('form-horarios').classList.remove('hidden');
    const wp = data.workingPlan || {}; const tbody = document.getElementById('wp-tbody'); tbody.innerHTML = '';
    DAYS.forEach(d => {
        const day = wp[d.key]; const active = day && day.start && day.end;
        const tr = document.createElement('tr');
        tr.innerHTML = `<td><strong>${d.label}</strong></td>
            <td><input type="checkbox" class="day-active" data-day="${d.key}" ${active?'checked':''}></td>
            <td><input type="time" class="day-start" data-day="${d.key}" value="${active?day.start:'09:00'}" ${active?'':'disabled'}></td>
            <td><input type="time" class="day-end" data-day="${d.key}" value="${active?day.end:'18:00'}" ${active?'':'disabled'}></td>
            <td><div class="breaks-container" data-day="${d.key}">
                ${active&&day.breaks?day.breaks.map((b,i)=>`<div class="break-row"><input type="time" class="break-start" value="${b.start}" style="width:80px"><input type="time" class="break-end" value="${b.end}" style="width:80px"><button type="button" class="btn btn-ghost btn-xs" onclick="this.closest('.break-row').remove()">✕</button></div>`).join(''):''}
                <button type="button" class="btn btn-ghost btn-xs" style="margin-top:4px;" onclick="agregarDescanso('${d.key}')">+ Descanso</button>
            </div></td>`;
        tbody.appendChild(tr);
    });
    document.querySelectorAll('.day-active').forEach(cb => {
        cb.addEventListener('change', function() {
            const day = this.dataset.day; const row = this.closest('tr');
            row.querySelector('.day-start').disabled = !this.checked;
            row.querySelector('.day-end').disabled = !this.checked;
            row.querySelectorAll('.breaks-container input').forEach(i => i.disabled = !this.checked);
        });
    });
}

function agregarDescanso(day) {
    const container = document.querySelector(`.breaks-container[data-day="${day}"]`);
    const div = document.createElement('div'); div.className = 'break-row';
    div.innerHTML = `<input type="time" class="break-start" value="13:00" style="width:80px"><input type="time" class="break-end" value="14:00" style="width:80px"><button type="button" class="btn btn-ghost btn-xs" onclick="this.closest('.break-row').remove()">✕</button>`;
    container.insertBefore(div, container.lastElementChild);
}

document.getElementById('form-horarios').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btn-guardar-horarios'); btn.textContent = 'Guardando...'; btn.disabled = true;
    const workingPlan = {};
    DAYS.forEach(d => {
        const active = document.querySelector(`.day-active[data-day="${d.key}"]`).checked;
        if (!active) { workingPlan[d.key] = null; return; }
        const start = document.querySelector(`.day-start[data-day="${d.key}"]`).value;
        const end = document.querySelector(`.day-end[data-day="${d.key}"]`).value;
        const breaks = [];
        document.querySelector(`.breaks-container[data-day="${d.key}"]`).querySelectorAll('.break-row').forEach(row => {
            const bs = row.querySelector('.break-start').value; const be = row.querySelector('.break-end').value;
            if (bs && be) breaks.push({start:bs, end:be});
        });
        workingPlan[d.key] = {start, end, breaks};
    });
    try {
        const res = await fetch(WP_API, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({workingPlan})});
        const data = await res.json();
        if (!data.success) { mostrarToast('Error: '+(data.error||'')); return; }
        mostrarToast('Horarios guardados');
    } catch(e) { mostrarToast('Error de conexión'); }
    finally { btn.textContent = 'Guardar horarios'; btn.disabled = false; }
});

// ===== CALENDARIO =====
function irHoy() { const n = new Date(); calYear = n.getFullYear(); calMonth = n.getMonth(); renderCalendario(); }
function navegarCal(dir) { calMonth += dir; if (calMonth > 11) { calMonth = 0; calYear++; } if (calMonth < 0) { calMonth = 11; calYear--; } renderCalendario(); }

async function renderCalendario() {
    if (calYear === undefined) { const n = new Date(); calYear = n.getFullYear(); calMonth = n.getMonth(); }
    const monthStr = `${calYear}-${String(calMonth+1).padStart(2,'0')}`;
    document.getElementById('calTitle').textContent = MONTHS[calMonth] + ' ' + calYear;
    document.getElementById('calContainer').innerHTML = 'Cargando...';
    const res = await fetch(`${TURNOS_API}?month=${monthStr}`);
    const appts = await res.json();
    allAppointments = appts;

    const firstDay = new Date(calYear, calMonth, 1);
    const lastDay = new Date(calYear, calMonth + 1, 0);
    const startDow = firstDay.getDay(); // 0=Sun
    const startOffset = startDow === 0 ? 6 : startDow - 1;

    // Build day map
    const dayMap = {};
    appts.forEach(a => {
        const d = a.start.slice(0,10);
        if (!dayMap[d]) dayMap[d] = [];
        dayMap[d].push(a);
    });

    const todayStr = new Date().toISOString().slice(0,10);
    let html = '<div class="cal-grid">';
    DAY_LABELS.forEach(l => { html += `<div class="cal-weekday">${l}</div>`; });

    // Previous month padding
    const prevLastDay = new Date(calYear, calMonth, 0).getDate();
    for (let i = startOffset - 1; i >= 0; i--) {
        const d = prevLastDay - i;
        html += `<div class="cal-cell other-month"><div class="day-num">${d}</div></div>`;
    }

    // Current month
    for (let d = 1; d <= lastDay.getDate(); d++) {
        const dateStr = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
        const isToday = dateStr === todayStr;
        let cellClass = isToday ? 'cal-cell today' : 'cal-cell';
        html += `<div class="${cellClass}"><div class="day-num">${d}</div>`;
        if (dayMap[dateStr]) {
            dayMap[dateStr].forEach(a => {
                const time = a.start.slice(11,16);
                const name = a.customer ? a.customer.firstName : '?';
                const status = a.status || 'confirmed';
                html += `<div class="cal-appt ${status}" onclick="event.stopPropagation();abrirModalTurno(${a.id})"><span class="cal-time">${time}</span> ${name}</div>`;
            });
        }
        html += '</div>';
    }

    // Next month padding
    const totalCells = startOffset + lastDay.getDate();
    const remaining = (7 - (totalCells % 7)) % 7;
    for (let d = 1; d <= remaining; d++) {
        html += `<div class="cal-cell other-month"><div class="day-num">${d}</div></div>`;
    }

    html += '</div>';
    document.getElementById('calContainer').innerHTML = html;
}

// ===== MODAL TURNO =====
async function abrirModalTurno(id) {
    const appt = allAppointments.find(a => a.id === id);
    if (!appt) return;
    selectedAppt = appt;
    const c = appt.customer || {};
    const s = appt.service || {};
    const fecha = new Date(appt.start);
    const fechaStr = fecha.toLocaleDateString('es-AR', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
    const horaStr = appt.start.slice(11,16) + ' - ' + appt.end.slice(11,16);
    const status = appt.status || 'confirmed';
    const statusLabel = status === 'confirmed' ? 'Confirmado' : 'Cancelado';
    const precio = s.price ? '$' + Number(s.price).toLocaleString('es-AR') : '—';

    let html = `
        <h2>Detalle del turno</h2>
        <div class="detail-row"><span class="icon">👤</span><span class="val"><strong>${c.firstName||'?'} ${c.lastName||''}</strong></span></div>
        <div class="detail-row"><span class="icon">📞</span><span class="val">${c.phone||'—'}</span></div>
        <div class="detail-row"><span class="icon">✉️</span><span class="val">${c.email||'—'}</span></div>
        <div style="height:1px;background:#f0ebe7;margin:12px 0;"></div>
        <div class="detail-row"><span class="icon">💅</span><span class="val">${s.name||'—'}</span></div>
        <div class="detail-row"><span class="icon">⏱️</span><span class="val">${horaStr} (${s.duration||'?'} min)</span></div>
        <div class="detail-row"><span class="icon">💰</span><span class="val">${precio}</span></div>
        <div class="detail-row"><span class="icon">📅</span><span class="val" style="text-transform:capitalize;">${fechaStr}</span></div>
        <div class="detail-row"><span class="icon">🏷️</span><span class="val"><span class="status-badge ${status}">${statusLabel}</span></span></div>`;

    if (status !== 'cancelled') {
        html += `<div class="modal-actions">
            <button class="btn btn-primary" onclick="mostrarReagendar()">Reagendar</button>
            <button class="btn btn-danger" onclick="cancelarTurno(${id})">Cancelar turno</button>
        </div>`;
    } else {
        html += `<div class="modal-actions"><button class="btn btn-ghost" onclick="cerrarModal()">Cerrar</button></div>`;
    }

    // Pre-reschedule section (hidden initially)
    html += `<div id="rescheduleSection" class="reschedule-section hidden">
        <h3>Reagendar turno</h3>
        <label>Nueva fecha</label>
        <input type="date" id="reschedDate" min="${new Date().toISOString().slice(0,10)}" onchange="cargarSlotsReagendar(${id})">
        <div id="reschedSlots" style="margin-top:8px;"></div>
        <div class="form-actions" style="margin-top:12px;">
            <button class="btn btn-ghost" onclick="cerrarReagendar()">Cancelar</button>
            <button class="btn btn-primary" id="btnConfirmarResched" onclick="confirmarReagendar(${id})" disabled>Confirmar reagendamiento</button>
        </div>
    </div>`;

    abrirModal(html);
}

function cerrarReagendar() {
    document.getElementById('rescheduleSection').classList.add('hidden');
    document.getElementById('reschedSlots').innerHTML = '';
}

async function cargarSlotsReagendar(id) {
    const date = document.getElementById('reschedDate').value;
    if (!date) return;
    const appt = selectedAppt;
    if (!appt || !appt.service) { document.getElementById('reschedSlots').innerHTML = '<div style="color:#999;">Servicio no disponible</div>'; return; }
    const serviceId = appt.service.id;
    const res = await fetch(`../api/horarios.php?serviceId=${serviceId}&date=${date}`);
    const data = await res.json();
    const container = document.getElementById('reschedSlots');
    const btnConfirmar = document.getElementById('btnConfirmarResched');
    btnConfirmar.disabled = true;

    if (data.dayOff || !data.slots || data.slots.length === 0) {
        container.innerHTML = '<div style="color:#999;">No hay horarios disponibles para esta fecha</div>';
        return;
    }

    let html = '<label style="margin-top:8px;">Horario disponible</label><div class="slot-options">';
    data.slots.forEach((s, i) => {
        html += `<button type="button" class="slot-btn" data-slot="${s}" onclick="seleccionarSlot(this,'${s}')">${s}</button>`;
    });
    html += '</div>';
    container.innerHTML = html;
}

let selectedSlot = null;

function seleccionarSlot(el, slot) {
    document.querySelectorAll('.slot-btn').forEach(b => b.classList.remove('selected'));
    el.classList.add('selected');
    selectedSlot = slot;
    document.getElementById('btnConfirmarResched').disabled = false;
}

async function confirmarReagendar(id) {
    const date = document.getElementById('reschedDate').value;
    if (!date || !selectedSlot) return;
    const start = `${date} ${selectedSlot}:00`;
    const appt = selectedAppt;
    if (!appt || !appt.service) return;
    const dur = appt.service.duration;
    const startDt = new Date(`${date}T${selectedSlot}:00`);
    const endDt = new Date(startDt.getTime() + dur * 60000);
    const end = endDt.toISOString().slice(0,19).replace('T',' ');

    const res = await fetch(`${TURNOS_API}?id=${id}`, {
        method: 'PUT',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({start, end}),
    });
    if (!res.ok) { const err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Turno reagendado con éxito');
    cerrarModal();
    renderCalendario();
    if (document.querySelector('.tab-btn.active')?.dataset.tab === 'turnos') renderTurnos();
    if (document.querySelector('.tab-btn.active')?.dataset.tab === 'dashboard') cargarDashboard();
}

function mostrarReagendar() {
    document.getElementById('rescheduleSection').classList.remove('hidden');
    // Set min date to tomorrow if today
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    document.getElementById('reschedDate').min = tomorrow.toISOString().slice(0,10);
}

async function cancelarTurno(id) {
    if (!confirm('¿Estás segura de cancelar este turno?')) return;
    const res = await fetch(`${TURNOS_API}?id=${id}`, {method:'DELETE'});
    if (!res.ok) { const err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Turno cancelado');
    cerrarModal();
    renderCalendario();
    if (document.querySelector('.tab-btn.active')?.dataset.tab === 'turnos') renderTurnos();
    if (document.querySelector('.tab-btn.active')?.dataset.tab === 'dashboard') cargarDashboard();
}

// ===== TURNOS LISTA =====
let filteredTurnos = [];

async function renderTurnos() {
    const container = document.getElementById('turnosContainer');
    container.innerHTML = '<div class="empty-state">Cargando turnos...</div>';
    const res = await fetch(TURNOS_API);
    const appts = await res.json();
    allAppointments = appts;
    filteredTurnos = appts;
    filtrarTurnos();
}

function filtrarTurnos() {
    const q = document.getElementById('searchTurno').value.toLowerCase();
    const estado = document.getElementById('filtroEstado').value;
    let list = allAppointments;
    if (q) list = list.filter(a => {
        const c = a.customer || {};
        return (c.firstName+' '+c.lastName).toLowerCase().includes(q) || (c.phone||'').includes(q);
    });
    if (estado) list = list.filter(a => a.status === estado);
    list.sort((a,b) => a.start.localeCompare(b.start));
    filteredTurnos = list;
    const container = document.getElementById('turnosContainer');

    if (!list.length) {
        container.innerHTML = '<div class="empty-state"><div class="icon">📋</div>No se encontraron turnos</div>';
        return;
    }

    let html = `<table><thead><tr><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Servicio</th><th>Precio</th><th>Estado</th><th></th></tr></thead><tbody>`;
    list.forEach(a => {
        const d = a.start.slice(0,10).split('-').reverse().join('/');
        const h = a.start.slice(11,16) + ' - ' + a.end.slice(11,16);
        const c = a.customer ? a.customer.firstName + ' ' + (a.customer.lastName||'') : '—';
        const tel = a.customer ? a.customer.phone||'' : '';
        const s = a.service ? a.service.name : '—';
        const p = a.service ? '$' + Number(a.service.price).toLocaleString('es-AR') : '—';
        const est = a.status || 'confirmed';
        const estLabel = est === 'confirmed' ? 'Confirmado' : 'Cancelado';
        html += `<tr class="turno-row">
            <td>${d}</td><td style="font-weight:500;">${h}</td>
            <td><span class="cliente">${c}</span>${tel ? '<br><span style="font-size:12px;color:#999;">'+tel+'</span>' : ''}</td>
            <td class="servicio-info">${s}</td>
            <td class="precio">${p}</td>
            <td><span class="status-badge ${est}">${estLabel}</span></td>
            <td class="actions">
                <button class="btn btn-ghost btn-sm" onclick="abrirModalTurno(${a.id})">Ver</button>
                ${est !== 'cancelled' ? `<button class="btn btn-danger btn-sm" onclick="cancelarTurnoLista(${a.id})">Cancelar</button>` : ''}
            </td>
        </tr>`;
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

async function cancelarTurnoLista(id) {
    if (!confirm('¿Cancelar este turno?')) return;
    const res = await fetch(`${TURNOS_API}?id=${id}`, {method:'DELETE'});
    if (!res.ok) { const err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Turno cancelado');
    renderTurnos();
    renderCalendario();
}

// ===== WHATSAPP QR =====
let waPollTimer = null;

async function cargarWhatsApp() {
    const statusDiv = document.getElementById('whatsapp-status');
    const qrDiv = document.getElementById('whatsapp-qr-container');
    const connectedDiv = document.getElementById('whatsapp-connected');
    try {
        const res = await fetch('../api/whatsapp-qr.php');
        const data = await res.json();
        if (data.status === 'connected') {
            statusDiv.innerHTML = '';
            qrDiv.style.display = 'none';
            connectedDiv.style.display = 'block';
            if (waPollTimer) { clearInterval(waPollTimer); waPollTimer = null; }
            return;
        }
        if (data.status === 'awaiting_qr' && data.qr) {
            statusDiv.innerHTML = '';
            document.getElementById('whatsapp-qr-img').src = data.qr;
            qrDiv.style.display = 'block';
            connectedDiv.style.display = 'none';
            return;
        }
        statusDiv.innerHTML = '<div class="wa-loading">⏳ Esperando código QR...</div><div class="wa-retry">La página se actualiza automáticamente</div>';
        qrDiv.style.display = 'none';
        connectedDiv.style.display = 'none';
    } catch(e) {
        statusDiv.innerHTML = '<div class="wa-error">❌ No se pudo conectar con el servicio de WhatsApp</div><div class="wa-retry">Asegurate que el contenedor de Baileys esté funcionando</div>';
        qrDiv.style.display = 'none';
        connectedDiv.style.display = 'none';
    }
}

// Poll QR every 5 seconds when WhatsApp tab is visible
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        if (this.dataset.tab === 'whatsapp') {
            cargarWhatsApp();
            if (!waPollTimer) waPollTimer = setInterval(cargarWhatsApp, 5000);
        } else if (waPollTimer) {
            clearInterval(waPollTimer);
            waPollTimer = null;
        }
    });
});

// ===== INIT =====
cargarServicios();
cargarHorarios();
cargarDashboard();
</script>
</body>
</html>
