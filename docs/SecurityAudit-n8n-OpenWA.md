# Security Audit — n8n Workflows & OpenWA Integration

**Fecha:** 18 Junio 2026  
**Alcance:** n8n workflows, OpenWA/WhatsApp integration, webhook endpoints, API exposure  
**Stack auditado:** scheduler (Node/Express), n8n v2.26.3, OpenWA, landing nginx proxy, admin PHP

---

## Resumen

| Criticidad | Cantidad |
|-----------|----------|
| 🔴 Crítico | 5 |
| 🟠 Alto | 6 |
| 🟡 Medio | 4 |

El stack actual tiene **mejoras significativas** respecto a la auditoría anterior (CORS ya no es wildcard, `.env.example` existe, `.gitignore` cubre configs y backups). Sin embargo, persisten problemas graves de exposición de PII en endpoints públicos, falta de verificación de propiedad en cancelaciones, y **credenciales reales trackeadas en git** dentro de `AGENTS.md` y `docs/`.

---

## 🔴 Critical Risks

### CR-N1: AGENTS.md y docs/ con credenciales reales trackeadas en git

**Evidencia:**
- `AGENTS.md:34,75,95` — admin password `admin2024`, session ID `5d81145b-...`, API key `dev-admin-key`, email `godoy97@gmail.com`
- `docs/n8n.md:11-12` — email `godoy97@gmail.com`, password `150588-reg`
- `docs/WF1-Confirmacion.md:40-42` — session ID, phone `5493826403110`, API key `dev-admin-key`
- `docs/OpenWA.md:48` — `X-Api-Key: tuahora_openwa_2024`
- `docs/SecurityAudit-Report.md:12-14` — todas las credenciales listadas

**Estado git:** `AGENTS.md` y `docs/` están **trackeados** (`git ls-files` confirma). Ya hay modificaciones pendientes de commit. Si se hace push, las credenciales quedan en el historial de git para siempre.

**Riesgo:** Filtración irreversible de credenciales. Cualquiera con acceso al repo (público o privado comprometido) obtiene control total del stack.

**Fix:** 
1. Agregar `AGENTS.md` a `.gitignore` **inmediatamente**
2. Agregar `docs/WF1-Confirmacion.md` a `.gitignore` (contiene credenciales)
3. Sanitizar el resto de docs (reemplazar valores reales por placeholders)
4. Si ya se commiteó a un remote, regenerar TODAS las credenciales y hacer `git filter-branch` o aceptar que están comprometidas

---

### CR-N2: Exposición de PII de clientes en endpoints públicos

**Evidencia:** `scheduler/src/auth.js:7,9`

```javascript
// auth.js:7 — público
{ method: 'GET', pattern: /^\/api\/v1\/customers/ },
// auth.js:9 — público  
{ method: 'GET', pattern: /^\/api\/v1\/appointments/ },
```

- `GET /api/v1/customers` → `scheduler/src/routes/customers.js:4-14` devuelve TODOS los clientes con `id, firstName, lastName, email, phone`. Sin paginación. Sin filtro de auth.
- `GET /api/v1/appointments` → `scheduler/src/routes/appointments.js:5-41` devuelve TODOS los turnos con JOIN a customers: `c_first_name, c_last_name, c_email, c_phone` (líneas 8-10). Sin restricción de `hash` o `customer_id` obligatorio.

**Riesgo:** Cualquiera con acceso a `http://localhost/api/v1/customers` (vía nginx port 80, o port 3000 directo) obtiene la base completa de clientes con teléfonos y emails.

**Fix:** 
- `GET /customers` debe requerir API key (sacarlo de `publicRoutes`)
- `GET /appointments` debe requerir API key O filtrar por `hash`/`customer_id` y nunca devolver datos de otros clientes
- La landing page solo necesita `POST /customers` (crear) y `GET /appointments?customer_id=X&hash=Y` (consultar turnos propios). El resto debe ser authenticated.

---

### CR-N3: Cancelación de turnos sin verificación de propiedad

**Evidencia:** `scheduler/src/routes/appointments.js:43-51`

```javascript
// Línea 43: ruta pública (auth.js:12)
router.get('/appointments/:id/cancel', (req, res) => {
    // No verifica hash, customer_id ni ningún token
    db.prepare('UPDATE appointments SET status = ? WHERE id = ?')
      .run('cancelled', +req.params.id);
    // Línea 50: devuelve phone del cliente en la respuesta
    res.json({ ..., phone: '549' + (full.customer?.phone || ...) });
});
```

