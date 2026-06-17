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
  router.post('/whatsapp/send', (req, res) => {
    if (!OPENWA_API_KEY || !OPENWA_SESSION_ID) {
      return res.status(500).json({ success: false, message: 'WhatsApp no configurado (faltan variables de entorno)' });
    }
    const { phone, message } = req.body || {};
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

    request.on('error', (err) => {
      res.status(502).json({ success: false, error: err.message });
    });

    request.write(body);
    request.end();
  });
}

module.exports = { register };
