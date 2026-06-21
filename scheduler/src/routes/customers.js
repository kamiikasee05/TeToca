const { getDb } = require('../db');

function register(router) {
  router.get('/customers', (req, res) => {
    const db = getDb();
    const { q } = req.query;
    let rows;
    if (q) {
      rows = db.prepare('SELECT * FROM customers WHERE phone LIKE ? OR email LIKE ? ORDER BY id')
        .all(`%${q}%`, `%${q}%`);
    } else {
      rows = db.prepare('SELECT * FROM customers ORDER BY id').all();
    }
    res.json(rows.map(mapCustomer));
  });

  router.get('/customers/:id', (req, res) => {
    const db = getDb();
    const row = db.prepare('SELECT * FROM customers WHERE id = ?').get(+req.params.id);
    if (!row) return res.status(404).json({ success: false, message: 'Cliente no encontrado' });
    res.json(mapCustomer(row));
  });

  router.post('/customers', (req, res) => {
    const db = getDb();
    const { firstName, lastName, email, phone } = req.body || {};
    if (!firstName || !phone) {
      return res.status(400).json({ success: false, message: 'firstName y phone requeridos' });
    }
    const existing = db.prepare('SELECT * FROM customers WHERE phone = ?').get(phone);
    if (existing) {
      if (firstName && firstName !== existing.first_name) {
        db.prepare("UPDATE customers SET first_name = ?, last_name = ?, email = COALESCE(NULLIF(?, ''), email) WHERE id = ?")
          .run(firstName, lastName || existing.last_name, email || '', existing.id);
      }
      const updated = db.prepare('SELECT * FROM customers WHERE id = ?').get(existing.id);
      return res.status(200).json(mapCustomer(updated));
    }
    const result = db.prepare(`
      INSERT INTO customers (first_name, last_name, email, phone)
      VALUES (?, ?, ?, ?)
    `).run(firstName, lastName || '', email || '', phone);
    const row = db.prepare('SELECT * FROM customers WHERE id = ?').get(result.lastInsertRowid);
    res.status(201).json(mapCustomer(row));
  });

  router.put('/customers/:id', (req, res) => {
    const db = getDb();
    const existing = db.prepare('SELECT * FROM customers WHERE id = ?').get(+req.params.id);
    if (!existing) return res.status(404).json({ success: false, message: 'Cliente no encontrado' });
    const d = req.body || {};
    db.prepare(`
      UPDATE customers SET first_name=?, last_name=?, email=?, phone=? WHERE id=?
    `).run(
      d.firstName ?? existing.first_name, d.lastName ?? existing.last_name,
      d.email ?? existing.email, d.phone ?? existing.phone, +req.params.id
    );
    const row = db.prepare('SELECT * FROM customers WHERE id = ?').get(+req.params.id);
    res.json(mapCustomer(row));
  });

  router.delete('/customers/:id', (req, res) => {
    const db = getDb();
    const row = db.prepare('SELECT * FROM customers WHERE id = ?').get(+req.params.id);
    if (!row) return res.status(404).json({ success: false, message: 'Cliente no encontrado' });
    const pending = db.prepare('SELECT COUNT(*) as c FROM appointments WHERE customer_id = ?').get(+req.params.id);
    if (pending.c > 0) {
      return res.status(409).json({ success: false, message: `No se puede eliminar: tiene ${pending.c} turnos asociados` });
    }
    db.prepare('DELETE FROM customers WHERE id = ?').run(+req.params.id);
    res.json(mapCustomer(row));
  });
}

function mapCustomer(r) {
  return {
    id: r.id,
    firstName: r.first_name,
    lastName: r.last_name,
    email: r.email,
    phone: r.phone,
  };
}

module.exports = { register };
