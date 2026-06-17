const bp=require('/usr/local/lib/node_modules/better-sqlite3');
const db=new bp('/home/node/.n8n/database.sqlite');
const r=db.prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name='workflow_entity'").get();
console.log(r.sql);
db.close();
