# Estado del Proyecto

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

- [[Sesion-2026-06-18]] — Deploy fixes: WSL2 port binding, CRLF, API routing nginx, env vars, admin fixes (commit `bdcae27`)
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
- **Public routes**: `POST /customers`, `POST /appointments`, `GET /appointments?status=confirmed&customer_id=X`, `GET /appointments/:id/cancel`.
- **`address` field**: agregado a provider_settings y al payload del webhook.

### OpenWA — WhatsApp via Puppeteer
- Session ID: `5d81145b-eb81-4fb9-82e3-ab1b1ed5ad6d`
- Phone: `5493826403110`
- API Key: `dev-admin-key`
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
- **Credentials**: `admin` / `admin2024`.

### Landing pública (Nginx + vanilla JS SPA, puerto :8080)
- Formulario de reserva 3 pasos: servicio → fecha/hora → datos.
- Mobile-first, colores desde `config.json`.
- Llama directo a scheduler API (`POST /customers`, `POST /appointments`).

## Seguridad — Auditoría final (2026-06-16)

**Veredicto:** 🟡 MEDIUM RISK — 3 críticos, 4 sospechosos, 4 observaciones.

| Categoría | Cantidad | Acción |
|---|---|---|
| 🔴 Críticos | 3 | Deben resolverse antes de deploy productivo (Etapa 5) |
| 🟠 Sospechosos | 4 | Revisar antes de producción |
| 🟡 Observaciones | 4 | Mejoras deseables |

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
