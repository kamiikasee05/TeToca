const bp=require('/usr/local/lib/node_modules/better-sqlite3');
const crypto=require('crypto');
const db=new bp('/home/node/.n8n/database.sqlite');

// Revert to ORIGINAL WF3, but remove webhookId
const origHist=db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get('4668a1c7-f9a9-4eb7-b6d4-fa4c829a32b7');
const nodes=JSON.parse(origHist.nodes);
const conn=JSON.parse(origHist.connections);
const ts=new Date().toISOString().replace('T',' ').substring(0,23).replace('Z','000');
const wf=db.prepare('SELECT * FROM workflow_entity WHERE id=?').get('wf3-cancelacion');

// Remove webhookId, change responseMode
const wh=nodes.find(n=>n.id==='webhook-trigger');
delete wh.webhookId;
wh.parameters.responseMode='lastNode';

const newVer=crypto.randomUUID();
db.prepare('INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups) VALUES(?,?,?,?,?,?,?,?,?,?,?)').run(newVer,wf.id,'opencode',ts,ts,JSON.stringify(nodes),JSON.stringify(conn),'v'+wf.versionCounter,0,'Test: remove webhookId','[]');

db.prepare('UPDATE workflow_entity SET versionId=?,activeVersionId=?,nodes=NULL,connections=NULL,versionCounter=versionCounter+1,updatedAt=? WHERE id=?').run(newVer,newVer,ts,wf.id);

console.log('WF3 updated: removed webhookId, lastNode mode. ver='+newVer);
db.close();
