// Fix WF3 (cancelacion) and WF4 (reagendado) - field name mismatches v2
const Database = require('/usr/local/lib/node_modules/better-sqlite3');
const crypto = require('crypto');
const db = new Database('/home/node/.n8n/database.sqlite');

function uuid() { return crypto.randomUUID(); }
function now() { return new Date().toISOString().replace('T',' ').substring(0,23).replace('Z','000'); }

// Set node typeVersion 1 format (matching n8n 2.25.7)
function makeSetNode(id, name, pos, assignments) {
  const strings = assignments.map(a => ({ name: a.name, value: a.value }));
  return {
    id, name, type: 'n8n-nodes-base.set', typeVersion: 1, position: pos,
    parameters: {
      values: { string: strings },
      options: {}
    }
  };
}

// ===================== WF3: Cancelacion =====================
(function() {
  const wf = db.prepare('SELECT * FROM workflow_entity WHERE id=?').get('wf3-cancelacion');
  const hist = db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get(wf.activeVersionId);
  const nodes = JSON.parse(hist.nodes);
  const ts = now();

  // Remove any nodes I might have added in the previous run (clean slate from original)
  // Actually, we're reading from the activeVersionId which is now the NEW version from v1.
  // Let me read from the ORIGINAL activeVersionId that was there before my v1 changes.
  // I need the original: 4668a1c7-f9a9-4eb7-b6d4-fa4c829a32b7
  const origHist = db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get('4668a1c7-f9a9-4eb7-b6d4-fa4c829a32b7');
  const origNodes = JSON.parse(origHist.nodes);
  const newNodes = [...origNodes]; // fresh copy

  // --- Normalize Set node (typeVersion 1) ---
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
  db.prepare(`INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups)
    VALUES(?,?,?,?,?,?,?,?,?,?,?)`).run(
    newVer, wf.id, 'opencode', ts, ts, JSON.stringify(newNodes), JSON.stringify(nc),
    'v' + wf.versionCounter, 0, 'WF3: fixed field mismatches + normalize + msg set nodes (v2)', '[]'
  );
  db.prepare('UPDATE workflow_entity SET activeVersionId=?,versionCounter=?,updatedAt=? WHERE id=?').run(newVer, wf.versionCounter+1, ts, wf.id);
  console.log('WF3 done. verId=' + newVer + ' counter=' + (wf.versionCounter+1));
})();

// ===================== WF4: Reagendado =====================
(function() {
  const wf = db.prepare('SELECT * FROM workflow_entity WHERE id=?').get('wf4-reagendado');
  const origHist = db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get('5385b7e3-7f1a-4f1a-ae24-7d2aaebe060f');
  const newNodes = JSON.parse(origHist.nodes);
  const ts = now();

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
  db.prepare(`INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups)
    VALUES(?,?,?,?,?,?,?,?,?,?,?)`).run(
    newVer, wf.id, 'opencode', ts, ts, JSON.stringify(newNodes), JSON.stringify(nc),
    'v' + wf.versionCounter, 0, 'WF4: fixed field mismatches + normalize + msg set nodes (v2)', '[]'
  );
  db.prepare('UPDATE workflow_entity SET activeVersionId=?,versionCounter=?,updatedAt=? WHERE id=?').run(newVer, wf.versionCounter+1, ts, wf.id);
  console.log('WF4 done. verId=' + newVer + ' counter=' + (wf.versionCounter+1));
})();

console.log('All done.');
db.close();
