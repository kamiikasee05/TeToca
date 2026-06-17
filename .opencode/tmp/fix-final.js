// Final fix: Apply all WF3/WF4 fixes, preserving the existing structure
const bp = require('/usr/local/lib/node_modules/better-sqlite3');
const crypto = require('crypto');
const db = new bp('/home/node/.n8n/database.sqlite');

function uuid() { return crypto.randomUUID(); }
function ts() { return new Date().toISOString().replace('T',' ').substring(0,23).replace('Z','000'); }

function makeSetNode(id, name, pos, assignments) {
  return {
    id, name,
    type: 'n8n-nodes-base.set', typeVersion: 1,
    position: pos,
    parameters: {
      values: { string: assignments.map(a => ({ name: a.name, value: a.value })) },
      options: {}
    }
  };
}

// ===================== WF3 =====================
(function() {
  const wf = db.prepare("SELECT * FROM workflow_entity WHERE id='wf3-cancelacion'").get();
  const origId = '4668a1c7-f9a9-4eb7-b6d4-fa4c829a32b7';
  const hist = db.prepare("SELECT * FROM workflow_history WHERE versionId=?").get(origId);
  const nodes = JSON.parse(hist.nodes);
  const now = ts();

  // Change webhook responseMode
  const wh = nodes.find(n => n.id === 'webhook-trigger');
  wh.parameters.responseMode = 'lastNode';

  // Add Normalize Set node
  nodes.push(makeSetNode(uuid(), 'Normalize', [350, 300], [
    { name: 'phone', value: '={{ ($json.from || "").replace(/@c\\.us$/, "") }}' },
    { name: 'text', value: '={{ $json.body }}' }
  ]));

  // Fix IF condition
  const ifNode = nodes.find(n => n.id === 'check-cancel');
  ifNode.parameters.conditions.string[0].value1 = '={{ $json.body }}';
  ifNode.parameters.options = { caseSensitive: false };

  // Add message Set nodes
  nodes.push(makeSetNode(uuid(), 'Set Confirm Cancel Msg', [1900, 300], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'Tu turno ha sido cancelado exitosamente.' }
  ]));
  nodes.push(makeSetNode(uuid(), 'Set Notify Owner Msg', [2050, 300], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'Un cliente cancelo su turno.' }
  ]));
  nodes.push(makeSetNode(uuid(), 'Set No Booking Msg', [1650, 550], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'No encontramos turnos activos para tu numero.' }
  ]));

  // Connections
  const conn = {};

  conn['Webhook WhatsApp'] = { main: [[{ node: 'Normalize', type: 'main', index: 0 }]] };
  conn['Normalize'] = { main: [[{ node: '¿Contiene CANCELAR?', type: 'main', index: 0 }]] };
  conn['¿Contiene CANCELAR?'] = {
    main: [
      [{ node: 'Buscar cliente por teléfono', type: 'main', index: 0 }],
      []
    ]
  };
  conn['Buscar cliente por teléfono'] = { main: [[{ node: '¿Cliente encontrado?', type: 'main', index: 0 }]] };
  conn['¿Cliente encontrado?'] = {
    main: [
      [{ node: 'Buscar turnos del cliente', type: 'main', index: 0 }],
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }]
    ]
  };
  conn['Buscar turnos del cliente'] = { main: [[{ node: 'Filtrar turnos futuros del cliente', type: 'main', index: 0 }]] };
  conn['Filtrar turnos futuros del cliente'] = { main: [[{ node: '¿Turno futuro encontrado?', type: 'main', index: 0 }]] };
  conn['¿Turno futuro encontrado?'] = {
    main: [
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }],
      [{ node: 'Cancelar turno en EA', type: 'main', index: 0 }]
    ]
  };
  conn['Cancelar turno en EA'] = { main: [[{ node: 'Set Confirm Cancel Msg', type: 'main', index: 0 }]] };
  conn['Set Confirm Cancel Msg'] = { main: [[{ node: 'Enviar confirmación de cancelación', type: 'main', index: 0 }]] };
  conn['Enviar confirmación de cancelación'] = { main: [[{ node: 'Set Notify Owner Msg', type: 'main', index: 0 }]] };
  conn['Set Notify Owner Msg'] = { main: [[{ node: 'Notificar cancelación a dueña', type: 'main', index: 0 }]] };
  conn['Set No Booking Msg'] = { main: [[{ node: 'Responder sin turno activo', type: 'main', index: 0 }]] };

  const newVer = uuid();
  const newCounter = wf.versionCounter + 1;

  db.exec('PRAGMA foreign_keys=OFF');

  db.prepare("INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups) VALUES(?,?,?,?,?,?,?,?,?,?,?)").run(
    newVer, wf.id, 'opencode', now, now,
    JSON.stringify(nodes), JSON.stringify(conn),
    'v' + newCounter, 0,
    'Fixed field name mismatches: Normalize node, IF body check, message Set nodes',
    '[]'
  );

  db.prepare("UPDATE workflow_entity SET versionId=?,activeVersionId=?,versionCounter=?,nodes=?,connections=?,updatedAt=? WHERE id=?").run(
    newVer, newVer, newCounter,
    JSON.stringify(nodes), JSON.stringify(conn),
    now, wf.id
  );

  db.exec('PRAGMA foreign_keys=ON');

  console.log('WF3 fixed. ver=' + newVer + ' counter=' + newCounter);
})();

