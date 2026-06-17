// Fix WF3 (cancelacion) and WF4 (reagendado) - field name mismatches
const Database = require('/usr/local/lib/node_modules/better-sqlite3');
const crypto = require('crypto');
const db = new Database('/home/node/.n8n/database.sqlite');

function uuid() { return crypto.randomUUID(); }
function now() { return new Date().toISOString().replace('T',' ').substring(0,23).replace('Z','000'); }

// ===================== WF3: Cancelacion =====================
(function() {
  const wf = db.prepare('SELECT * FROM workflow_entity WHERE id=?').get('wf3-cancelacion');
  const hist = db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get(wf.activeVersionId);
  const nodes = JSON.parse(hist.nodes);
  const conn = JSON.parse(hist.connections);
  const ts = now();

  // --- Normalize Set node ---
  const normalizeId = uuid();
  nodes.push({
    id: normalizeId,
    name: 'Normalize',
    type: 'n8n-nodes-base.set',
    typeVersion: 3.4,
    position: [350, 300],
    parameters: {
      mode: 'manual',
      keepAll: true,
      assignments: {
        assignments: [
          { id: uuid(), name: 'phone', value: '={{ ($json.from || "").replace(/@c\\.us$/, "") }}', type: 'string' },
          { id: uuid(), name: 'text', value: '={{ $json.body }}', type: 'string' }
        ]
      },
      options: {}
    }
  });

  // --- Fix IF: $json.text -> $json.body ---
  const ifNode = nodes.find(n => n.id === 'check-cancel');
  ifNode.parameters.conditions.string[0].value1 = '={{ $json.body }}';
  ifNode.parameters.options = { caseSensitive: false };

  // --- Set node: confirm-cancel message ---
  const setConfirmId = uuid();
  nodes.push({
    id: setConfirmId,
    name: 'Set Confirm Cancel Msg',
    type: 'n8n-nodes-base.set',
    typeVersion: 3.4,
    position: [1900, 300],
    parameters: {
      mode: 'manual',
      keepAll: false,
      assignments: {
        assignments: [
          { id: uuid(), name: 'phone', value: '={{ $("Normalize").first().json.phone }}', type: 'string' },
          { id: uuid(), name: 'message', value: 'Tu turno ha sido cancelado exitosamente. Para reagendar visita: https://ejemplo.com/reservar', type: 'string' }
        ]
      },
      options: {}
    }
  });

  // --- Set node: notify-owner message ---
  const setOwnerId = uuid();
  nodes.push({
    id: setOwnerId,
    name: 'Set Notify Owner Msg',
    type: 'n8n-nodes-base.set',
    typeVersion: 3.4,
    position: [2050, 300],
    parameters: {
      mode: 'manual',
      keepAll: false,
      assignments: {
        assignments: [
          { id: uuid(), name: 'phone', value: '={{ $("Normalize").first().json.phone }}', type: 'string' },
          { id: uuid(), name: 'message', value: 'Un cliente acaba de cancelar su turno', type: 'string' }
        ]
      },
      options: {}
    }
  });

  // --- Set node: no-booking message ---
  const setNoBookId = uuid();
  nodes.push({
    id: setNoBookId,
    name: 'Set No Booking Msg',
    type: 'n8n-nodes-base.set',
    typeVersion: 3.4,
    position: [1650, 550],
    parameters: {
      mode: 'manual',
      keepAll: false,
      assignments: {
        assignments: [
          { id: uuid(), name: 'phone', value: '={{ $("Normalize").first().json.phone }}', type: 'string' },
          { id: uuid(), name: 'message', value: 'No encontramos turnos activos para tu numero. Si necesitas ayuda, contactanos directamente.', type: 'string' }
        ]
      },
      options: {}
    }
  });

  // --- Rewrite all connections ---
  const newConn = {};

  newConn['Webhook WhatsApp'] = { main: [[{ node: 'Normalize', type: 'main', index: 0 }]] };
  newConn['Normalize'] = { main: [[{ node: '¿Contiene CANCELAR?', type: 'main', index: 0 }]] };
  // IF true branch -> find customer
  newConn['¿Contiene CANCELAR?'] = {
    main: [
      [{ node: 'Buscar cliente por teléfono', type: 'main', index: 0 }],
      []
    ]
  };
  newConn['Buscar cliente por teléfono'] = { main: [[{ node: '¿Cliente encontrado?', type: 'main', index: 0 }]] };
  // Customer found: true->appointments, false->no-booking msg
  newConn['¿Cliente encontrado?'] = {
    main: [
      [{ node: 'Buscar turnos del cliente', type: 'main', index: 0 }],
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }]
    ]
  };
  newConn['Buscar turnos del cliente'] = { main: [[{ node: 'Filtrar turnos futuros del cliente', type: 'main', index: 0 }]] };
  newConn['Filtrar turnos futuros del cliente'] = { main: [[{ node: '¿Turno futuro encontrado?', type: 'main', index: 0 }]] };
  // Future found: false->no-booking, true->delete
  newConn['¿Turno futuro encontrado?'] = {
    main: [
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }],
      [{ node: 'Cancelar turno en EA', type: 'main', index: 0 }]
    ]
  };
  newConn['Cancelar turno en EA'] = { main: [[{ node: 'Set Confirm Cancel Msg', type: 'main', index: 0 }]] };
  newConn['Set Confirm Cancel Msg'] = { main: [[{ node: 'Enviar confirmación de cancelación', type: 'main', index: 0 }]] };
  newConn['Enviar confirmación de cancelación'] = { main: [[{ node: 'Set Notify Owner Msg', type: 'main', index: 0 }]] };
  newConn['Set Notify Owner Msg'] = { main: [[{ node: 'Notificar cancelación a dueña', type: 'main', index: 0 }]] };
  newConn['Set No Booking Msg'] = { main: [[{ node: 'Responder sin turno activo', type: 'main', index: 0 }]] };

  // Write
  const newVer = uuid();
  db.prepare(`INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups)
    VALUES(?,?,?,?,?,?,?,?,?,?,?)`).run(
    newVer, wf.id, 'opencode', ts, ts, JSON.stringify(nodes), JSON.stringify(newConn),
    'v' + wf.versionCounter, 0, 'WF3: fixed field mismatches + normalize + msg set nodes', '[]'
  );
  db.prepare('UPDATE workflow_entity SET activeVersionId=?,versionCounter=?,updatedAt=? WHERE id=?').run(newVer, wf.versionCounter+1, ts, wf.id);
  console.log('WF3 done. verId=' + newVer + ' counter=' + (wf.versionCounter+1));
})();