**Riesgo:** 
1. Un atacante puede iterar IDs (1, 2, 3...) y cancelar **todos** los turnos
2. La respuesta incluye el teléfono del cliente (línea 50), filtrando PII
3. El endpoint es GET (no POST), trivial de explotar con un navegador o script

**Fix:**
- Requerir `hash` como query param obligatorio y verificar que coincide con el appointment
- O requerir `customer_id` + `hash`
- Cambiar a POST/DELETE
- No devolver phone en la respuesta de cancelación pública

---

### CR-N4: Webhooks scheduler→n8n sin token de autenticación

**Evidencia:** `scheduler/src/webhooks.js:6-18`

```javascript
// No incluye ningún header de autenticación
const req = client.request(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', ... },
    timeout: 3000,
});
```

**Contexto:** `docker-compose.yml:97` define `N8N_WEBHOOK_TOKEN` como variable de entorno para n8n, PERO:
1. No es una variable estándar de n8n (la estándar es `N8N_AUTH_EXCLUDE_ENDPOINTS` o `N8N_WEBHOOK_AUTH_TOKEN`)
2. El scheduler **no envía** este token al llamar a los webhooks
3. Los workflows n8n no validan el token (no hay header auth configurado en los webhook nodes)

**Webhooks afectados:** `/webhook/appointment-created`, `/webhook/appointment-cancelled`, `/webhook/appointment-rescheduled`

**Riesgo:** Aunque estos webhooks solo son accesibles desde la red Docker interna, si cualquier servicio en la red se compromete, puede disparar webhooks falsos (crear notificaciones de turnos falsos, cancelaciones falsas).

**Fix:**
- Agregar header `X-Webhook-Token` en `webhooks.js` al disparar webhooks
- Configurar autenticación `headerAuth` en los webhook nodes de n8n
- O usar la variable estándar de n8n `N8N_WEBHOOK_AUTH_TOKEN` (requiere n8n ≥1.92)

---

### CR-N5: WhatsApp proxy sin validación de formato de teléfono

**Evidencia:** `scheduler/src/routes/whatsapp.js:20-23`

```javascript
const { phone, message } = req.body || {};
if (!phone || !message) {
    return res.status(400).json({ success: false, message: 'phone y message requeridos' });
}
// Sin validación de formato del phone
```

**Agregado:** línea 29: `chatId: phone.includes('@c.us') ? phone : phone + '@c.us'`

No hay validación de que `phone` sea un número válido (ej: solo dígitos, longitud mínima). Se acepta cualquier string.

**Riesgo:** Si se combina con un SSRF o bypass de auth, un atacante podría enviar mensajes a números arbitrarios con contenido arbitrario. La ruta es pública (`auth.js:11`).

**Fix:**
- Validar que phone sean solo dígitos (con posible `+` inicial), longitud 8-15
- Este endpoint solo debería ser llamado por n8n (red interna). Agregar verificación de IP de origen o token interno
- Considerar sacarlo de las rutas públicas y que solo n8n lo llame con API key

---

## 🟠 High Risk

### H1: n8n webhook de WhatsApp (`whatsapp-cancelacion`) sin verificación de origen

**Evidencia:** `n8n-workflows/WF3-cancelacion.json:11-26`

```json
"parameters": {
    "path": "whatsapp-cancelacion",
    "responseMode": "lastNode",
    "httpMethod": "GET,POST"
}
```

No tiene `authentication` configurado. El webhook acepta requests sin verificar el origen.

**Configuración en OpenWA:** `docs/OpenWA.md:105` sugiere configurar OpenWA para enviar mensajes entrantes a `http://tuahora_n8n:5678/webhook/whatsapp`. OpenWA no envía ningún token en el webhook.

**Riesgo:** Si se expone el puerto 5678 de n8n (actualmente solo accesible desde Docker network), un atacante podría enviar mensajes falsos de "CANCELAR" para cancelar turnos.

**Fix:**
- Agregar `authentication: "headerAuth"` en el webhook node con validación del token
- OpenWA debe incluir `X-Webhook-Token` en sus webhooks (configurable en OpenWA API)
- Validar en un Code node que el `from` del mensaje coincide con el `customer.phone` antes de cancelar

---

### H2: Sin rate limiting en ningún endpoint

**Evidencia:** Ausencia total de rate limiting en:
- `scheduler/src/index.js` — sin `express-rate-limit`
- `landing/nginx.conf` — sin `limit_req` ni `limit_conn`  
- Admin PHP — sin contador de intentos

