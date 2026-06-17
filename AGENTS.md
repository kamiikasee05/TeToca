# Project Documentation Workflow

This project uses Obsidian for documentation. The vault is at `E:\TUAHORA`.

## Rules

- All significant changes, architectural decisions, and progress must be documented in `docs/` as Obsidian markdown notes.
- Use wiki links `[[NoteName]]` to interconnect related documentation.
- Update `docs/README.md` as the index/toc when adding new notes.
- Update `docs/Arquitectura.md` when the system architecture changes.
- Progress tracking: `docs/EstadoProyecto.md`, `docs/Roadmap.md`.
- Each component (EasyAppointments, Baileys, n8n, etc.) has its own note in `docs/`.
- Each n8n workflow has its own note: `docs/WF1-Confirmacion.md` through `docs/WF4-Reagendado.md`.

## Security audits

- Run the `code-security-auditor` skill after every major change: new component, new dependency, auth changes, API exposure, infra changes, or before production deploy.
- Review findings in `docs/SecurityAudit-Report.md`. Critical (🔴) findings must be resolved before deploy.
- Update `docs/SecurityAudit-Plan.md` when audit criteria changes.

## Documentation-first approach

Before starting any task, check `docs/` for existing context. After completing a task, update the relevant documentation.

## What did we do so far?

### MVP COMPLETO — 2026-06-16 (closing session)

#### Stack final
| Service | Port | Status |
|---|---|---|
| Landing (Nginx + SPA) | :8080 | ✅ |
| Admin panel (PHP + GD) | :8081 | ✅ Dashboard operativo |
| Scheduler API (Node + SQLite) | :3000 | ✅ |
| OpenWA (WhatsApp via Puppeteer) | :2785 (exposed) | ✅ connected |
| n8n (workflow engine) | :5678 | ✅ 7 workflows end-to-end |
| Redis | internal | ✅ |
| Mailpit | internal | ✅ |

#### Scheduler API — motor de reservas
- **SQLite database**, migrated from EasyAppointments+MySQL (retired 15 Jun).
- **Endpoints**: customers CRUD, services CRUD, appointments CRUD, availabilities, webhooks.
- **WhatsApp proxy**: `GET/POST /api/v1/whatsapp/send?phone=...&message=...` — inline handler before auth middleware, proxies to OpenWA.
- **Webhooks**: fires POST to n8n on `appointment-created`, `appointment-cancelled`, `appointment-rescheduled` with full payload (customer, service, provider, address).
- **`getFullAppointment()`**: joined SELECT for complete payload on cancel/reschedule webhooks.
- **Public routes**: `POST /customers`, `POST /appointments`, `GET /appointments?status=confirmed&customer_id=X`, `GET /appointments/:id/cancel`.
- **Query params**: `hash`, `customer_id`, `status` filters on GET /appointments.
- **`address` field**: added to provider_settings and webhook payload.

#### 7 Workflows n8n — todos funcionales

| Workflow | Tipo | Descripción | Estado |
|---|---|---|---|
| WF-RT | Outbound | Confirmación en tiempo real vía webhook `appointment-created` | ✅ |
| WF-1 | Outbound | Confirmación 24h antes (cron) | ✅ |
| WF-2 | Outbound | Recordatorio diario 21:00 ART (cron) | ✅ |
| WF-3 | Inbound | "CANCELAR" vía WhatsApp → cancela turno | ✅ v3 |
| WF-4 | Inbound | "CAMBIAR/REAGENDAR" vía WhatsApp → cancela + link reagendado | ✅ |
| WF-5 | Outbound | Notificación cancelación vía webhook `appointment-cancelled` | ✅ |
| WF-6 | Outbound | Notificación reagendado vía webhook `appointment-rescheduled` | ✅ |

#### WF-3 v3 — Cancelación inbound (breakthrough)
- **Problema original**: WhatsApp Web en dispositivos vinculados usa LID (`300815528157@lid`), NO número de teléfono. Buscar cliente por `from` era imposible.
- **Solución v3 (Code-based router)**: usuario escribe "CANCELAR" → webhook recibe mensaje → normaliza texto → detecta "CANCELAR" → busca TODOS los appointments confirmados → Code node cuenta resultados:
  - 1 turno → cancela directo + notifica "Turno cancelado"
  - 2+ turnos → envía lista numerada para que elija
  - 0 turnos → "No tenés turnos activos"
- No requiere hash ni número de teléfono. Simple y robusto.

#### Dashboard admin (PHP + GD, puerto :8081)
- **GD library**: instalada vía Dockerfile (`libpng-dev libjpeg-dev`, `--with-gd --with-jpeg --with-png`).
- **Logo upload**: renderiza en navbar (left) + hero (centered), 200px max-width. PNG + JPEG.
- **Gallery upload**: múltiples imágenes PNG/JPEG, mostradas en tab gallery.
- **Branding config sync**: `admin/save-branding.php` escribe a `landing-salon/config.json` (admin) Y `landing/config.json` (landing pública, password stripped).
- **Services CRUD**: create/read/update/delete vía scheduler API.
- **Appointments management**: view/edit/delete desde dashboard.
- **Credentials**: `admin` / `admin2024`.