// ===================== WF4: Reagendado =====================
(function() {
  const wf = db.prepare('SELECT * FROM workflow_entity WHERE id=?').get('wf4-reagendado');
  const hist = db.prepare('SELECT * FROM workflow_history WHERE versionId=?').get(wf.activeVersionId);
  const nodes = JSON.parse(hist.nodes);
  const conn = JSON.parse(hist.connections);
  const ts = now();

  // --- Normalize Set node ---
  const normalizeId = uuid();
  nodes.push({
    id: normalizeId,
    name: 'Normalize',
    type: 'n8n-nodes-base.set',
    typeVersion: 3.4,
    position: [350, 300],
    parameters: {
      mode: 'manual',
      keepAll: true,
      assignments: {
        assignments: [
          { id: uuid(), name: 'phone', value: '={{ ($json.from || "").replace(/@c\\.us$/, "") }}', type: 'string' },
          { id: uuid(), name: 'text', value: '={{ $json.body }}', type: 'string' }
        ]
      },
      options: {}
    }
  });

  // --- Fix IF: $json.text -> $json.body ---
  const ifNode = nodes.find(n => n.id === 'check-keywords');
  ifNode.parameters.conditions.string[0].value1 = '={{ $json.body }}';
  if (ifNode.parameters.conditionsUi) {
    ifNode.parameters.conditionsUi.and[0].conditions[0].leftValue = '={{ $json.body }}';
    ifNode.parameters.conditionsUi.and[1].conditions[0].leftValue = '={{ $json.body }}';
  }

  // --- Set node: send-link message ---
  const setLinkId = uuid();
  nodes.push({
    id: setLinkId,
    name: 'Set Send Link Msg',
    type: 'n8n-nodes-base.set',
    typeVersion: 3.4,
    position: [1900, 300],
    parameters: {
      mode: 'manual',
      keepAll: false,
      assignments: {
        assignments: [
          { id: uuid(), name: 'phone', value: '={{ $("Normalize").first().json.phone }}', type: 'string' },
          { id: uuid(), name: 'message', value: 'Tu turno ha sido cancelado. Por favor ingresa a nuestro sistema para reagendar un nuevo turno.', type: 'string' }
        ]
      },
      options: {}
    }
  });

  // --- Set node: no-booking message ---
  const setNoBookId = uuid();
  nodes.push({
    id: setNoBookId,
    name: 'Set No Booking Msg',
    type: 'n8n-nodes-base.set',
    typeVersion: 3.4,
    position: [1650, 550],
    parameters: {
      mode: 'manual',
      keepAll: false,
      assignments: {
        assignments: [
          { id: uuid(), name: 'phone', value: '={{ $("Normalize").first().json.phone }}', type: 'string' },
          { id: uuid(), name: 'message', value: 'No encontramos turnos activos para tu numero. Si necesitas ayuda, contactanos directamente.', type: 'string' }
        ]
      },
      options: {}
    }
  });

  // --- Rewrite all connections ---
  const newConn = {};

  newConn['Webhook WhatsApp'] = { main: [[{ node: 'Normalize', type: 'main', index: 0 }]] };
  newConn['Normalize'] = { main: [[{ node: '¿Contiene CAMBIAR o REAGENDAR?', type: 'main', index: 0 }]] };
  newConn['¿Contiene CAMBIAR o REAGENDAR?'] = {
    main: [
      [{ node: 'Buscar cliente por teléfono', type: 'main', index: 0 }],
      []
    ]
  };
  newConn['Buscar cliente por teléfono'] = { main: [[{ node: '¿Cliente encontrado?', type: 'main', index: 0 }]] };
  newConn['¿Cliente encontrado?'] = {
    main: [
      [{ node: 'Buscar turnos del cliente', type: 'main', index: 0 }],
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }]
    ]
  };
  newConn['Buscar turnos del cliente'] = { main: [[{ node: 'Filtrar turnos futuros del cliente', type: 'main', index: 0 }]] };
  newConn['Filtrar turnos futuros del cliente'] = { main: [[{ node: '¿Turno futuro encontrado?', type: 'main', index: 0 }]] };
  newConn['¿Turno futuro encontrado?'] = {
    main: [
      [{ node: 'Set No Booking Msg', type: 'main', index: 0 }],
      [{ node: 'Cancelar turno actual', type: 'main', index: 0 }]
    ]
  };
  newConn['Cancelar turno actual'] = { main: [[{ node: 'Set Send Link Msg', type: 'main', index: 0 }]] };
  newConn['Set Send Link Msg'] = { main: [[{ node: 'Enviar link de reserva', type: 'main', index: 0 }]] };
  newConn['Set No Booking Msg'] = { main: [[{ node: 'Responder sin turno activo', type: 'main', index: 0 }]] };

  // Write
  const newVer = uuid();
  db.prepare(`INSERT INTO workflow_history(versionId,workflowId,authors,createdAt,updatedAt,nodes,connections,name,autosaved,description,nodeGroups)
    VALUES(?,?,?,?,?,?,?,?,?,?,?)`).run(
    newVer, wf.id, 'opencode', ts, ts, JSON.stringify(nodes), JSON.stringify(newConn),
    'v' + wf.versionCounter, 0, 'WF4: fixed field mismatches + normalize + msg set nodes', '[]'
  );
  db.prepare('UPDATE workflow_entity SET activeVersionId=?,versionCounter=?,updatedAt=? WHERE id=?').run(newVer, wf.versionCounter+1, ts, wf.id);
  console.log('WF4 done. verId=' + newVer + ' counter=' + (wf.versionCounter+1));
})();

console.log('All done.');
db.close();
