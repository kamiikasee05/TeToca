# WF-3: Cancelación por WhatsApp

**Estado:** ⚠️ Flujo inbound (CANCELAR) funciona. Pendiente: notificación outbound por cancelación del sistema.

**Trigger:** Webhook `GET,POST` `/webhook/whatsapp` (mensajes entrantes de OpenWA, `responseMode: "responseNode"`, tiene Respond to Webhook node)

## Pendiente (16 Junio 2026)

- **Notificación de cancelación no enviada**: El webhook `appointment-cancelled` del scheduler dispara correctamente hacia n8n, pero n8n no está enviando la notificación por WhatsApp. Requiere investigación (¿falta un workflow que escuche ese webhook?).
- El flujo **inbound** (cliente envía "CANCELAR" por WhatsApp → se cancela el turno) funciona correctamente.

## Lógica

1. Detectar "CANCELAR" o "cancelar"
2. Buscar cliente en Easy!Appointments por teléfono
3. Obtener appointments del cliente
4. Filtrar turnos futuros
5. Si existe: cancelar vía PHP relay → confirmar por WA → notificar a la dueña
6. Si no existe: responder "No encontré ningún turno activo"

## Nodos clave y fixes (14 Junio)

### Normalize node
OpenWA envuelve el payload en `data`. Se normaliza usando:
- `$json.body.data.from` → número de teléfono entrante
- `$json.body.data.body` → texto del mensaje

### Filter node
- Busca **todos** los customer IDs que matchean (no solo el primero)
- Usa `Number()` coercion para comparar IDs numéricos
- Detecta keyword "CANCELAR" en el mensaje

### Cancel node
- Usa PHP relay: GET a `/tuahora/api/cancel-appointment.php?id=X`
- El relay hace curl PUT a EA API con `status: cancelled`
- Ver [[CancelRelay]] para detalles del endpoint

### Webhook
- `httpMethod: "GET,POST"`, `responseMode: "responseNode"`
- Incluye nodo Respond to Webhook al final del flujo

## Archivo

`E:\TUAHORA\n8n-workflows\WF3-cancelacion.json`

## Dependencias

- [[EasyAppointments]] — API de turnos
- [[OpenWA]] — Recepción y envío de WhatsApp
- [[CancelRelay]] — Endpoint PHP para cancelación
