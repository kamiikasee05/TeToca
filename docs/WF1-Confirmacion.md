# WF-1: Confirmación Inmediata de Turno — HISTÓRICO

> ⚠️ n8n no corre en el stack actual (bare metal). WF-1 (confirmación 24h) está pendiente de migrar a cron del sistema.

**Estado:** ⏳ Pendiente migrar a cron del sistema

## WF-RT: Confirmación en tiempo real (primario)

**Trigger:** Webhook `appointment-created` — el scheduler dispara POST a `n8n:5678/webhook/appointment-created`

### Lógica

1. Webhook `appointment-created` recibe los datos del turno desde [[TuAhoraScheduler]]
2. Code "Extraer datos" — extrae phone, client name, service, date, time, location. Normaliza phone: elimina `+`, espacios, agrega prefijo `549`
3. Set "Formatear mensaje" — construye el texto WhatsApp con los detalles del turno
4. HTTP Request "Enviar WhatsApp" — GET `http://scheduler:3000/api/v1/whatsapp/send?phone=...&message=...` (usa el scheduler como proxy hacia OpenWA; ver nota abajo)
5. Respond to Webhook — `{status: "ok", processed: true}`

### Envío WhatsApp vía Scheduler Proxy

El HTTP Request node de n8n v4.2 tiene un bug: ignora `requestMethod: "POST"` cuando hay query parameters configurados y envía GET. Para sortear esto, se usa GET con query params hacia el endpoint `/api/v1/whatsapp/send` del scheduler, que acepta tanto GET como POST (`app.all()`) y proxyea la llamada a OpenWA.

El scheduler expone este handler **antes** del auth middleware, leyendo de `req.query` (GET) o `req.body` (POST), y reenviando a `http://openwa:2785/api/sendText`.

**Flujo completo:** appointment → webhook → n8n WF-RT → scheduler proxy → OpenWA → WhatsApp. Verificado 2 veces seguidas con `workflow.success`.

## WF-1: Polling cada 2 minutos (backup)

**Trigger:** Schedule cada 2 minutos (polling)

### Lógica

1. GET `/api/v1/appointments?sort=-id&length=1` al scheduler (con header `X-API-Key`)
2. Comparar ID con el último procesado
3. Si hay turno nuevo: extraer datos del turno (Code node para flat JSON objects)
4. Formatear mensaje de confirmación en español
5. GET `http://scheduler:3000/api/v1/whatsapp/send?phone=...&message=...` (mismo proxy que WF-RT)
6. Actualizar último ID procesado

## OpenWA Session

- Session ID: `5d81145b-eb81-4fb9-82e3-ab1b1ed5ad6d`
- Phone: `5493826403110`
- API Key: `dev-admin-key`

## Fixes aplicados

- Extract-data: migrado de Set node a Code node para manejar correctamente objetos JSON planos
- Prefijo `549` agregado automáticamente al phone del customer (normalización: strip `+`, espacios, agregar `549`)
- Envío WhatsApp migrado de llamada directa a OpenWA → proxy vía scheduler (`/api/v1/whatsapp/send`) por bug en HTTP Request node de n8n v4.2
- Sesión OpenWA recreada tras corrupción por WSL restart (archivos `SingletonLock` de Chromium)

## Archivo

`E:\TUAHORA\n8n-workflows\WF1-confirmacion.json`

## Dependencias

- [[TuAhoraScheduler]] — API de turnos + proxy WhatsApp
- [[OpenWA]] — Envío de WhatsApp (vía scheduler)
