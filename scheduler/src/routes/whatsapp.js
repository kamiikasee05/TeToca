const http = require('http');

const OPENWA_HOST = process.env.OPENWA_HOST || 'tetoca_openwa';
const OPENWA_PORT = process.env.OPENWA_PORT || 2785;
const OPENWA_API_KEY = process.env.OPENWA_API_KEY;
const OPENWA_SESSION_ID = process.env.OPENWA_SESSION_ID;

if (!OPENWA_API_KEY) {
  console.error('FATAL: OPENWA_API_KEY no configurada');
}
if (!OPENWA_SESSION_ID) {
  console.error('FATAL: OPENWA_SESSION_ID no configurada');
}

function register(router) {
  const handler = (req, res) => {
    if (!OPENWA_API_KEY || !OPENWA_SESSION_ID) {
      return res.status(500).json({ success: false, message: 'WhatsApp no configurado' });
    }
    const phone = req.body?.phone || req.query?.phone;
    const message = req.body?.message || req.query?.message;
    if (!phone || !message) {
      return res.status(400).json({ success: false, message: 'phone y message requeridos' });
    }
    if (message.length > 4096) {
      return res.status(400).json({ success: false, message: 'Mensaje excede 4096 chars' });
    }

    // ponytail: node http module in slim images doesn't route to OpenWA correctly; use curl
    const { exec } = require('child_process');
    const fs = require('fs');
    const os = require('os');
    const chatId = phone.includes('@c.us') ? phone : phone + '@c.us';
    
    const tmpFile = os.tmpdir() + '/wa-' + Date.now() + '.json';
    fs.writeFileSync(tmpFile, JSON.stringify({ chatId, text: message }));
    const cmd = `curl -s -X POST http://${OPENWA_HOST}:${OPENWA_PORT}/api/sessions/${OPENWA_SESSION_ID}/messages/send-text -H 'Content-Type: application/json' -H 'X-API-Key: ${OPENWA_API_KEY}' --data-binary @${tmpFile}`;

    exec(cmd, { timeout: 15000 }, (err, stdout) => {
      fs.unlinkSync(tmpFile);
      if (err) return res.status(502).json({ success: false, message: 'Error al enviar mensaje' });
      try {
        const data = JSON.parse(stdout);
        const success = !!data.messageId;
        res.status(success ? 201 : 500).json({ success, statusCode: success ? 201 : 500, response: stdout });
      } catch {
        res.status(502).json({ success: false, message: 'Error al enviar mensaje' });
      }
    });
  };

  router.post('/whatsapp/send', handler);
  router.get('/whatsapp/send', handler);
}

function sendWhatsApp(phone, message) {
  if (!OPENWA_API_KEY || !OPENWA_SESSION_ID) { console.error('[whatsapp] missing config'); return; }
  if (!phone || !message) { console.error('[whatsapp] missing phone or message'); return; }
  const body = JSON.stringify({ chatId: phone.includes('@c.us') ? phone : phone + '@c.us', text: message });
  const opts = { hostname: OPENWA_HOST, port: OPENWA_PORT, path: `/api/sessions/${OPENWA_SESSION_ID}/messages/send-text`, method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-API-Key': OPENWA_API_KEY, 'Content-Length': Buffer.byteLength(body) } };
  const r = http.request(opts, (res) => {
    let d = ''; res.on('data', c => d += c); res.on('end', () => console.log('[whatsapp] sent to', phone, 'status:', res.statusCode, d.substring(0, 100)));
  });
  r.on('error', e => console.error('[whatsapp] error:', e.message));
  r.write(body); r.end();
}

module.exports = { register, sendWhatsApp };
