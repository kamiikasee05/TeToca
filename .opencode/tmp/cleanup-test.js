const bp = require('/usr/local/lib/node_modules/better-sqlite3');
const db = new bp('/home/node/.n8n/database.sqlite');
db.exec('PRAGMA foreign_keys=OFF');
db.prepare('DELETE FROM webhook_entity WHERE workflowId=?').run('wf-test-echo');
db.prepare('DELETE FROM workflow_history WHERE workflowId=?').run('wf-test-echo');
db.prepare('DELETE FROM workflow_entity WHERE id=?').run('wf-test-echo');
db.exec('PRAGMA foreign_keys=ON');
console.log('Deleted test workflow');
db.close();
