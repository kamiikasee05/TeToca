const { getDb } = require('../db');

function register(router) {
  router.get('/slots', (req, res) => {
    const db = getDb();
    const serviceId = +req.query.serviceId;
    const date = req.query.date;
    if (!serviceId || !/^\d{4}-\d{2}-\d{2}$/.test(date)) {
      return res.status(400).json({ error: 'Faltan serviceId y date (YYYY-MM-DD)' });
    }

    const service = db.prepare('SELECT * FROM services WHERE id = ?').get(serviceId);
    if (!service) return res.json({ slots: [], error: 'Servicio no encontrado' });

    const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const dt = new Date(date + 'T12:00:00');
    const dayOfWeek = dayNames[dt.getDay()];

    const dayOff = db.prepare('SELECT reason FROM days_off WHERE provider_id = 5 AND date = ?').get(date);
    if (dayOff) {
      return res.json({ slots: [], dayOff: true, reason: dayOff.reason });
    }

    const prov = db.prepare('SELECT * FROM provider_settings WHERE provider_id = 5').get();
    if (!prov) return res.json({ slots: [], error: 'Profesional no configurado' });

    let wp = {};
    try { wp = JSON.parse(prov.working_plan); } catch {}
    const day = wp[dayOfWeek];
    if (!day || !day.start || !day.end) {
      return res.json({ slots: [], dayOff: true });
    }

    const dayStart = new Date(`${date}T${day.start}:00`).getTime();
    const dayEnd = new Date(`${date}T${day.end}:00`).getTime();
    const duration = service.duration * 60 * 1000;
    const slotInterval = (service.slot_interval || service.duration) * 60 * 1000;

    const appointments = db.prepare(`
      SELECT start, end FROM appointments
      WHERE provider_id = 5 AND start LIKE ? AND status != 'cancelled'
    `).all(`${date}%`);

    const existing = appointments.map(a => ({
      start: new Date(a.start).getTime(),
      end: new Date(a.end).getTime(),
    }));

    const breaks = (day.breaks || []).filter(b => b.start && b.end).map(b => ({
      start: new Date(`${date}T${b.start}:00`).getTime(),
      end: new Date(`${date}T${b.end}:00`).getTime(),
    }));

    const slots = [];
    const now = Date.now();
    const isToday = new Date().toISOString().split('T')[0] === date;

    for (let slotStart = dayStart; slotStart + duration <= dayEnd; slotStart += slotInterval) {
      const slotEnd = slotStart + duration;
      if (isToday && slotStart <= now) continue;
      if (breaks.some(b => slotStart < b.end && slotEnd > b.start)) continue;
      if (existing.some(a => slotStart < a.end && slotEnd > a.start)) continue;
      slots.push(new Date(slotStart).toTimeString().slice(0, 5));
    }

    res.json({ slots, date, serviceId, duration: service.duration, dayOff: false });
  });

  router.get('/availabilities', (req, res) => {
    const { providerId, serviceId, date } = req.query;
    if (!providerId || !serviceId || !date) {
      return res.status(400).json({ success: false, message: 'providerId, serviceId y date requeridos' });
    }
    const db = getDb();
    const service = db.prepare('SELECT * FROM services WHERE id = ?').get(+serviceId);
    if (!service) return res.json([]);

    const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
    const dt = new Date(date + 'T12:00:00');
    const dayOfWeek = dayNames[dt.getDay()];

    const dayOff = db.prepare('SELECT reason FROM days_off WHERE provider_id = ? AND date = ?').get(+providerId, date);
    if (dayOff) {
      return res.json({ dayOff: true, reason: dayOff.reason });
    }

    const prov = db.prepare('SELECT * FROM provider_settings WHERE provider_id = ?').get(+providerId);
    if (!prov) return res.json([]);

    let wp = {};
    try { wp = JSON.parse(prov.working_plan); } catch {}
    const day = wp[dayOfWeek];
    if (!day || !day.start || !day.end) return res.json([]);

    const dayStart = new Date(`${date}T${day.start}:00`).getTime();
    const dayEnd = new Date(`${date}T${day.end}:00`).getTime();
    const duration = service.duration * 60 * 1000;
    const slotInterval = (service.slot_interval || service.duration) * 60 * 1000;

    const appointments = db.prepare(`
      SELECT start, end FROM appointments
      WHERE provider_id = ? AND start LIKE ? AND status != 'cancelled'
    `).all(+providerId, `${date}%`);

    const existing = appointments.map(a => ({
      start: new Date(a.start).getTime(),
      end: new Date(a.end).getTime(),
    }));

    const breaks = (day.breaks || []).filter(b => b.start && b.end).map(b => ({
      start: new Date(`${date}T${b.start}:00`).getTime(),
      end: new Date(`${date}T${b.end}:00`).getTime(),
    }));

    const slots = [];
    const now = Date.now();
    const isToday = new Date().toISOString().split('T')[0] === date;

    for (let slotStart = dayStart; slotStart + duration <= dayEnd; slotStart += slotInterval) {
      const slotEnd = slotStart + duration;
      if (isToday && slotStart <= now) continue;
      if (breaks.some(b => slotStart < b.end && slotEnd > b.start)) continue;
      if (existing.some(a => slotStart < a.end && slotEnd > a.start)) continue;
      slots.push(new Date(slotStart).toTimeString().slice(0, 5));
    }

    res.json(slots);
  });
}

module.exports = { register };
