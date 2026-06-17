const bp=require('/usr/local/lib/node_modules/better-sqlite3');
const db=new bp('/home/node/.n8n/database.sqlite');
db.prepare("DELETE FROM webhook_entity WHERE webhookPath NOT IN ('whatsapp-cancelacion','whatsapp-reagendado')").run();
console.log('Cleaned up webhook_entity');
const r=db.prepare('SELECT * FROM webhook_entity ORDER BY workflowId,method').all();
console.log(JSON.stringify(r,null,2));
db.close();
