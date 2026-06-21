# Estado del Proyecto

## 18 Junio 2026 — Security hardening 🛡️

**Hito:** Auditoría de seguridad completa (2ª pasada). 11 hallazgos críticos resueltos. Stack hardened para producción. Commits `4afd018` + `edb1660`.

**Fecha:** 18 Junio 2026
**Fase:** ✅ Etapa 1-4. ⏳ Etapa 5 — Infraestructura productiva (solo si hay cliente que paga).

### Fixes aplicados (security — 11 críticos)

| ID | Finding | Fix |
|---|---|---|
| CR-1 | `POST /whatsapp/send` público | Requiere API key (solo n8n) |
| CR-2 | `GET /customers`, `GET /appointments` públicos (PII leak) | Removidos de rutas públicas |
| CR-3 | `GET /appointments/:id/cancel` sin auth | Requiere API key |
| CR-4 | Stored XSS en admin dashboard | `esc()` en todos los templates JS |
| CR-5 | Rate limiter usaba IP de nginx | Ahora usa `X-Real-IP` header |
| CR-6 | `whatsapp-send.php` legacy sin auth | Archivo eliminado |
| CR-7 | Nginx servía config con password | Monta `landing/config.json` limpio |
| CR-9 | Admin PHP corría como root | Dockerfile: `USER app` (non-root) |
| CR-10 | Credenciales en AGENTS.md | Reemplazadas por refs a `.env` |
| CR-11 | Webhooks scheduler→n8n sin token | `X-Webhook-Token` header agregado |
| — | Security headers ausentes | `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy` |

### n8n workflow hardening
- 13 HTTP Request nodes ahora incluyen `x-api-key={{ $env.SCHEDULER_API_KEY }}` header
- 4 WF JSON exports convertidos UTF-16LE → UTF-8
- `n8n-workflows/add-auth-headers.js`: script utility para auto-agregar headers
- ⚠️ WF-RT, WF-5, WF-6 no están en `n8n-workflows/` — exportar manualmente desde n8n UI

### No-go / deferidos (con justificación)
- CR-8: ports en `0.0.0.0` → necesario para WSL2
- H-6: n8n `--no-sandbox` → requerido para Puppeteer en Docker
- H-7: Redis sin password → red interna, no expuesto
- H-9: WF-RT/5/6 sin exportar → creados ad-hoc en UI

Ver [[SecurityAudit-Report]] para detalle completo.

---

## 18 Junio 2026 — Deploy fixes (WSL2/Windows) 🛠️

**Hito:** Stack desplegado y operativo en entorno Windows/WSL2. Corregidos bugs de port binding, CRLF, API routing, env vars faltantes, y límites de upload. Commit `bdcae27`.

**Fecha:** 18 Junio 2026
**Fase:** ✅ Etapa 1-4. ⏳ Etapa 5 — Infraestructura productiva (solo si hay cliente que paga).

### Stack actual
| Service | Port | Status |
|---|---|---|
| Landing (Nginx + SPA) | :80 | ✅ — nginx proxies `/api/v1` → scheduler, `/admin` + `/api` → admin PHP |
| Admin panel (PHP + GD) | :8081 | ✅ — upload 20M, logout confiable, saveConfig sin is_writable |
| Scheduler API (Node + SQLite) | :3000 | ✅ — env vars completos (OPENWA_API_KEY, OPENWA_SESSION_ID) |
| OpenWA (WhatsApp via Puppeteer) | :2785 (exposed) | ✅ connected |
| n8n (workflow engine) | :5678 | ✅ 7 workflows end-to-end |
| Redis | internal | ✅ |
| Mailpit | internal | ✅ |

### Fixes aplicados (commit bdcae27)
- **Port binding WSL2:** binds cambiados de `127.0.0.1` a `0.0.0.0` en `docker-compose.prod.yml`
- **CRLF + bcrypt quoting:** `setup.sh` wrappea valores .env en single quotes + `sed -i 's/\r$//'`
- **API routing:** landing SPA cambió `localhost:3000` → `/api/v1` relativo; nginx.conf agregó `location /api/v1` proxy a `scheduler:3000` (antes del bloque `/api`)
- **Scheduler env vars:** agregados `OPENWA_API_KEY` y `OPENWA_SESSION_ID` en docker-compose.yml
- **saveConfig():** removido gate `is_writable()` (falsos negativos en Docker Windows), usa `@file_put_contents` directo
- **logout.php:** cookie clear explícito + `session_destroy()` para logout confiable vía proxy nginx
- **Dockerfile admin:** `upload_max_filesize` 2M→20M, `post_max_size` 8M→25M, `max_input_time` 15→60

Ver [[Sesion-2026-06-18]] para detalle completo de los fixes.

