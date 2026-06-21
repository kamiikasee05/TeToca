const http = require('http');

const OPENWA_HOST = process.env.OPENWA_HOST || 'openwa';
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
      return res.status(500).json({ success: false, message: 'WhatsApp no configurado (faltan variables de entorno)' });
    }
    const phone = req.body?.phone || req.query?.phone;
    const message = req.body?.message || req.query?.message;
    if (!phone || !message) {
      return res.status(400).json({ success: false, message: 'phone y message requeridos' });
    }
    if (message.length > 4096) {
      return res.status(400).json({ success: false, message: 'El mensaje excede el límite de 4096 caracteres de WhatsApp' });
    }

    const body = JSON.stringify({
      chatId: phone.includes('@c.us') ? phone : phone + '@c.us',
      text: message
    });

    const options = {
      hostname: OPENWA_HOST,
      port: OPENWA_PORT,
      path: `/api/sessions/${OPENWA_SESSION_ID}/messages/send-text`,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-API-Key': OPENWA_API_KEY,
        'Content-Length': Buffer.byteLength(body)
      }
    };

    const request = http.request(options, (openwaRes) => {
      let data = '';
      openwaRes.on('data', (chunk) => data += chunk);
      openwaRes.on('end', () => {
        res.status(openwaRes.statusCode).json({
          success: openwaRes.statusCode >= 200 && openwaRes.statusCode < 300,
          statusCode: openwaRes.statusCode,
          response: data
        });
      });
    });

    request.on('error', () => {
      res.status(502).json({ success: false, message: 'Error al enviar mensaje' });
    });

    request.write(body);
    request.end();
  };

  router.post('/whatsapp/send', handler);
  router.get('/whatsapp/send', handler);
}

function sendWhatsApp(phone, message) {
  if (!OPENWA_API_KEY || !OPENWA_SESSION_ID) return;
  if (!phone || !message) return;
  const body = JSON.stringify({ chatId: phone.includes('@c.us') ? phone : phone + '@c.us', text: message });
  const opts = { hostname: OPENWA_HOST, port: OPENWA_PORT, path: `/api/sessions/${OPENWA_SESSION_ID}/messages/send-text`, method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-API-Key': OPENWA_API_KEY, 'Content-Length': Buffer.byteLength(body) } };
  try { const r = http.request(opts); r.write(body); r.end(); } catch {}
}

module.exports = { register, sendWhatsApp };
