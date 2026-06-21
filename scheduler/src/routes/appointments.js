const { getDb } = require('../db');
const webhooks = require('../webhooks');
const { sendWhatsApp } = require('./whatsapp');

function notifyCustomer(full, type) {
  const c = full.customer || {};
  const s = full.service || {};
  const p = full.provider || {};
  const phone = (c.phone || '').replace(/\+/g, '').replace(/ /g, '');
  if (!phone || phone.length < 8) return;
  const date = (full.start || '').split(' ')[0];
  const time = ((full.start || '').split(' ')[1] || '').substring(0, 5);
  const prof = p.profesional || (p.firstName ? p.firstName + ' ' + p.lastName : '');
  const addr = p.address || 'Mitre 456, Chamical';
  let msg;
  if (type === 'cancel') {
    msg = `Hola ${c.firstName || ''}, tu turno del ${date} a las ${time} (${s.name || ''}) fue cancelado.\n\n` +
      `👩‍🎨 ${prof}\n📍 ${addr}\n\nReserva un nuevo turno desde nuestra web.`;
  } else if (type === 'reschedule') {
    msg = `Hola ${c.firstName || ''}, tu turno fue reagendado.\n\n📅 ${date} a las ${time}\n💅 ${s.name || ''}\n👩‍🎨 ${prof}\n📍 ${addr}`;
  } else {
    msg = `Hola ${c.firstName || ''} ${c.lastName || ''}!\n\nTu turno esta confirmado:\n📅 ${date} a las ${time}\n💅 ${s.name || ''}\n👩‍🎨 ${prof}\n📍 ${addr}`;
  }
  sendWhatsApp(phone, msg);
}