**Riesgo:** 
- Brute-force de API keys en endpoints protegidos
- Enumeration de IDs en `/appointments/:id/cancel`
- Denegación de servicio (DoS) a la API del scheduler
- Spam de creación de turnos falsos

**Fix:**
- Agregar `express-rate-limit` en scheduler (100 req/min para endpoints públicos, 1000 req/min para autenticados)
- Agregar `limit_req` en nginx para `/api/v1`
- Agregar rate limiting en admin login PHP

---

### H3: Scheduler y n8n expuestos directamente en puertos del host

**Evidencia:** `docker-compose.yml:19-20,86-87,113-114`

```yaml
scheduler:    ports: - "3000:3000"
n8n:          ports: - "5678:5678"
openwa:       ports: - "2785:2785"
```

El productivo (`docker-compose.prod.yml`) mantiene estos puertos expuestos.

**Riesgo:** En producción, la API del scheduler, la consola de n8n y la API de OpenWA están expuestas directamente al host (y potencialmente a internet si no hay firewall).

**Fix (prod):**
- Solo exponer el puerto 80 (landing nginx)
- Scheduler, n8n y OpenWA solo en red interna Docker
- Acceder a n8n admin vía túnel SSH o VPN
- Si se necesita acceso externo a n8n, usar autenticación fuerte + Cloudflare Tunnel

---

### H4: n8n admin sin autenticación en entorno actual

**Evidencia:** `docker-compose.yml:82-105`

No se configuran variables de autenticación de n8n:
- Sin `N8N_BASIC_AUTH_ACTIVE`
- Sin `N8N_BASIC_AUTH_USER` / `N8N_BASIC_AUTH_PASSWORD`
- Sin `N8N_USER_MANAGEMENT_DISABLED`

n8n v2.26.3 **requiere** user management. Si no se configura, puede usar el owner email para login.

El email y password del owner están en `docs/n8n.md:11-12` (`godoy97@gmail.com` / `150588-reg`).

**Riesgo:** Si alguien accede a `http://localhost:5678`, puede autenticarse con credenciales documentadas públicamente en el repo.

**Fix:**
- Cambiar el password del owner en n8n
- No documentar credenciales de n8n en archivos del repo
- Para producción: restringir acceso al puerto 5678

---

### H5: PUT y DELETE de appointments sin verificación en rutas "públicas"

**Evidencia:** `scheduler/src/auth.js` — solo GET /appointments y POST /appointments son públicas. PUT y DELETE requieren API key.

Esto es correcto en auth, PERO: PUT y DELETE en `appointments.js:104-150` disparan webhooks (`appointment-cancelled`, `appointment-rescheduled`) que notifican al cliente y a la dueña. Si un atacante obtiene la API key del scheduler, puede modificar/cancelar turnos y disparar notificaciones falsas.

**Riesgo:** Medio mientras la API key esté segura. Alto si la API key se filtra (está en `AGENTS.md`).

**Fix:**
- Rotar `SCHEDULER_API_KEY` inmediatamente (está expuesta en AGENTS.md)
- Agregar auditoría/logging de acciones PUT/DELETE

---

### H6: Scheduler expone datos de provider (profesional) sin auth

**Evidencia:** `scheduler/src/auth.js` — `providers` no está en la lista de rutas públicas. Requiere API key. **OK en auth.**

Pero `GET /api/v1/appointments` (público) hace JOIN con `provider_settings` (appointments.js:10) y expone `p_address` y `p_profesional`. Estos datos del profesional se filtran en cada consulta de appointments.

**Riesgo:** Bajo (son datos semi-públicos del negocio), pero es exposición innecesaria.

---

## 🟡 Medium Risk

### M1: Inconsistencia entre docs y código en whatsapp/send

**Evidencia:**
- `docs/WF1-Confirmacion.md:14` dice que n8n usa GET con query params
- `scheduler/src/routes/whatsapp.js:16` registra `router.post(...)` — solo acepta POST
- El código lee de `req.body` (no `req.query`)

Si n8n realmente envía GET, el endpoint no funciona (posible bug silencioso). Si n8n envía POST con query params, funciona pero es frágil.

**Fix:** Unificar: que n8n use POST con JSON body, y que el endpoint acepte solo POST con body.

---

### M2: .gitignore no cubre docs/ ni AGENTS.md

**Evidencia:** `.gitignore:1-48` — no hay entrada para `AGENTS.md` ni `docs/`. Ambos están trackeados en git y contienen credenciales.

**Fix:** 
- Agregar `AGENTS.md` a `.gitignore`
- Sanitizar docs/ antes de commit (reemplazar credenciales por placeholders)