---

## 16 Junio 2026 — MVP COMPLETO (sesión de cierre) 🎉

**Hito:** MVP 100% operativo. 7/7 workflows n8n funcionales end-to-end. Dashboard admin completo. Landing pública operativa. Auditoría de seguridad completada. Stack reducido y estabilizado.

**Fecha:** 16 Junio 2026
**Fase:** ✅ Etapa 1. ✅ Etapa 2. ✅ Etapa 3. ✅ Etapa 4. ⏳ Etapa 5 — Infraestructura productiva (solo si hay cliente que paga).

### Stack (16 Jun — pre-deploy fixes)
| Service | Port | Status |
|---|---|---|
| Landing (Nginx + SPA) | :8080 | ✅ |
| Admin panel (PHP + GD) | :8081 | ✅ Dashboard operativo |
| Scheduler API (Node + SQLite) | :3000 | ✅ |
| OpenWA (WhatsApp via Puppeteer) | :2785 (exposed) | ✅ connected |
| n8n (workflow engine) | :5678 | ✅ 7 workflows end-to-end |
| Redis | internal | ✅ |
| Mailpit | internal | ✅ |

## Sesiones

- [[Sesion-2026-06-18]] — Deploy fixes + Security hardening: WSL2, CRLF, nginx routing, env vars, admin + 11 críticos resueltos (commits `bdcae27`, `4afd018`, `edb1660`)
- [[Sesion-2026-06-16]] — Landing migration PHP→Nginx static, OpenWA session recovery, WhatsApp proxy vía scheduler, 7 workflows end-to-end, WF-3 v3 con LID breakthrough, dashboard completo, auditoría de seguridad final
- [[Sesion-2026-06-15]] — n8n upgrade 1.92.0→2.26.3, EA+MySQL retirados, migración completada
- [[Sesion-2026-06-14]] — WF3/WF4 end-to-end debugging, PHP cancel relay, Docker Desktop estabilidad
- [[Sesion-2026-06-13]] — Landing page, dashboard merge, estabilización de workflows, OpenWA webhooks
- [[Sesion-2026-06-12]] — Configuración inicial, documentación en Obsidian, setup del vault

## Barras de progreso

- Infraestructura base: 100%
- Etapa 1 — Visual: 100%
- Etapa 2 — Config: 100%
- Etapa 3 — WhatsApp: 100%
- Etapa 4 — Negocio: 100%
- Etapa 5 — Infra. prod: 0%

## Estado de Workflows (7/7)

| Workflow | Tipo | Descripción | Estado |
|---|---|---|---|
| WF-RT | Outbound | Confirmación en tiempo real vía webhook `appointment-created` | ✅ |
| WF-1 | Outbound | Confirmación 24h antes (cron) | ✅ |
| WF-2 | Outbound | Recordatorio diario 21:00 ART (cron) | ✅ Verificado |
| WF-3 | Inbound | "CANCELAR" vía WhatsApp → cancela turno | ✅ v3 (Code-based router) |
| WF-4 | Inbound | "CAMBIAR/REAGENDAR" vía WhatsApp → cancela + link reagendado | ✅ |
| WF-5 | Outbound | Notificación cancelación vía webhook `appointment-cancelled` | ✅ |
| WF-6 | Outbound | Notificación reagendado vía webhook `appointment-rescheduled` | ✅ |

## Logros clave — 16 Junio 2026

### WF-3 v3 — Cancelación inbound (breakthrough)
- **Problema original**: WhatsApp Web en dispositivos vinculados usa LID (`300815528157@lid`), NO número de teléfono. Buscar cliente por `from` era imposible.
- **WF-3 reconstruido 3 veces**. Versión final v3: Code-based router.
- **Solución**: usuario escribe "CANCELAR" → webhook recibe mensaje → normaliza texto → detecta "CANCELAR" → busca TODOS los appointments confirmados → Code node cuenta: 1 turno = cancela directo, 2+ = lista numerada, 0 = "no tenés turnos".
- No requiere hash ni número de teléfono. Simple y robusto.

### WF-RT / WF-5 / WF-6 — Limpieza de mensajes
- Eliminadas expresiones de hash rotas de los mensajes de cancelación.
- Mensajes simplificados: "Para cancelar, responde CANCELAR a este mensaje".
- Corregidos quote mismatches que causaban errores "invalid syntax".

