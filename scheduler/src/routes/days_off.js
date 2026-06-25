const { getDb } = require('../db');

function register(router) {
  router.get('/days_off', (req, res) => {
    const db = getDb();
    const rows = db.prepare('SELECT * FROM days_off WHERE provider_id = 5 ORDER BY date DESC').all();
    res.json(rows);
  });

  router.post('/days_off', (req, res) => {
    const db = getDb();
    const { date, reason } = req.body || {};
    if (!date) return res.status(400).json({ success: false, message: 'date requerido' });
    const apptDate = date.split(' ')[0];
    const active = db.prepare("SELECT COUNT(*) as c FROM appointments WHERE provider_id = 5 AND start LIKE ? AND status = 'confirmed'").get(apptDate + '%');
    if (active.c > 0) {
      return res.status(409).json({ success: false, message: `Hay ${active[0].c} turno(s) activo(s) en esta fecha. Reagendalos manualmente antes de bloquear el día.` });
    }
    try {
      db.prepare('INSERT OR REPLACE INTO days_off (provider_id, date, reason) VALUES (5, ?, ?)').run(apptDate, reason || '');
      res.json({ success: true, date: apptDate, reason: reason || '' });
    } catch (e) {
      res.status(500).json({ success: false, message: 'Error al guardar' });
    }
  });

  router.delete('/days_off/:date', (req, res) => {
    const db = getDb();
    db.prepare('DELETE FROM days_off WHERE provider_id = 5 AND date = ?').run(req.params.date);
    res.json({ success: true });
  });
}

module.exports = { register };