#### Landing pública (Nginx + vanilla JS SPA, puerto :8080)
- Formulario de reserva 3 pasos: servicio → fecha/hora → datos.
- Mobile-first, colores desde `config.json`.
- Llama directo a scheduler API (`POST /customers`, `POST /appointments`).

#### OpenWA — WhatsApp via Puppeteer
- Session ID: `5d81145b-eb81-4fb9-82e3-ab1b1ed5ad6d`
- Phone: `5493826403110`
- API Key: `dev-admin-key`
- **Lección clave**: WhatsApp Web vinculado reporta `from` como LID (`XXXXXXXXX@lid`), NO como número de teléfono. No se puede buscar clientes por `from` en dispositivos vinculados.
- **Webhooks configurados**: evento `message.received` (no `message` ni `message.create`).
- **Sesión**: persiste en volumen `openwa_data`. Reconexión sin QR si sesión válida. Si se corrompe (WSL restart → `SingletonLock`), eliminar `/app/data/sessions/session-tuahora/` y recrear.

#### Seguridad — Auditoría completa
- **Veredicto**: 🟡 MEDIUM RISK — 3 críticos, 4 sospechosos, 4 observaciones.
- Reporte completo en [[SecurityAudit-Report]].
- Críticos deben resolverse antes de deploy productivo (Etapa 5).

#### Etapas del roadmap
- ✅ Etapa 1 — Visual/Landing
- ✅ Etapa 2 — Config (servicios y horarios)
- ✅ Etapa 3 — WhatsApp automation (7/7 workflows)
- ✅ Etapa 4 — Artefactos de negocio
- ⏳ Etapa 5 — Infraestructura productiva (solo si hay cliente que paga)

### Key decisions
- **n8n HTTP node v4.2 bug**: refuses to send POST when body/query params are set. Workaround: use GET with query params.
- **Scheduler as WhatsApp proxy**: instead of n8n calling OpenWA directly (required `require('http')` which is blocked in Code node sandbox, and HTTP Request node was unreliable), the scheduler proxies the call. Simple, testable, works.
- **WhatsApp session recreated**: after WSL restart, Chromium profile lock files corrupted. Deleting `/app/data/sessions/session-tuahora/` Singleton* files and recreating the session fixed it.
- **WF-3 LID breakthrough**: WhatsApp Web linked devices report `from` as LID (`300815528157@lid`), NOT phone number. Searching customers by `from` is impossible. Solution: search ALL confirmed appointments, use Code node to route based on count (1=direct cancel, 2+=numbered list, 0=no appointments). No hash or phone required.
- **OpenWA webhook event**: must be `message.received`, not `message` or `message.create`.
- **$(Normalize) doesn't work**: n8n URL expressions don't support `$()` syntax — use Code node for phone normalization instead.

### Current stack
| Service | Port | Status |
|---|---|---|
| Landing (Nginx + SPA) | :8080 | ✅ |
| Admin panel (PHP + GD) | :8081 | ✅ Dashboard operativo |
| Scheduler API | :3000 | ✅ |
| OpenWA (WhatsApp) | :2785 (exposed) | ✅ connected |
| n8n | :5678 | ✅ 7 workflows — MVP COMPLETO |
| Redis | internal | ✅ |
| Mailpit | internal | ✅ |

### OpenWA session
- Session ID: `5d81145b-eb81-4fb9-82e3-ab1b1ed5ad6d`
- Phone: `5493826403110`
- API Key: `dev-admin-key`

### WF-RT: Confirmación en tiempo real
1. Webhook `appointment-created` — scheduler fires POST to `n8n:5678/webhook/appointment-created`
2. Code "Extraer datos" — extracts phone (normalizes to 549), client name, service, date, time, location
3. Set "Formatear mensaje" — builds WhatsApp text with appointment details
4. HTTP Request "Enviar WhatsApp" — GET `http://scheduler:3000/api/v1/whatsapp/send?phone=...&message=...`
5. Respond to Webhook — `{status: "ok", processed: true}`

### Additional fixes (late night session)
- **Professional name in messages**: Added `profesional` column to `provider_settings` table. WF-RT confirmation now shows "Profesional: Cecilia Natali Godoy" instead of old "Laura". The name comes from the scheduler DB (synced with brand config).
- **Landing phone validation**: Added real-time phone normalization to booking form. Shows preview "Se enviará como: 549XXXXXXXXXX". Strips 0, 15, non-digits. Validates minimum length.
- **Landing brand integration**: Google Maps link now dynamic (uses brand address from config). Hero shows professional name ("con Cecilia Natali Godoy"). Admin Marca tab has "Nombre del profesional" field.
- **Cancel message cleanup**: Removed redundant text from WF-5 cancel notification. Now says "Reserva un nuevo turno desde la web." instead of two redundant sentences.
- **WF-3 hash-based cancel → simplified**: Removed hash requirement. User just types CANCELAR. Workflow searches ALL active confirmed appointments, if exactly 1 → cancels directly. If 2+ → sends numbered list.
- **Key discovery**: WhatsApp Web linked devices use LID (`300815528157@lid`) not phone numbers in `from` field. Customer search by phone impossible. Solution: search all appointments, cancel by count.