### Scheduler API — Motor de reservas
- **SQLite database**, migrado de EasyAppointments+MySQL (retirado 15 Jun).
- **Endpoints**: customers CRUD, services CRUD, appointments CRUD, availabilities, webhooks.
- **WhatsApp proxy**: `GET/POST /api/v1/whatsapp/send?phone=...&message=...` — inline handler before auth middleware, proxies to OpenWA.
- **Webhooks**: fires POST to n8n on `appointment-created`, `appointment-cancelled`, `appointment-rescheduled` con full payload (customer, service, provider, address).
- **`getFullAppointment()`**: joined SELECT para payload completo en webhooks de cancel/reagendado.
- **Query params**: `hash`, `customer_id`, `status` filters en GET /appointments.
- **Public routes**: `POST /customers`, `POST /appointments`, `GET /services`, `GET /availabilities`, `GET /slots`.
- **Auth-required routes**: `GET /customers`, `GET /appointments`, `GET /appointments/:id/cancel`, `POST /whatsapp/send` (requieren `X-API-Key` header).
- **`address` field**: agregado a provider_settings y al payload del webhook.

### OpenWA — WhatsApp via Puppeteer
- Session ID: configurada vía `.env` (`OPENWA_SESSION_ID`)
- Phone: configurado vía `.env` (`N8N_OWNER_PHONE`)
- API Key: configurada vía `.env` (`OPENWA_API_KEY`)
- **Lección clave**: WhatsApp Web vinculado reporta `from` como LID (`XXXXXXXXX@lid`), NO como número de teléfono. No se puede buscar clientes por `from` en dispositivos vinculados.
- **Webhook event**: debe ser `message.received`, no `message` ni `message.create`.
- **$('Normalize') no funciona**: n8n URL expressions no soportan sintaxis `$()` — usar Code node.
- **Sesión**: persiste en volumen `openwa_data`. Reconexión sin QR si sesión válida. Si se corrompe (WSL restart → `SingletonLock`), eliminar `/app/data/sessions/session-tuahora/` y recrear.

### Dashboard admin (PHP + GD, puerto :8081)
- **GD library**: instalada vía Dockerfile (`libpng-dev libjpeg-dev`, `--with-gd --with-jpeg --with-png`).
- **Logo upload**: renderiza en navbar (left) + hero (centered), 200px max-width. PNG + JPEG.
- **Gallery upload**: múltiples imágenes PNG/JPEG, mostradas en tab gallery.
- **Branding config sync**: `admin/save-branding.php` escribe a `landing-salon/config.json` (admin) Y `landing/config.json` (landing pública, password stripped).
- **Services CRUD**: create/read/update/delete vía scheduler API.
- **Appointments management**: view/edit/delete desde dashboard.
- **Credentials**: configuradas vía `.env` (hash bcrypt).

### Landing pública (Nginx + vanilla JS SPA, puerto :8080)
- Formulario de reserva 3 pasos: servicio → fecha/hora → datos.
- Mobile-first, colores desde `config.json`.
- Llama directo a scheduler API (`POST /customers`, `POST /appointments`).

## Seguridad — Auditorías

| Fecha | Veredicto | Críticos | Estado |
|---|---|---|---|
| 16 Jun 2026 | 🟡 MEDIUM RISK | 3 | Resueltos 18 Jun |
| 18 Jun 2026 | 🟢 HARDENED FOR PRODUCTION | 11 | ✅ 11 resueltos, 4 deferidos documentados |

Reporte completo en [[SecurityAudit-Report]].

## Cambios históricos

### 15 Junio 2026 — Migración completada
- ✅ n8n actualizado 1.92.0 → 2.26.3 con migración de BD exitosa. Login restaurado (godoy97@gmail.com)
- ✅ EasyAppointments + MySQL retirados del stack Docker
- ✅ tuahora-scheduler es el motor de reservas principal (scheduler:3000, interno)
- ✅ Datos migrados: 7 servicios, 17 clientes, turnos activos, config providers
- ✅ Volumen ea_mysql_data eliminado (~1.1GB liberados)
- ✅ .env limpiado de variables viejas de EA/MySQL
- ✅ WF-1, WF-2, WF-3, WF-4 migrados EA→Scheduler
- ✅ WF-RT creado — Webhook en tiempo real `appointment-created` del scheduler
- ✅ Bug slots corregido — `/availabilities` devolvía fechas completas, frontend esperaba solo horas

### Sesiones anteriores (12-14 Junio)
- Landing page: formulario de reserva 3 pasos, integración API, mobile-first
- Dashboard admin: fusión de tabs, personalización
- OpenWA: sesión persistente, webhooks configurados
- WF3: cancelación automática al recibir "CANCELAR"
- WF4: cancelación + link de reagendado al recibir "CAMBIAR"/"REAGENDAR"
- [[Baileys]] removido del stack, reemplazado por [[OpenWA]]

## Relacionado

- [[README|Volver al inicio]]
- [[Roadmap]]
- [[SecurityAudit-Report]]
- [[Arquitectura]]
