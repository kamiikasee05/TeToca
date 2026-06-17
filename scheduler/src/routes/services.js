const { getDb } = require('../db');

function register(router) {
  router.get('/services', (req, res) => {
    const db = getDb();
    const rows = db.prepare('SELECT * FROM services ORDER BY id').all();
    res.json(rows.map(mapService));
  });

  router.get('/services/:id', (req, res) => {
    const db = getDb();
    const row = db.prepare('SELECT * FROM services WHERE id = ?').get(+req.params.id);
    if (!row) return res.status(404).json({ success: false, message: 'Servicio no encontrado' });
    res.json(mapService(row));
  });

  router.post('/services', (req, res) => {
    const db = getDb();
    const { name, duration, price, currency, description, slotInterval, attendantsNumber, serviceCategoryId } = req.body || {};
    if (!name || !duration) {
      return res.status(400).json({ success: false, message: 'name y duration requeridos' });
    }
    const result = db.prepare(`
      INSERT INTO services (name, duration, price, currency, description, slot_interval, attendants_number, category_id)
      VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    `).run(name, duration, price || 0, currency || 'ARS', description || '', slotInterval || 15, attendantsNumber || 1, serviceCategoryId || null);
    const row = db.prepare('SELECT * FROM services WHERE id = ?').get(result.lastInsertRowid);
    res.status(201).json(mapService(row));
  });

  router.put('/services/:id', (req, res) => {
    const db = getDb();
    const existing = db.prepare('SELECT * FROM services WHERE id = ?').get(+req.params.id);
    if (!existing) return res.status(404).json({ success: false, message: 'Servicio no encontrado' });
    const d = req.body || {};
    db.prepare(`
      UPDATE services SET name=?, duration=?, price=?, currency=?, description=?, slot_interval=?, attendants_number=?, category_id=?
      WHERE id=?
    `).run(
      d.name ?? existing.name, d.duration ?? existing.duration, d.price ?? existing.price,
      d.currency ?? existing.currency, d.description ?? existing.description,
      d.slotInterval ?? existing.slot_interval, d.attendantsNumber ?? existing.attendants_number,
      d.serviceCategoryId ?? existing.category_id, +req.params.id
    );
    const row = db.prepare('SELECT * FROM services WHERE id = ?').get(+req.params.id);
    res.json(mapService(row));
  });

  router.delete('/services/:id', (req, res) => {
    const db = getDb();
    const row = db.prepare('SELECT * FROM services WHERE id = ?').get(+req.params.id);
    if (!row) return res.status(404).json({ success: false, message: 'Servicio no encontrado' });
    db.prepare('DELETE FROM services WHERE id = ?').run(+req.params.id);
    res.json(mapService(row));
  });
}

function mapService(r) {
  return {
    id: r.id,
    name: r.name,
    duration: r.duration,
    price: r.price,
    currency: r.currency,
    description: r.description,
    slotInterval: r.slot_interval,
    attendantsNumber: r.attendants_number,
    serviceCategoryId: r.category_id,
  };
}

module.exports = { register };
