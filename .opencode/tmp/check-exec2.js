const bp=require('/usr/local/lib/node_modules/better-sqlite3');
const db=new bp('/home/node/.n8n/database.sqlite');

// Check execution #21 (successful WF4) and #9 (error WF3)
for (const id of [21, 9]) {
  const exec = db.prepare('SELECT * FROM execution_entity WHERE id=?').get(id);
  if (exec) {
    console.log('Execution', id, ':', exec.workflowId, exec.status, exec.stoppedAt);
    const data = db.prepare('SELECT data FROM execution_data WHERE executionId=?').get(id);
    if (data) {
      console.log('  Data length:', data.data.length);
      // Just show node names from runData
      try {
        const d = JSON.parse(data.data);
        if (d.resultData?.runData) {
          console.log('  Run nodes:', Object.keys(d.resultData.runData).join(', '));
        }
      } catch(e) { console.log('  Parse error:', e.message); }
    } else {
      console.log('  No execution_data');
    }
  }
}

// Also check the latest executions that might be from our tests
const recent = db.prepare("SELECT id,workflowId,status,stoppedAt FROM execution_entity WHERE workflowId IN ('wf3-cancelacion','wf4-reagendado') ORDER BY id DESC LIMIT 5").all();
console.log('\nRecent WF3/WF4 executions:', JSON.stringify(recent));

db.close();
