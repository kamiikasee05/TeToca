const bp=require('/usr/local/lib/node_modules/better-sqlite3');
const db=new bp('/home/node/.n8n/database.sqlite');

// Get execution #20 (successful WF3) and #21 (successful WF4) - look at execution data
for (const id of [20, 21]) {
  const exec = db.prepare('SELECT * FROM execution_entity WHERE id=?').get(id);
  if (exec) {
    console.log('Execution', id, '-', exec.workflowId, exec.status);
    const data = db.prepare('SELECT data FROM execution_data WHERE executionId=?').get(id);
    if (data) {
      try {
        const d = JSON.parse(data.data);
        // Check if this was triggered by webhook
        console.log('  Mode:', d.mode);
        console.log('  Started at:', d.startedAt);
        if (d.resultData?.runData) {
          Object.keys(d.resultData.runData).forEach(n => {
            const nd = d.resultData.runData[n];
            console.log('  Node:', n, '-', nd.length, 'runs');
          });
        }
        if (d.resultData?.error) {
          console.log('  Error:', d.resultData.error.message);
        }
      } catch(e) {}
    }
    console.log();
  }
}
db.close();
