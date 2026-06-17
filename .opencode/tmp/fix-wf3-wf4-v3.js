// Fix WF3 and WF4 - v3: also update versionId, use proper Set node format
const Database = require('/usr/local/lib/node_modules/better-sqlite3');
const crypto = require('crypto');
const db = new Database('/home/node/.n8n/database.sqlite');

function uuid() { return crypto.randomUUID(); }
function now() { return new Date().toISOString().replace('T',' ').substring(0,23).replace('Z','000'); }

function makeSetNode(id, name, pos, assignments) {
  return {
    id, name, type: 'n8n-nodes-base.set', typeVersion: 1, position: pos,
    parameters: {
      values: { string: assignments.map(a => ({ name: a.name, value: a.value })) },
      options: {}
    }
  };
}

// ===================== WF3: Cancelacion =====================
(function() {
  const wf = db.prepare('SELECT * FROM workflow_entity WHERE id=?').get('wf3-cancelacion');
  const origHist = db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get('4668a1c7-f9a9-4eb7-b6d4-fa4c829a32b7');
  const newNodes = JSON.parse(origHist.nodes);
  const ts = now();

  // Change webhook responseMode from responseNode to lastNode (no Respond node exists)
  const wh = newNodes.find(n => n.id === 'webhook-trigger');
  wh.parameters.responseMode = 'lastNode';

  // --- Normalize Set node ---
  const normalizeId = uuid();
  newNodes.push(makeSetNode(normalizeId, 'Normalize', [350, 300], [
    { name: 'phone', value: '={{ ($json.from || "").replace(/@c\\.us$/, "") }}' },
    { name: 'text', value: '={{ $json.body }}' }
  ]));

  // --- Fix IF: $json.text -> $json.body ---
  const ifNode = newNodes.find(n => n.id === 'check-cancel');
  ifNode.parameters.conditions.string[0].value1 = '={{ $json.body }}';
  ifNode.parameters.options = { caseSensitive: false };

  // --- Set nodes for messages ---
  const setConfirmId = uuid();
  newNodes.push(makeSetNode(setConfirmId, 'Set Confirm Cancel Msg', [1900, 300], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'Tu turno ha sido cancelado exitosamente. Para reagendar visita: https://ejemplo.com/reservar' }
  ]));

  const setOwnerId = uuid();
  newNodes.push(makeSetNode(setOwnerId, 'Set Notify Owner Msg', [2050, 300], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'Un cliente acaba de cancelar su turno' }
  ]));

  const setNoBookId = uuid();
  newNodes.push(makeSetNode(setNoBookId, 'Set No Booking Msg', [1650, 550], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'No encontramos turnos activos para tu numero. Si necesitas ayuda, contactanos directamente.' }
  ]));

  // --- Connections ---
  const nc = {};

  nc['Webhook WhatsApp'] = { main: [[{ node: 'Normalize', type: 'main', index: 0 }]] };
  nc['Normalize'] = { main: [[{ node: '¿Contiene CANCELAR?', type: 'main', index: 0 }]] };
  nc['¿Contiene CANCELAR?'] = {
    main: [
      [{ node: 'Buscar cliente por teléfono', type: 'main', index: 0 }],
      []
    ]
  };
  nc['Buscar cliente por teléfono'] = { main: [[{ node: '¿Cliente encontrado?', type: 'main', index: 0 }]] };
  nc['¿Cliente encontrado?'] = {
    main: [
      [{ node: 'Buscar turnos del cliente', type: 'main', index: 0 }],
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }]
    ]
  };
  nc['Buscar turnos del cliente'] = { main: [[{ node: 'Filtrar turnos futuros del cliente', type: 'main', index: 0 }]] };
  nc['Filtrar turnos futuros del cliente'] = { main: [[{ node: '¿Turno futuro encontrado?', type: 'main', index: 0 }]] };
  nc['¿Turno futuro encontrado?'] = {
    main: [
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }],
      [{ node: 'Cancelar turno en EA', type: 'main', index: 0 }]
    ]
  };
  nc['Cancelar turno en EA'] = { main: [[{ node: 'Set Confirm Cancel Msg', type: 'main', index: 0 }]] };
  nc['Set Confirm Cancel Msg'] = { main: [[{ node: 'Enviar confirmación de cancelación', type: 'main', index: 0 }]] };
  nc['Enviar confirmación de cancelación'] = { main: [[{ node: 'Set Notify Owner Msg', type: 'main', index: 0 }]] };
  nc['Set Notify Owner Msg'] = { main: [[{ node: 'Notificar cancelación a dueña', type: 'main', index: 0 }]] };
  nc['Set No Booking Msg'] = { main: [[{ node: 'Responder sin turno activo', type: 'main', index: 0 }]] };

  // Write
  const newVer = uuid();
  const newCounter = wf.versionCounter + 1;
  db.prepare(`INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups)
    VALUES(?,?,?,?,?,?,?,?,?,?,?)`).run(
    newVer, wf.id, 'opencode', ts, ts, JSON.stringify(newNodes), JSON.stringify(nc),
    'v' + newCounter, 0, 'WF3: normalize + msg set nodes + lastNode (v3)', '[]'
  );
  db.prepare('UPDATE workflow_entity SET versionId=?,activeVersionId=?,versionCounter=?,updatedAt=? WHERE id=?').run(
    newVer, newVer, newCounter, ts, wf.id
  );
  console.log('WF3 done. ver=' + newVer + ' counter=' + newCounter);
})();