// ===================== WF4 =====================
(function() {
  const wf = db.prepare("SELECT * FROM workflow_entity WHERE id='wf4-reagendado'").get();
  const origId = '5385b7e3-7f1a-4f1a-ae24-7d2aaebe060f';
  const hist = db.prepare("SELECT * FROM workflow_history WHERE versionId=?").get(origId);
  const nodes = JSON.parse(hist.nodes);
  const now = ts();

  const wh = nodes.find(n => n.id === 'webhook-trigger');
  wh.parameters.responseMode = 'lastNode';

  nodes.push(makeSetNode(uuid(), 'Normalize', [350, 300], [
    { name: 'phone', value: '={{ ($json.from || "").replace(/@c\\.us$/, "") }}' },
    { name: 'text', value: '={{ $json.body }}' }
  ]));

  const ifNode = nodes.find(n => n.id === 'check-keywords');
  ifNode.parameters.conditions.string[0].value1 = '={{ $json.body }}';
  if (ifNode.parameters.conditionsUi) {
    ifNode.parameters.conditionsUi.and[0].conditions[0].leftValue = '={{ $json.body }}';
    ifNode.parameters.conditionsUi.and[1].conditions[0].leftValue = '={{ $json.body }}';
  }

  nodes.push(makeSetNode(uuid(), 'Set Send Link Msg', [1900, 300], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'Tu turno fue cancelado. Ingresa a nuestro sistema para reagendar.' }
  ]));
  nodes.push(makeSetNode(uuid(), 'Set No Booking Msg', [1650, 550], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'No encontramos turnos activos para tu numero.' }
  ]));

  const conn = {};

  conn['Webhook WhatsApp'] = { main: [[{ node: 'Normalize', type: 'main', index: 0 }]] };
  conn['Normalize'] = { main: [[{ node: '¿Contiene CAMBIAR o REAGENDAR?', type: 'main', index: 0 }]] };
  conn['¿Contiene CAMBIAR o REAGENDAR?'] = {
    main: [
      [{ node: 'Buscar cliente por teléfono', type: 'main', index: 0 }],
      []
    ]
  };
  conn['Buscar cliente por teléfono'] = { main: [[{ node: '¿Cliente encontrado?', type: 'main', index: 0 }]] };
  conn['¿Cliente encontrado?'] = {
    main: [
      [{ node: 'Buscar turnos del cliente', type: 'main', index: 0 }],
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }]
    ]
  };
  conn['Buscar turnos del cliente'] = { main: [[{ node: 'Filtrar turnos futuros del cliente', type: 'main', index: 0 }]] };
  conn['Filtrar turnos futuros del cliente'] = { main: [[{ node: '¿Turno futuro encontrado?', type: 'main', index: 0 }]] };
  conn['¿Turno futuro encontrado?'] = {
    main: [
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }],
      [{ node: 'Cancelar turno actual', type: 'main', index: 0 }]
    ]
  };
  conn['Cancelar turno actual'] = { main: [[{ node: 'Set Send Link Msg', type: 'main', index: 0 }]] };
  conn['Set Send Link Msg'] = { main: [[{ node: 'Enviar link de reserva', type: 'main', index: 0 }]] };
  conn['Set No Booking Msg'] = { main: [[{ node: 'Responder sin turno activo', type: 'main', index: 0 }]] };

  const newVer = uuid();
  const newCounter = wf.versionCounter + 1;

  db.exec('PRAGMA foreign_keys=OFF');

  db.prepare("INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups) VALUES(?,?,?,?,?,?,?,?,?,?,?)").run(
    newVer, wf.id, 'opencode', now, now,
    JSON.stringify(nodes), JSON.stringify(conn),
    'v' + newCounter, 0,
    'Fixed field name mismatches: Normalize node, IF body check, message Set nodes',
    '[]'
  );

  db.prepare("UPDATE workflow_entity SET versionId=?,activeVersionId=?,versionCounter=?,nodes=?,connections=?,updatedAt=? WHERE id=?").run(
    newVer, newVer, newCounter,
    JSON.stringify(nodes), JSON.stringify(conn),
    now, wf.id
  );

  db.exec('PRAGMA foreign_keys=ON');

  console.log('WF4 fixed. ver=' + newVer + ' counter=' + newCounter);
})();

console.log('Done.');
db.close();