---

### M3: info del profesional en DB inicial con datos placeholder

**Evidencia:** `scheduler/src/db.js:66,68,72`

```javascript
first_name TEXT DEFAULT 'Laura',
email TEXT DEFAULT '',
username TEXT DEFAULT 'laura',
```

La DB se inicializa con `first_name: 'Laura'` y `username: 'laura'`. Son placeholders que deberían actualizarse al configurar el sistema. No es un riesgo de seguridad directo pero indica datos de desarrollo en runtime.

---

### M4: n8n workflows referencian credenciales antiguas (EA)

**Evidencia:** `n8n-workflows/fix-workflows.js:4`

```javascript
const EA_CREDENTIAL = { id: 'g4JZy1RbupSK6sx9', name: 'EA Cred' };
```

Este script modifica la DB de n8n para reemplazar credenciales embebidas en URLs. La credencial `g4JZy1RbupSK6sx9` es una referencia interna de n8n, no directamente explotable, pero revela estructura interna.

**Fix:** Script solo para migración. Puede eliminarse una vez completada.

---

## Inventario de webhooks

| URL | Origen | Autenticación | Expuesto |
|-----|--------|---------------|----------|
| `n8n:5678/webhook/appointment-created` | scheduler webhooks.js | ❌ Sin token | Solo red Docker |
| `n8n:5678/webhook/appointment-cancelled` | scheduler webhooks.js | ❌ Sin token | Solo red Docker |
| `n8n:5678/webhook/appointment-rescheduled` | scheduler webhooks.js | ❌ Sin token | Solo red Docker |
| `n8n:5678/webhook/whatsapp-cancelacion` | OpenWA → n8n | ❌ Sin token | Solo red Docker |
| `n8n:5678/webhook/whatsapp-reagendado` | OpenWA → n8n | ❌ Sin token | Solo red Docker |
| `scheduler:3000/api/v1/whatsapp/send` | n8n workflows | ❌ Ruta pública | Port 3000 + nginx :80 |

---

## Inventario de endpoints públicos (sin auth)

| Método | Ruta | Datos expuestos | auth.js ref |
|--------|------|-----------------|-------------|
| GET | `/api/v1/services` | Catálogo de servicios | Línea 4 |
| GET | `/api/v1/services/:id` | Detalle de servicio | Línea 4 |
| GET | `/api/v1/slots` | Horarios disponibles | Línea 5 |
| GET | `/api/v1/availabilities` | Horarios disponibles | Línea 6 |
| GET | `/api/v1/customers` | **Todos los clientes (PII)** | Línea 7 |
| GET | `/api/v1/customers/:id` | Cliente específico (PII) | Línea 7 |
| POST | `/api/v1/customers` | Crear cliente | Línea 8 |
| GET | `/api/v1/appointments` | **Todos los turnos + PII clientes** | Línea 9 |
| POST | `/api/v1/appointments` | Crear turno | Línea 10 |
| POST | `/api/v1/whatsapp/send` | Enviar WhatsApp | Línea 11 |
| GET | `/api/v1/appointments/:id/cancel` | Cancelar turno + filtrar phone | Línea 12 |

---

## Verificación rápida post-auditoría

```powershell
# 1. Verificar credenciales en archivos trackeados
git -C E:\TUAHORA ls-files | ForEach-Object {
    $content = Get-Content "E:\TUAHORA\$_" -Raw -ErrorAction SilentlyContinue
    if ($content -match 'admin2024|dev-admin-key|tuahora_openwa_2024|150588-reg|5d81145b-eb81-4fb9-82e3-ab1b1ed5ad6d') {
        Write-Host "CREDENCIAL EN: $_"
    }
}

# 2. Verificar endpoints públicos devolviendo PII
curl -s http://localhost/api/v1/customers | jq 'length'  # Si > 0, PII expuesto

# 3. Verificar cancelación sin auth
curl -s http://localhost/api/v1/appointments/1/cancel  # Si devuelve 200, vulnerable

# 4. Verificar acceso a n8n sin auth
curl -s -o /dev/null -w "%{http_code}" http://localhost:5678/rest/workflows
# 200 = accesible sin auth
```

---

## Relacionado

- [[SecurityAudit-Report]] — Auditoría anterior (2026-06-16)
- [[PropuestaSeguridad]] — Plan de remediación previo
- [[Arquitectura]] — Diagrama del stack
- [[n8n]] — Documentación de workflows
- [[OpenWA]] — Configuración WhatsApp