function register(router) {
  router.get('/appointments', (req, res) => {
    const db = getDb();
    let sql = `SELECT a.*, 
      c.first_name AS c_first_name, c.last_name AS c_last_name, c.email AS c_email, c.phone AS c_phone,
      s.name AS s_name, s.duration AS s_duration, s.price AS s_price,
      p.first_name AS p_first_name, p.last_name AS p_last_name, p.address AS p_address, p.profesional AS p_profesional
      FROM appointments a
      LEFT JOIN customers c ON a.customer_id = c.id
      LEFT JOIN services s ON a.service_id = s.id
      LEFT JOIN provider_settings p ON a.provider_id = p.provider_id`;

    const { sort, length, with: withParam, start, end, hash, customer_id, status } = req.query;
    const conditions = [];
    const params = [];

    if (hash) { conditions.push('a.hash = ?'); params.push(hash); }
    if (customer_id) { conditions.push('a.customer_id = ?'); params.push(+customer_id); }
    if (status) { conditions.push('a.status = ?'); params.push(status); }
    if (start) { conditions.push('a.start >= ?'); params.push(start); }
    if (end) { conditions.push('a.end <= ?'); params.push(end); }
    if (conditions.length) sql += ' WHERE ' + conditions.join(' AND ');

    if (sort) {
      const dir = sort.startsWith('-') ? 'DESC' : 'ASC';
      const col = sort.replace(/^-/, '');
      const colMap = { id: 'a.id', start: 'a.start', end: 'a.end' };
      sql += ` ORDER BY ${colMap[col] || 'a.id'} ${dir}`;
    } else {
      sql += ' ORDER BY a.start';
    }

    if (length) sql += ` LIMIT ${+length}`;

    const rows = db.prepare(sql).all(...params);
    const wants = withParam ? withParam.split(',').map(s => s.trim()) : [];
    res.json(rows.map(r => mapAppointment(r, wants)));
  });

  router.get('/appointments/:id/cancel', (req, res) => {
    const db = getDb();
    const row = db.prepare('SELECT * FROM appointments WHERE id = ?').get(+req.params.id);
    if (!row) return res.status(404).json({ success: false, message: 'Turno no encontrado' });
    db.prepare('UPDATE appointments SET status = ? WHERE id = ?').run('cancelled', +req.params.id);
    const full = getFullAppointment(+req.params.id);
    webhooks.fire('appointment-cancelled', full);
    notifyCustomer(full, 'cancel');
    res.json({ id: row.id, status: 'cancelled', phone: '549' + (full.customer?.phone || row.phone || ''), start: row.start, service: full.service?.name || '' });
  });

  router.get('/appointments/:id', (req, res) => {
    const db = getDb();
    const row = db.prepare(`
      SELECT a.*,
        c.first_name AS c_first_name, c.last_name AS c_last_name, c.email AS c_email, c.phone AS c_phone,
        s.name AS s_name, s.duration AS s_duration, s.price AS s_price,
        p.first_name AS p_first_name, p.last_name AS p_last_name, p.address AS p_address, p.profesional AS p_profesional
      FROM appointments a
      LEFT JOIN customers c ON a.customer_id = c.id
      LEFT JOIN services s ON a.service_id = s.id
      LEFT JOIN provider_settings p ON a.provider_id = p.provider_id
      WHERE a.id = ?
    `).get(+req.params.id);
    if (!row) return res.status(404).json({ success: false, message: 'Turno no encontrado' });
    res.json(mapAppointment(row, ['customer', 'service', 'provider']));
  });

  router.post('/appointments', (req, res) => {
    const db = getDb();
    const { start, end, serviceId, customerId, providerId, notes } = req.body || {};
    if (!start || !end || !serviceId || !customerId) {
      return res.status(400).json({ success: false, message: 'start, end, serviceId y customerId requeridos' });
    }
    const hash = Math.random().toString(36).substring(2, 10);
    const result = db.prepare(`
      INSERT INTO appointments (start, end, service_id, customer_id, provider_id, notes, hash)
      VALUES (?, ?, ?, ?, ?, ?, ?)
    `).run(start, end, serviceId, customerId, providerId || 5, notes || '', hash);

    const row = db.prepare(`
      SELECT a.*,
        c.first_name AS c_first_name, c.last_name AS c_last_name, c.email AS c_email, c.phone AS c_phone,
        s.name AS s_name, s.duration AS s_duration, s.price AS s_price,
        p.first_name AS p_first_name, p.last_name AS p_last_name, p.address AS p_address, p.profesional AS p_profesional
      FROM appointments a
      LEFT JOIN customers c ON a.customer_id = c.id
      LEFT JOIN services s ON a.service_id = s.id
      LEFT JOIN provider_settings p ON a.provider_id = p.provider_id
      WHERE a.id = ?
    `).get(result.lastInsertRowid);

    webhooks.fire('appointment-created', mapAppointment(row, ['customer', 'service', 'provider']));

    // Direct WhatsApp notification (no depende de n8n)
    const full = mapAppointment(row, ['customer', 'service', 'provider']);
    const cust = full.customer || {};
    const svc = full.service || {};
    const prov = full.provider || {};
    const phone = (cust.phone || '').replace(/\+/g, '').replace(/ /g, '');
    if (phone && phone.length >= 8) {
      const date = (full.start || '').split(' ')[0] || '';
      const time = ((full.start || '').split(' ')[1] || '').substring(0, 5);
      const msg = `¡Hola ${cust.firstName || ''} ${cust.lastName || ''}!\n\n` +
        `Tu turno está confirmado:\n` +
        `📅 ${date} a las ${time}\n` +
        `💅 Servicio: ${svc.name || ''}\n` +
        `👩‍🎨 Profesional: ${prov.profesional || prov.firstName || ''}\n` +
        `📍 ${prov.address || 'Mitre 456, Chamical'}\n\n` +
        `Para cancelar, respondé CANCELAR a este mensaje.`;
      // Direct OpenWA call (avoid proxy loopback + auth middleware)
      const waOpenwa = process.env.OPENWA_HOST || 'tetoca_openwa';
      const waPort = process.env.OPENWA_PORT || 2785;
      const waSession = process.env.OPENWA_SESSION_ID;
      const waApiKey = process.env.OPENWA_API_KEY;
      if (waSession && waApiKey) {
        const { exec } = require('child_process');
        const fs = require('fs');
        const os = require('os');
        const chatId = phone.includes('@c.us') ? phone : phone + '@c.us';
        const body = JSON.stringify({ chatId, text: msg });
        const tmpFile = os.tmpdir() + '/wa-' + Date.now() + '.json';
        fs.writeFileSync(tmpFile, body);
        exec(`curl -s -X POST http://${waOpenwa}:${waPort}/api/sessions/${waSession}/messages/send-text -H 'Content-Type: application/json' -H 'X-API-Key: ${waApiKey}' --data-binary @${tmpFile}`, { timeout: 15000 }, (err, stdout) => {
          fs.unlinkSync(tmpFile);
          if (err) console.error('[appt] WA error:', err.message);
        });
      }
    }

    res.status(201).json({
      id: row.id, start: row.start, end: row.end,
      serviceId: row.service_id, providerId: row.provider_id,
      customerId: row.customer_id, status: row.status,
      notes: row.notes, hash: row.hash,
    });
  });

  router.put('/appointments/:id', (req, res) => {
    const db = getDb();
    const existing = db.prepare('SELECT * FROM appointments WHERE id = ?').get(+req.params.id);
    if (!existing) return res.status(404).json({ success: false, message: 'Turno no encontrado' });
    const d = req.body || {};
    db.prepare(`
      UPDATE appointments SET start=?, end=?, service_id=?, customer_id=?, provider_id=?, status=?, notes=?
      WHERE id=?
    `).run(
      d.start ?? existing.start, d.end ?? existing.end,
      d.serviceId ?? existing.service_id, d.customerId ?? existing.customer_id,
      d.providerId ?? existing.provider_id, d.status ?? existing.status,
      d.notes ?? existing.notes, +req.params.id
    );
    const row = db.prepare('SELECT * FROM appointments WHERE id = ?').get(+req.params.id);

    if (d.status === 'cancelled') {
      const full = getFullAppointment(row.id);
      webhooks.fire('appointment-cancelled', full);
      notifyCustomer(full, 'cancel');
    }
    if (d.start && d.start !== existing.start) {
      const full = getFullAppointment(row.id);
      webhooks.fire('appointment-rescheduled', { ...full, oldStart: existing.start, newStart: row.start });
      notifyCustomer(full, 'reschedule');
    }

    res.json({
      id: row.id, start: row.start, end: row.end,
      serviceId: row.service_id, providerId: row.provider_id,
      customerId: row.customer_id, status: row.status,
      notes: row.notes, hash: row.hash,
    });
  });

  router.delete('/appointments/:id', (req, res) => {
    const db = getDb();
    const row = db.prepare('SELECT * FROM appointments WHERE id = ?').get(+req.params.id);
    if (!row) return res.status(404).json({ success: false, message: 'Turno no encontrado' });
    const full = getFullAppointment(row.id);
    db.prepare('DELETE FROM appointments WHERE id = ?').run(+req.params.id);
    webhooks.fire('appointment-cancelled', full);
    notifyCustomer(full, 'cancel');
    res.json({
      id: row.id, start: row.start, end: row.end,
      serviceId: row.service_id, providerId: row.provider_id,
      customerId: row.customer_id, status: row.status,
      notes: row.notes, hash: row.hash,
    });
  });
}

