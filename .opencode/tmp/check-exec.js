const bp=require('/usr/local/lib/node_modules/better-sqlite3');
const db=new bp('/home/node/.n8n/database.sqlite');
const r=db.prepare("SELECT id,workflowId,status,stoppedAt FROM execution_entity WHERE workflowId IN ('wf3-cancelacion','wf4-reagendado') ORDER BY id DESC LIMIT 10").all();
console.log(JSON.stringify(r,null,2));
db.close();
