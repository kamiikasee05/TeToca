// Workflows formerly in n8n — now run directly in scheduler
const cron = require('node-cron');
const { getDb } = require('./db');
const { sendWhatsApp } = require('./routes/whatsapp');

function startCronJobs() {
  // WF-2: Daily reminder at 21:00 ART (00:00 UTC) — find tomorrow's appointments
  cron.schedule('0 0 * * *', () => {
    console.log('[wf] running daily reminder');
    const db = getDb();
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const dateStr = tomorrow.toISOString().split('T')[0];

    const rows = db.prepare(`
      SELECT a.*, c.first_name, c.last_name, c.phone, s.name as svc_name,
        p.profesional, p.address
      FROM appointments a
      JOIN customers c ON a.customer_id = c.id
      JOIN services s ON a.service_id = s.id
      LEFT JOIN provider_settings p ON a.provider_id = p.provider_id
      LEFT JOIN days_off d ON d.provider_id = a.provider_id AND d.date = ?
      WHERE a.status = 'confirmed' AND a.start LIKE ? AND d.id IS NULL
    `).all(dateStr, dateStr + '%');

    for (const r of rows) {
      const time = (r.start || '').split(' ')[1]?.substring(0, 5) || '';
      const phone = (r.phone || '').replace(/\+/g, '').replace(/ /g, '');
      if (!phone || phone.length < 8) continue;
      const prof = r.profesional || 'Cecilia Natali Godoy';
      const addr = r.address || 'Mitre 456, Chamical';
      const msg = `⏰ Recordatorio: tenés un turno mañana, ${r.first_name}!\n\n` +
        `💅 ${r.svc_name}\n📅 ${dateStr} a las ${time}\n👩‍🎨 ${prof}\n📍 ${addr}\n\n` +
        `Para cancelar, respondé CANCELAR a este mensaje.`;
      sendWhatsApp(phone, msg);
    }
    console.log(`[wf] reminders sent: ${rows.length} appointments`);
  }, { timezone: 'America/Argentina/Buenos_Aires' });

  console.log('[wf] cron jobs started');
}

// WF-3/WF-4: Inbound WhatsApp messages via webhook from OpenWA
function registerWhatsAppWebhook(app) {
  app.post('/webhook/whatsapp', (req, res) => {
    const payload = req.body?.data || req.body || {};
    const from = (payload.from || '').replace(/@.*$/, '');
    const text = ((payload.body || '').toUpperCase());

    if (!text.includes('CANCELAR') && !text.includes('CAMBIAR') && !text.includes('REAGENDAR')) {
      return res.json({ processed: false });
    }

    console.log('[wf] WhatsApp inbound:', from, text.substring(0, 30));
    const db = getDb();

    // Get brand info for messages
    const brand = db.prepare('SELECT profesional, address FROM provider_settings WHERE provider_id = 5').get() || {};
    const brandProf = brand.profesional || 'Cecilia Natali Godoy';
    const brandAddr = brand.address || 'Mitre 456, Chamical';

    // Find confirmed appointments (search by phone if possible)
    const now = new Date().toISOString().replace('T', ' ').substring(0, 19);
    let appts = db.prepare(`
      SELECT a.*, c.first_name, c.last_name, c.phone
      FROM appointments a
      JOIN customers c ON a.customer_id = c.id
      WHERE a.status = 'confirmed' AND a.start > ?
      ORDER BY a.start
    `).all(now);

    // Try to filter by phone (from may be LID, not phone number — best effort)
    const found = appts.filter(a => {
      const p = (a.phone || '').replace(/\+/g, '').replace(/ /g, '');
      return p.includes(from) || from.includes(p.substring(p.length - 8));
    });

    if (found.length === 0) {
      // Broad search — all confirmed appointments (WF-3 v3 LID workaround)
      if (appts.length === 0) {
        return res.json({ processed: true, message: 'no appointments' });
      }
    }

    const candidates = found.length > 0 ? found : appts;

    if (candidates.length === 1) {
      // Single appointment — cancel directly
      const a = candidates[0];
      if (text.includes('CANCELAR')) {
        db.prepare('UPDATE appointments SET status = ? WHERE id = ?').run('cancelled', a.id);
        const phone = (a.phone || '').replace(/\+/g, '').replace(/ /g, '');
        sendWhatsApp(phone, `Hola ${a.first_name}, tu turno del ${(a.start||'').split(' ')[0]} fue cancelado.\n\n👩‍🎨 ${brandProf}\n📍 ${brandAddr}\n\nReserva un nuevo turno desde la web.`);
        console.log('[wf] cancelled appointment', a.id);
        return res.json({ processed: true, action: 'cancelled', id: a.id });
      } else {
        // CAMBIAR/REAGENDAR
        db.prepare('UPDATE appointments SET status = ? WHERE id = ?').run('cancelled', a.id);
        const phone = (a.phone || '').replace(/\+/g, '').replace(/ /g, '');
        sendWhatsApp(phone, `Hola ${a.first_name}, tu turno fue cancelado. Ingresá a nuestra web para reagendar uno nuevo.\n\n👩‍🎨 ${brandProf}\n📍 ${brandAddr}`);
        console.log('[wf] cancelled for reschedule', a.id);
        return res.json({ processed: true, action: 'cancelled_for_reschedule', id: a.id });
      }
    } else {
      // Multiple appointments — list them for the user to choose
      const list = candidates.map((a, i) =>
        `${i + 1}. ${(a.start||'').split(' ')[0]} ${((a.start||'').split(' ')[1]||'').substring(0,5)} — ID ${a.id}`
      ).join('\n');
      const phone = (candidates[0].phone || '').replace(/\+/g, '').replace(/ /g, '');
      const action = text.includes('CANCELAR') ? 'cancelar' : 'cambiar';
      sendWhatsApp(phone, `Tenés ${candidates.length} turnos activos. ¿Cuál querés ${action}?\n\n${list}\n\n👩‍🎨 ${brandProf}\n📍 ${brandAddr}\n\nRespondé con el número.`);
      console.log('[wf] multiple appointments, sent selection list');
      return res.json({ processed: true, action: 'list_sent', count: candidates.length });
    }
  });

  console.log('[wf] WhatsApp webhook registered at POST /webhook/whatsapp');
}

module.exports = { startCronJobs, registerWhatsAppWebhook };
