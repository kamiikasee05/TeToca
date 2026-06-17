const http = require('http');
const https = require('https');

const N8N_WEBHOOK_URL = process.env.N8N_WEBHOOK_URL || '';

function fire(event, payload) {
  if (!N8N_WEBHOOK_URL) return;
  const url = `${N8N_WEBHOOK_URL.replace(/\/+$/, '')}/${event}`;
  const body = JSON.stringify({ event, ...payload });
  const client = url.startsWith('https') ? https : http;
  try {
    const req = client.request(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) },
      timeout: 3000,
    });
    req.write(body);
    req.end();
  } catch { }
}

module.exports = { fire };
