# WF-4: Reagendado por WhatsApp

**Estado:** ✅ End-to-end confirmado (14 Junio 2026)

**Trigger:** Webhook `GET,POST` `/webhook/whatsapp` (mensajes entrantes de OpenWA, `responseMode: "responseNode"`, tiene Respond to Webhook node)

## Lógica

1. Detectar "CAMBIAR" o "REAGENDAR"
2. Buscar turno activo (mismo flujo que WF-3)
3. Cancelar turno actual vía PHP relay (PUT con `status: cancelled`)
4. Responder con link del booking público para reagendar

## Nodos clave y fixes (14 Junio)

### Normalize node
OpenWA envuelve el payload en `data`. Se normaliza usando:
- `$json.body.data.from` → número de teléfono entrante
- `$json.body.data.body` → texto del mensaje

### Filter node
- Busca **todos** los customer IDs que matchean (no solo el primero)
- Usa `Number()` coercion para comparar IDs numéricos
- Detecta keywords "CAMBIAR" o "REAGENDAR"

### Cancel node
- Usa PHP relay: GET a `/tuahora/api/cancel-appointment.php?id=X`
- Ver [[CancelRelay]] para detalles del endpoint

### Webhook
- `httpMethod: "GET,POST"`, `responseMode: "responseNode"`
- Incluye nodo Respond to Webhook al final del flujo

## Archivo

`E:\TUAHORA\n8n-workflows\WF4-reagendado.json`

## Dependencias

- [[EasyAppointments]] — API de turnos
- [[OpenWA]] — Recepción y envío de WhatsApp
- [[CancelRelay]] — Endpoint PHP para cancelación
