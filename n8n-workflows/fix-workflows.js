const Database = require('better-sqlite3');
const db = new Database('/home/node/.n8n/database.sqlite');

const EA_CREDENTIAL = { id: 'g4JZy1RbupSK6sx9', name: 'EA Cred' };

function fixEmbeddedCredentials(node) {
  if (!node.parameters || !node.parameters.url) return false;
  const url = node.parameters.url;
  // Check for embedded credentials pattern: user:pass@host
  const credMatch = url.match(/\/\/([^:]+):([^@]+)@/);
  if (credMatch) {
    // Remove any embedded credentials from URL
    node.parameters.url = url.replace(`${credMatch[1]}:${credMatch[2]}@`, '');
    node.parameters.authentication = 'genericCredentialType';
    node.parameters.genericAuthType = 'httpBasicAuth';
    node.credentials = { httpBasicAuth: EA_CREDENTIAL };
    return true;
  }
  return false;
}

function fixSendQuery(node) {
  // For GET requests to whatsapp-send.php that build URL inline, set sendQuery: true
  if (node.parameters && node.parameters.method === 'GET' && node.parameters.sendQuery === false) {
    if (node.parameters.url && node.parameters.url.includes('whatsapp-send.php')) {
      node.parameters.sendQuery = true;
      return true;
    }
  }
  return false;
}

function swapIfConnections(connections, ifNodeName) {
  const conn = connections[ifNodeName];
  if (!conn || !conn.main || conn.main.length < 2) return false;
  const tmp = conn.main[0];
  conn.main[0] = conn.main[1];
  conn.main[1] = tmp;
  return true;
}

// --- WF2 ---
{
  const wf = db.prepare("SELECT id, activeVersionId FROM workflow_entity WHERE id = ?").get('wf2-recordatorio');
  if (wf) {
    console.log('=== WF2 (recordatorio) ===');
    const version = db.prepare("SELECT nodes, connections FROM workflow_history WHERE versionId = ?").get(wf.activeVersionId);
    const nodes = JSON.parse(version.nodes);
    const connections = JSON.parse(version.connections);
    let changes = 0;

    for (const node of nodes) {
      if (node.name === 'Consultar turnos EA' && fixEmbeddedCredentials(node)) {
        console.log('  Fixed: Consultar turnos EA - removed embedded credentials, using EA Cred');
        changes++;
      }
      if (node.name === 'Enviar recordatorio' && fixSendQuery(node)) {
        console.log('  Fixed: Enviar recordatorio - sendQuery: true');
        changes++;
      }
    }

    if (changes > 0) {
      const nodesJson = JSON.stringify(nodes);
      const connJson = JSON.stringify(connections);
      db.prepare("UPDATE workflow_history SET nodes = ?, connections = ? WHERE versionId = ?").run(nodesJson, connJson, wf.activeVersionId);
      db.prepare("UPDATE workflow_entity SET nodes = ?, connections = ? WHERE id = ?").run(nodesJson, connJson, 'wf2-recordatorio');
      console.log('  Applied', changes, 'fixes to WF2');
    } else {
      console.log('  No fixes needed');
    }
  } else {
    console.log('  WF2 not found');
  }
}

// --- WF3 ---
{
  const wf = db.prepare("SELECT id, activeVersionId FROM workflow_entity WHERE id = ?").get('wf3-cancelacion');
  if (wf) {
    console.log('=== WF3 (cancelacion) ===');
    const version = db.prepare("SELECT nodes, connections FROM workflow_history WHERE versionId = ?").get(wf.activeVersionId);
    const nodes = JSON.parse(version.nodes);
    const connections = JSON.parse(version.connections);
    let changes = 0;

    for (const node of nodes) {
      if (['Buscar cliente por teléfono', 'Buscar turnos del cliente', 'Cancelar turno en EA'].includes(node.name)) {
        if (fixEmbeddedCredentials(node)) {
          console.log('  Fixed:', node.name, '- removed embedded credentials, using EA Cred');
          changes++;
        }
      }
    }

    if (swapIfConnections(connections, '¿Contiene CANCELAR?')) {
      console.log('  Fixed: ¿Contiene CANCELAR? - swapped true/false branches');
      changes++;
    }

    if (changes > 0) {
      const nodesJson = JSON.stringify(nodes);
      const connJson = JSON.stringify(connections);
      db.prepare("UPDATE workflow_history SET nodes = ?, connections = ? WHERE versionId = ?").run(nodesJson, connJson, wf.activeVersionId);
      db.prepare("UPDATE workflow_entity SET nodes = ?, connections = ? WHERE id = ?").run(nodesJson, connJson, 'wf3-cancelacion');
      console.log('  Applied', changes, 'fixes to WF3');
    } else {
      console.log('  No fixes needed');
    }
  } else {
    console.log('  WF3 not found');
  }
}

// --- WF4 ---
{
  const wf = db.prepare("SELECT id, activeVersionId FROM workflow_entity WHERE id = ?").get('wf4-reagendado');
  if (wf) {
    console.log('=== WF4 (reagendado) ===');
    const version = db.prepare("SELECT nodes, connections FROM workflow_history WHERE versionId = ?").get(wf.activeVersionId);
    const nodes = JSON.parse(version.nodes);
    const connections = JSON.parse(version.connections);
    let changes = 0;

    for (const node of nodes) {
      if (['Buscar cliente por teléfono', 'Buscar turnos del cliente', 'Cancelar turno actual'].includes(node.name)) {
        if (fixEmbeddedCredentials(node)) {
          console.log('  Fixed:', node.name, '- removed embedded credentials, using EA Cred');
          changes++;
        }
      }
      if (['Enviar link de reserva', 'Responder sin turno activo'].includes(node.name)) {
        if (fixSendQuery(node)) {
          console.log('  Fixed:', node.name, '- sendQuery: true');
          changes++;
        }
      }
    }

    if (swapIfConnections(connections, '¿Contiene CAMBIAR o REAGENDAR?')) {
      console.log('  Fixed: ¿Contiene CAMBIAR o REAGENDAR? - swapped true/false branches');
      changes++;
    }

    if (changes > 0) {
      const nodesJson = JSON.stringify(nodes);
      const connJson = JSON.stringify(connections);
      db.prepare("UPDATE workflow_history SET nodes = ?, connections = ? WHERE versionId = ?").run(nodesJson, connJson, wf.activeVersionId);
      db.prepare("UPDATE workflow_entity SET nodes = ?, connections = ? WHERE id = ?").run(nodesJson, connJson, 'wf4-reagendado');
      console.log('  Applied', changes, 'fixes to WF4');
    } else {
      console.log('  No fixes needed');
    }
  } else {
    console.log('  WF4 not found');
  }
}

console.log('Done.');
db.close();
