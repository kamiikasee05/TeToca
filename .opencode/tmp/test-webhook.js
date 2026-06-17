const bp = require('/usr/local/lib/node_modules/better-sqlite3');
const crypto = require('crypto');
const db = new bp('/home/node/.n8n/database.sqlite');

function uuid() { return crypto.randomUUID(); }
function ts() { return new Date().toISOString().replace('T',' ').substring(0,23).replace('Z','000'); }

const wfId = 'wf-test-echo';
const verId = uuid();
const now = ts();

// Nodes: webhook -> set
const nodes = [
  {
    id: uuid(),
    name: 'Webhook',
    type: 'n8n-nodes-base.webhook',
    typeVersion: 1,
    position: [250, 300],
    parameters: { path: 'test-echo', responseMode: 'lastNode', options: {} },
    webhookId: 'test-echo'
  },
  {
    id: uuid(),
    name: 'Set',
    type: 'n8n-nodes-base.set',
    typeVersion: 1,
    position: [450, 300],
    parameters: {
      values: { string: [{ name: 'echo', value: '={{ $json.body || JSON.stringify($json) }}' }] },
      options: {}
    }
  }
];

const conn = {
  'Webhook': { main: [[{ node: 'Set', type: 'main', index: 0 }]] }
};

// Disable FK checks for cross-referencing inserts
db.exec('PRAGMA foreign_keys=OFF');

// Insert workflow_entity first
db.prepare(`INSERT OR REPLACE INTO workflow_entity
  (id, name, active, versionId, activeVersionId, versionCounter, createdAt, updatedAt, isArchived, nodeGroups)
  VALUES(?,?,?,?,?,?,?,?,?,?)`).run(
  wfId, 'Test Echo', 1, verId, verId, 1, now, now, 0, '[]'
);

// Then workflow_history
db.prepare(`INSERT OR REPLACE INTO workflow_history
  (versionId, workflowId, authors, createdAt, updatedAt, nodes, connections, name, autosaved, description, nodeGroups)
  VALUES(?,?,?,?,?,?,?,?,?,?,?)`).run(
  verId, wfId, 'opencode', now, now, JSON.stringify(nodes), JSON.stringify(conn), 'v1', 0, '', '[]'
);

db.exec('PRAGMA foreign_keys=ON');

// Register webhook
db.prepare('DELETE FROM webhook_entity WHERE workflowId = ?').run(wfId);
db.prepare('INSERT INTO webhook_entity(workflowId, webhookPath, method, node, webhookId, pathLength) VALUES(?,?,?,?,?,?)').run(wfId, 'test-echo', 'POST', 'Webhook', null, null);
db.prepare('INSERT INTO webhook_entity(workflowId, webhookPath, method, node, webhookId, pathLength) VALUES(?,?,?,?,?,?)').run(wfId, 'test-echo', 'GET', 'Webhook', null, null);

console.log('Created test workflow:', wfId, 'ver:', verId);
db.close();
