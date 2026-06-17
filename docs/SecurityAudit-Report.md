# Security Audit Report — TuAhora MVP

**Fecha:** 16 Junio 2026
**Alcance:** Stack completo (scheduler, landing, admin PHP, n8n, OpenWA, Docker)

---

## 🔴 Critical Risks

### 1. AGENTS.md contiene credenciales en texto plano
**Evidencia:** `AGENTS.md:34,75,95`
- Admin password: `admin2024`
- OpenWA session ID: `5d81145b-eb81-4fb9-82e3-ab1b1ed5ad6d`
- OpenWA API key: `dev-admin-key`
- n8n owner email: `godoy97@gmail.com`

**Riesgo:** Si AGENTS.md se commitea (actualmente untracked), todas las credenciales quedan expuestas en el histórico de git. Es irreversible sin reescribir el historial.

**Fix:** Borrar credenciales de AGENTS.md antes del primer commit. O usar placeholders.

---

### 2. Scheduler API key con default en producción (`dev-admin-key`)
**Evidencia:** `scheduler/src/index.js:32`, `scheduler/src/routes/whatsapp.js:5`
```javascript
'X-API-Key': process.env.OPENWA_API_KEY || 'dev-admin-key'
```

**Riesgo:** Si la variable de entorno no está configurada en producción, se usa el default `dev-admin-key` que es público (está en AGENTS.md y en el repo). Cualquiera que conozca esta key puede acceder a OpenWA.

**Fix:** Remover defaults. Las variables de entorno deben ser obligatorias, con fallback de error explícito si faltan.

---

### 3. Admin password en `config.json` accesible vía web
**Evidencia:** `landing-salon/config.json:2`
```json
"password": "admin2024"
```

**Riesgo:** El archivo `config.json` se sirve vía HTTP en `localhost:8080/config.json`. Aunque el sync al landing lo limpia, el archivo original en el admin es accesible si se expone el PHP.

**Fix:** Mover el password a `.env` exclusivamente. No guardarlo en `config.json`.

---

## 🟠 Suspicious Findings

### 4. Rutas públicas sin autenticación
**Evidencia:** `scheduler/src/auth.js:3-12`

Endpoints públicos (sin API key):
- `GET /api/v1/services` — lista de servicios
- `GET /api/v1/slots`, `GET /api/v1/availabilities` — horarios disponibles
- `GET /api/v1/customers` — **todos los datos de clientes**
- `POST /api/v1/customers` — crear cliente
- `GET /api/v1/appointments` — **todos los turnos, con datos de clientes**
- `POST /api/v1/appointments` — crear turno
- `POST /api/v1/whatsapp/send` — enviar WhatsApp
- `GET /api/v1/appointments/:id/cancel` — cancelar turno

**Riesgo:** Cualquiera puede:
- Leer la base de clientes completa (nombres, teléfonos)
- Leer el historial de turnos
- Cancelar turnos ajenos
- Enviar mensajes de WhatsApp arbitrarios

**Fix:** Solo los endpoints estrictamente necesarios deben ser públicos. GET/customers y GET/appointments requieren API key. El endpoint whatsapp/send debe requerir token interno (solo n8n).

---

### 5. CORS allow-origin: `*`
**Evidencia:** `scheduler/src/index.js:15`
```javascript
app.use(cors());
```

Por defecto, `cors()` permite cualquier origen (`Access-Control-Allow-Origin: *`).

**Riesgo:** Cualquier sitio web puede hacer requests al scheduler desde el navegador del usuario, leyendo datos de clientes o creando turnos falsos.

**Fix:** Restringir a `http://localhost:8080` en desarrollo. En producción, al dominio del landing.

---

### 6. n8n sin autenticación en webhooks
**Evidencia:** `docker-compose.yml` — n8n expuesto en `:5678`, sin `N8N_AUTH_EXCLUDE_ENDPOINTS` ni autenticación en webhooks.

**Riesgo:** Los webhooks de n8n (`/webhook/whatsapp-cancelacion`, `/webhook/appointment-created`) aceptan requests sin validación. Un atacante podría simular mensajes de WhatsApp o crear/cancelar turnos.

**Fix:** Agregar `N8N_WEBHOOK_TOKEN` como header requerido en todos los webhooks. Validar en los workflows.

---

### 7. OpenWA expuesto al host con API key de desarrollo
**Evidencia:** `docker-compose.yml:119-120`
```
ports:
  - "2785:2785"
```

**Riesgo:** OpenWA está expuesto en `localhost:2785` (y en red si el firewall lo permite). La API key `dev-admin-key` permite control total de la sesión de WhatsApp (enviar mensajes, leer chats).

**Fix:** Exponer solo en producción si es necesario (Cloudflare Tunnel). Usar API key fuerte.

---

## 🟡 Low Risk / Observations

### 8. Scheduler expuesto en `localhost:3000`
Puerto expuesto directamente. En producción debería estar detrás de reverse proxy.

### 9. n8n expuesto en `localhost:5678`
Consola de administración accesible localmente. En producción, proteger con autenticación.

### 10. Configs con datos de desarrollo
`config.json`, `.env`, `docker-compose.yml` contienen datos reales de WhatsApp, nombres, direcciones. En producción, usar valores de staging/producción separados.

### 11. Dependencias mínimas
Scheduler solo usa `express` y `cors` (2 dependencias). Riesgo de supply chain bajo. Verificar `npm audit` antes de deploy.

---

## 🚨 Indicators of Malicious Intent

- No se detectaron indicadores de código malicioso.
- Las dependencias son mínimas y conocidas.
- No hay ofuscación ni ejecución remota de código sospechosa.
- El proyecto es autocontenido y no exfiltra datos.

---

## 🧾 Final Verdict

**SAFE TO RUN** (en desarrollo local)

El stack es seguro para desarrollo local. Antes de producción, resolver los 🔴 Critical Risks.

---

## 🔍 Manual Review Checklist

- [ ] 🔴 Remover credenciales de AGENTS.md antes de commit
- [ ] 🔴 Eliminar defaults hardcodeados (`dev-admin-key`, `admin2024`) en código
- [ ] 🔴 Mover admin password de `config.json` a `.env` exclusivamente
- [ ] 🟠 Restringir rutas públicas a las estrictamente necesarias
- [ ] 🟠 Configurar CORS con origen específico
- [ ] 🟠 Validar webhook token en workflows n8n
- [ ] 🟡 Cerrar puertos innecesarios en prod (3000, 2785, 5678)
- [ ] 🟡 Separar configs dev/prod
