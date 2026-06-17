const fs = require('fs');
const path = require('path');

// Read the exported workflow file
const filePath = path.join(process.env.TEMP || 'C:\\Temp', 'wf-rt-openwa.json');
let raw = fs.readFileSync(filePath, 'utf-16le');

// Strip any lines before the JSON array starts (like deprecation warnings)
const jsonStart = raw.indexOf('[');
if (jsonStart === -1) {
  console.error('ERROR: No JSON array found in file');
  process.exit(1);
}
raw = raw.substring(jsonStart);

let workflow;
try {
  workflow = JSON.parse(raw);
} catch (e) {
  console.error('ERROR: Failed to parse JSON:', e.message);
  process.exit(1);
}

// workflow is an array with one element (the workflow object)
const wf = Array.isArray(workflow) ? workflow[0] : workflow;

// Find the send-whatsapp node
const sendNode = wf.nodes.find(n => n.id === 'send-whatsapp' || n.name === 'Enviar WhatsApp');
if (!sendNode) {
  console.error('ERROR: send-whatsapp node not found');
  console.log('Available nodes:', wf.nodes.map(n => `${n.id} (${n.name})`).join(', '));
  process.exit(1);
}

console.log('Found node:', sendNode.id, sendNode.name);
console.log('Before modification:', JSON.stringify(sendNode.parameters, null, 2));

// Modify the node parameters
sendNode.parameters.requestMethod = 'POST';
sendNode.parameters.url = 'http://openwa:2785/api/sessions/6413d670-56d2-43c3-8b5c-1084cf6c2eb6/messages/send-text';
sendNode.parameters.sendQuery = false;
sendNode.parameters.sendBody = true;
sendNode.parameters.sendHeaders = true;
sendNode.parameters.bodyContentType = 'json';

// Remove old parameters that are no longer needed
delete sendNode.parameters.authentication;

// Add header parameters
sendNode.parameters.headerParameters = {
  parameters: [
    {
      name: 'Content-Type',
      value: 'application/json'
    },
    {
      name: 'X-API-Key',
      value: process.env.OPENWA_API_KEY || ''
    }
  ]
};

// Add body parameters
sendNode.parameters.bodyParameters = {
  parameters: [
    {
      name: 'chatId',
      value: '={{ $json.phone }}@c.us'
    },
    {
      name: 'text',
      value: '={{ $json.message }}'
    }
  ]
};

// Clean up any stale options
sendNode.parameters.options = {};

console.log('\nAfter modification:', JSON.stringify(sendNode.parameters, null, 2));

// Write the modified workflow back
const output = Array.isArray(workflow) ? JSON.stringify([wf], null, 2) : JSON.stringify(wf, null, 2);
fs.writeFileSync(filePath, output, 'utf-8');

console.log('\nSUCCESS: Workflow saved to', filePath);