function getFullAppointment(id) {
  const db = getDb();
  const row = db.prepare(`
    SELECT a.*,
      c.first_name AS c_first_name, c.last_name AS c_last_name, c.email AS c_email, c.phone AS c_phone,
      s.name AS s_name, s.duration AS s_duration, s.price AS s_price,
      p.first_name AS p_first_name, p.last_name AS p_last_name, p.address AS p_address, p.profesional AS p_profesional
    FROM appointments a
    LEFT JOIN customers c ON a.customer_id = c.id
    LEFT JOIN services s ON a.service_id = s.id
    LEFT JOIN provider_settings p ON a.provider_id = p.provider_id
    WHERE a.id = ?
  `).get(id);
  return row ? mapAppointment(row, ['customer', 'service', 'provider']) : { id };
}

function mapAppointment(r, wants) {
  const a = {
    id: r.id,
    start: r.start,
    end: r.end,
    serviceId: r.service_id,
    providerId: r.provider_id,
    customerId: r.customer_id,
    status: r.status,
    notes: r.notes,
    hash: r.hash,
  };
  if (wants.includes('customer')) {
    a.customer = { id: r.customer_id, firstName: r.c_first_name, lastName: r.c_last_name, email: r.c_email, phone: r.c_phone };
  }
  if (wants.includes('service')) {
    a.service = { id: r.service_id, name: r.s_name, duration: r.s_duration, price: r.s_price };
  }
  if (wants.includes('provider')) {
    a.provider = { id: r.provider_id, firstName: r.p_first_name, lastName: r.p_last_name, address: r.p_address || '', profesional: r.p_profesional || '' };
  }
  return a;
}

module.exports = { register };
