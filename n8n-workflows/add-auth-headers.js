// Fix n8n workflow exports: add x-api-key header to scheduler calls, normalize to UTF-8.
const fs = require('fs');
const path = require('path');

const dir = __dirname;
const files = fs.readdirSync(dir).filter(f => f.startsWith('WF') && f.endsWith('.json'));

for (const file of files) {
  const filepath = path.join(dir, file);
  const buf = fs.readFileSync(filepath);

  // Detect encoding: BOM FE FF = UTF-16LE, BOM EF BB BF = UTF-8 BOM, else UTF-8
  let text;
  if (buf[0] === 0xFF && buf[1] === 0xFE) {
    // UTF-16LE: strip BOM
    text = buf.toString('utf-16le').replace(/^\uFEFF/, '');
    // Strip n8n deprecation warnings before JSON
    text = text.replace(/^Deprecation warning:.*?\n/s, '').trim();
  } else if (buf[0] === 0xEF && buf[1] === 0xBB && buf[2] === 0xBF) {
    text = buf.toString('utf-8').replace(/^\uFEFF/, '');
  } else {
    text = buf.toString('utf-8');
  }

  // Find JSON start
  const isArray = text.trim().startsWith('[');
  const idx = text.indexOf(isArray ? '[' : '{');
  if (idx < 0) { console.log(file + ': no JSON structure found'); continue; }

  let json;
  try {
    json = JSON.parse(text.slice(idx));
  } catch (e) {
    console.log(file + ': JSON parse error: ' + e.message);
    continue;
  }

  const workflows = isArray ? json : [json];
  let totalChanged = 0;

  for (const wf of workflows) {
    for (const node of wf.nodes || []) {
      if (node.type !== 'n8n-nodes-base.httpRequest') continue;

      const url = (node.parameters?.url || '').toLowerCase();
      if (!url.includes('whatsapp/send') && !url.includes('/api/v1/appointments')) continue;

      const headers = node.parameters.headerParameters?.parameters || node.parameters.headerParameters || [];
      const hasApiKey = Array.isArray(headers) && headers.some(h => h.name === 'x-api-key');
      if (hasApiKey) continue;

      if (!Array.isArray(headers)) {
        node.parameters.headerParameters = { parameters: [] };
      } else {
        node.parameters.headerParameters = { parameters: headers };
      }
      node.parameters.headerParameters.parameters.push({
        name: 'x-api-key',
        value: '={{ $env.SCHEDULER_API_KEY }}'
      });
      totalChanged++;
      console.log('  ' + (node.name || 'unnamed') + ': +x-api-key');
    }
  }

  fs.writeFileSync(filepath, JSON.stringify(isArray ? workflows : json, null, 2), 'utf-8');
  const changed = totalChanged ? ' (' + totalChanged + ' auth headers added)' : '';
  console.log(file + ': saved UTF-8' + changed);
}

console.log('Done.');