// ===================== WF4: Reagendado =====================
(function() {
  const wf = db.prepare('SELECT * FROM workflow_entity WHERE id=?').get('wf4-reagendado');
  const origHist = db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get('5385b7e3-7f1a-4f1a-ae24-7d2aaebe060f');
  const newNodes = JSON.parse(origHist.nodes);
  const ts = now();

  // Change webhook responseMode
  const wh = newNodes.find(n => n.id === 'webhook-trigger');
  wh.parameters.responseMode = 'lastNode';

  // --- Normalize Set node ---
  const normalizeId = uuid();
  newNodes.push(makeSetNode(normalizeId, 'Normalize', [350, 300], [
    { name: 'phone', value: '={{ ($json.from || "").replace(/@c\\.us$/, "") }}' },
    { name: 'text', value: '={{ $json.body }}' }
  ]));

  // --- Fix IF: $json.text -> $json.body ---
  const ifNode = newNodes.find(n => n.id === 'check-keywords');
  ifNode.parameters.conditions.string[0].value1 = '={{ $json.body }}';
  if (ifNode.parameters.conditionsUi) {
    ifNode.parameters.conditionsUi.and[0].conditions[0].leftValue = '={{ $json.body }}';
    ifNode.parameters.conditionsUi.and[1].conditions[0].leftValue = '={{ $json.body }}';
  }

  // --- Set nodes for messages ---
  const setLinkId = uuid();
  newNodes.push(makeSetNode(setLinkId, 'Set Send Link Msg', [1900, 300], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'Tu turno ha sido cancelado. Por favor ingresa a nuestro sistema para reagendar un nuevo turno.' }
  ]));

  const setNoBookId = uuid();
  newNodes.push(makeSetNode(setNoBookId, 'Set No Booking Msg', [1650, 550], [
    { name: 'phone', value: '={{ $("Normalize").first().json.phone }}' },
    { name: 'message', value: 'No encontramos turnos activos para tu numero. Si necesitas ayuda, contactanos directamente.' }
  ]));

  // --- Connections ---
  const nc = {};

  nc['Webhook WhatsApp'] = { main: [[{ node: 'Normalize', type: 'main', index: 0 }]] };
  nc['Normalize'] = { main: [[{ node: '¿Contiene CAMBIAR o REAGENDAR?', type: 'main', index: 0 }]] };
  nc['¿Contiene CAMBIAR o REAGENDAR?'] = {
    main: [
      [{ node: 'Buscar cliente por teléfono', type: 'main', index: 0 }],
      []
    ]
  };
  nc['Buscar cliente por teléfono'] = { main: [[{ node: '¿Cliente encontrado?', type: 'main', index: 0 }]] };
  nc['¿Cliente encontrado?'] = {
    main: [
      [{ node: 'Buscar turnos del cliente', type: 'main', index: 0 }],
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }]
    ]
  };
  nc['Buscar turnos del cliente'] = { main: [[{ node: 'Filtrar turnos futuros del cliente', type: 'main', index: 0 }]] };
  nc['Filtrar turnos futuros del cliente'] = { main: [[{ node: '¿Turno futuro encontrado?', type: 'main', index: 0 }]] };
  nc['¿Turno futuro encontrado?'] = {
    main: [
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }],
      [{ node: 'Cancelar turno actual', type: 'main', index: 0 }]
    ]
  };
  nc['Cancelar turno actual'] = { main: [[{ node: 'Set Send Link Msg', type: 'main', index: 0 }]] };
  nc['Set Send Link Msg'] = { main: [[{ node: 'Enviar link de reserva', type: 'main', index: 0 }]] };
  nc['Set No Booking Msg'] = { main: [[{ node: 'Responder sin turno activo', type: 'main', index: 0 }]] };

  // Write
  const newVer = uuid();
  const newCounter = wf.versionCounter + 1;
  db.prepare(`INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups)
    VALUES(?,?,?,?,?,?,?,?,?,?,?)`).run(
    newVer, wf.id, 'opencode', ts, ts, JSON.stringify(newNodes), JSON.stringify(nc),
    'v' + newCounter, 0, 'WF4: normalize + msg set nodes + lastNode (v3)', '[]'
  );
  db.prepare('UPDATE workflow_entity SET versionId=?,activeVersionId=?,versionCounter=?,updatedAt=? WHERE id=?').run(
    newVer, newVer, newCounter, ts, wf.id
  );
  console.log('WF4 done. ver=' + newVer + ' counter=' + newCounter);
})();

console.log('All done.');
db.close();
