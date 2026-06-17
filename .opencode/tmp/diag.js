const bp = require('/usr/local/lib/node_modules/better-sqlite3');
const db = new bp('/home/node/.n8n/database.sqlite');

// Check webhook_entity for WF3/WF4
const wf3 = db.prepare("SELECT * FROM webhook_entity WHERE workflowId = 'wf3-cancelacion'").all();
const wf4 = db.prepare("SELECT * FROM webhook_entity WHERE workflowId = 'wf4-reagendado'").all();
console.log('WF3 webhooks:', JSON.stringify(wf3, null, 2));
console.log('WF4 webhooks:', JSON.stringify(wf4, null, 2));

// Check WF3 active version details
const wf3ent = db.prepare("SELECT versionId, activeVersionId, name FROM workflow_entity WHERE id = 'wf3-cancelacion'").get();
console.log('\nWF3 entity:', JSON.stringify(wf3ent));

// Check the active version history's nodes count
const hist = db.prepare("SELECT versionId, nodes, connections FROM workflow_history WHERE versionId = ?").get(wf3ent.activeVersionId);
if (hist) {
  const nodes = JSON.parse(hist.nodes);
  const conns = JSON.parse(hist.connections);
  console.log('Active version node count:', nodes.length);
  console.log('Node names:', nodes.map(n => n.name).join(', '));
  console.log('Connection sources:', Object.keys(conns).join(', '));
  // Check webhook node
  const wh = nodes.find(n => n.id === 'webhook-trigger');
  if (wh) {
    console.log('Webhook node name:', wh.name, 'type:', wh.type, 'typeVersion:', wh.typeVersion);
    console.log('Webhook params:', JSON.stringify(wh.parameters));
    console.log('Webhook node connections:', JSON.stringify(conns[wh.name]));
  }
}
db.close();
