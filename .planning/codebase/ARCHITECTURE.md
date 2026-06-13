<!-- refreshed: 2026-06-13 -->
# Architecture

**Analysis Date:** 2026-06-13

## System Overview

```text
┌───────────────────────────────────────────────────────────────────────────┐
│                           Presentation Layer                              │
│  `landing-salon/index.php` (public landing)                               │
│  `landing-salon/admin/dashboard.php` (admin SPA)                          │
├────────────────┬──────────────────┬─────────────────┬────────────────────┤
│  API Gateway   │  Orchestration   │   Messaging     │   Support          │
│  (PHP cURL     │  `n8n-workflows` │ `baileys-svc`   │ `scripts/`         │
│   proxies)     │   port :5678     │  port :3001     │  (health/backup)   │
│ `landing-salon │                  │ `openwa/`       │                    │
│  /api/*.php`   │                  │  port :2785     │                    │
└───────┬────────┴────────┬─────────┴──────┬──────────┴──────┬─────────────┘
        │                 │                │                 │
        ▼                 ▼                ▼                 ▼
┌───────────────────────────────────────────────────────────────────────────┐
│                      Docker Compose Stack (network: `stack`)              │
│  `easyappointments/docker-compose.yml`                                    │
├───────────────────────────────────────────────────────────────────────────┤
│  easyappointments  │  mysql:8.0   │  redis:7    │  n8n   │  mailpit       │
│  (Apache+PHP, :80) │  (persisted)  │  (cache)    │ (:5678)│  (:1025/:8025) │
└────────────────────┴──────────────┴─────────────┴────────┴────────────────┘
        │
        ▼
┌───────────────────────────────────────────────────────────────────────────┐
│  Easy!Appointments REST API (internal)                                    │
│  `http://localhost/index.php/api/v1/`                                     │
│  Endpoints: /services, /customers, /appointments, /providers/{id}         │
│  Auth: HTTP Basic (hardcoded credentials)                                 │
└───────────────────────────────────────────────────────────────────────────┘
```

## Component Responsibilities

| Component | Responsibility | File |
|-----------|----------------|------|
| Easy!Appointments | Appointment engine (CRUD services, customers, appointments, provider schedules) | `easyappointments/docker-compose.yml` (service), `easyappointments/Dockerfile` |
| Landing Page | Public-facing site: service listing, booking form, Easy!Appointments iframe embed | `landing-salon/index.php` |
| Admin Panel | Protected SPA: dashboard stats, services CRUD, working hours, calendar, appointment management, WhatsApp QR pairing | `landing-salon/admin/dashboard.php` |
| API Gateway (PHP) | Authenticated cURL proxies to Easy!Appointments internal API + WhatsApp relay | `landing-salon/api/*.php` (9 files) |
| n8n | Workflow automation: polls EA for new appointments, triggers WhatsApp messages, processes chatbot replies | `n8n-workflows/WF1-*.json` through `WF4-*.json` |
| Baileys Service | WhatsApp Web bot: QR auth, incoming message webhooks to n8n, outgoing message send API | `baileys-service/index.js` |
| OpenWA | Alternative WhatsApp API gateway (NestJS): full REST API for WhatsApp messaging, session management | `openwa/src/main.ts`, `openwa/src/app.module.ts` |
| Health Scripts | PowerShell monitoring: container health, endpoint checks, ntfy.sh alerts | `scripts/health-check.ps1`, `scripts/check-stack.ps1` |
| Backup Script | MySQL database backup | `scripts/backup-mysql.ps1` |
| Documentation | Obsidian vault with architecture, component docs, workflow details, security audits | `docs/*.md` (26 notes) |

## Pattern Overview

**Overall:** Docker Compose microservices with API Gateway pattern

**Key Characteristics:**
- All services communicate over a shared Docker network named `stack`
- The landing page PHP is volume-mounted INTO the Easy!Appointments Apache container (`../landing-salon:/var/www/html/tuahora`)
- PHP API gateway scripts use cURL to proxy authenticated requests to Easy!Appointments internal API
- n8n orchestrates the WhatsApp notification flow via polling (no webhooks from Easy!Appointments)
- Two alternative WhatsApp backends exist: **Baileys** (simple Node.js, legacy profile) and **OpenWA** (NestJS, default)
- No external identity provider; admin auth is session-based with hardcoded password

## Layers

**Presentation Layer:**
- Purpose: Renders HTML to end users (public clients and admin)
- Location: `landing-salon/index.php`, `landing-salon/admin/*.php`
- Contains: PHP + inline HTML/CSS/JS; all styling and scripting is inline (no build toolchain)
- Depends on: API Gateway layer (`landing-salon/api/*.php` via client-side fetch)
- Used by: Browser clients

**API Gateway Layer:**
- Purpose: Secures and simplifies access to the Easy!Appointments backend API; relays WhatsApp messages to OpenWA
- Location: `landing-salon/api/*.php`
- Contains: 9 PHP files, each a single-purpose endpoint
- Depends on: Easy!Appointments REST API (`localhost/index.php/api/v1/`), OpenWA API
- Used by: Landing page (client-side JS), Admin panel (client-side JS)

**Backend Engine (Easy!Appointments):**
- Purpose: Core appointment management (services, customers, appointments, providers/schedules)
- Location: Docker container, image `alextselegidis/easyappointments:latest`
- Contains: PHP CodeIgniter-based REST API at `http://localhost/index.php/api/v1/`
- Depends on: MySQL database
- Used by: API Gateway layer, n8n workflows

**Orchestration Layer (n8n):**
- Purpose: Automates notification flows and WhatsApp chatbot logic
- Location: `n8n-workflows/*.json` (4 workflow definitions)
- Contains: Workflow nodes (schedule triggers, HTTP requests, code nodes, conditional logic)
- Depends on: Easy!Appointments API, Baileys/OpenWA messaging
- Used by: Nothing (autonomous; triggered by schedule or webhook)

**Messaging Layer:**
- Purpose: Send/receive WhatsApp messages
- Location: `baileys-service/index.js` (legacy, port 3001), `openwa/src/` (primary, port 2785)
- Contains: Baileys is a single-file Node.js Express app; OpenWA is a full NestJS modular application
- Depends on: Redis (Baileys, optional), SQLite (OpenWA)
- Used by: n8n workflows, API Gateway layer, Admin panel (QR pairing)

**Operations Layer:**
- Purpose: Monitoring, health checks, backups
- Location: `scripts/*.ps1`
- Contains: PowerShell scripts for container health, endpoint checking, MySQL backup
- Depends on: Docker CLI, external services (ntfy.sh for alerts)
- Used by: System administrators (manual or cron/scheduled task)

## Data Flow

### Primary Booking Flow

1. Customer visits `landing-salon/index.php` — landing page loads, fetches services from Easy!Appointments via cURL (`landing-salon/index.php:2-11`)
2. Customer clicks "Reservar" — scrolls to embedded Easy!Appointments iframe or uses the custom booking form
3. Custom form submits to `landing-salon/api/crear-turno.php` — creates/finds customer, then creates appointment via EA API (`crear-turno.php:53-121`)
4. n8n WF1 (`WF1-confirmacion.json`) — polls Easy!Appointments every 120s, detects new appointment
5. n8n sends confirmation via WhatsApp using Baileys `/send-text` endpoint
6. n8n WF2 (`WF2-recordatorio.json`) — 24h before appointment, sends reminder message

### WhatsApp Chatbot Flow (Cancellation/Reschedule)

1. Customer sends WhatsApp message → Baileys receives it (`baileys-service/index.js:79-97`)
2. Baileys forwards to n8n webhook URL(s) as POST with `{ phone, text, from }`
3. n8n WF3 (`WF3-cancelacion.json`) or WF4 (`WF4-reagendado.json`) processes the message
4. n8n calls Easy!Appointments API to cancel or reschedule the appointment
5. n8n sends confirmation response back via Baileys

### Admin Management Flow

1. Admin logs in at `landing-salon/admin/index.php` — session-based auth with hardcoded password
2. Admin SPA (`dashboard.php`) loads 6-tab interface: Dashboard, Servicios, Horarios, Calendario, Turnos, WhatsApp
3. Each tab calls its respective API endpoint:
   - Servicios: `api/admin-servicios.php` → CRUD on EA `/services`
   - Horarios: `api/horarios-admin.php` → read/write provider working plan on EA `/providers/5`
   - Turnos/Calendario: `api/turnos-admin.php` → read/filter/reschedule/cancel EA `/appointments`
   - WhatsApp: `api/whatsapp-qr.php` → proxy to OpenWA QR endpoint

**State Management:**
- Admin session stored in PHP server-side session (`$_SESSION['tuahora_admin']`)
- All client state is in-memory JS variables (no client-side store/library)
- n8n uses `$workflow.staticData` for tracking last processed appointment ID
- Baileys uses multi-file auth state in `/app/auth` directory (volume mount)
- OpenWA persists sessions in SQLite (`/app/data/openwa.sqlite`)

## Key Abstractions

**PHP API Gateway Pattern:**
- Purpose: Each file in `landing-salon/api/` is a self-contained endpoint that proxies to the Easy!Appointments API
- Examples: `landing-salon/api/servicios.php`, `landing-salon/api/crear-turno.php`, `landing-salon/api/turnos-admin.php`
- Pattern: Receive request → validate inputs → cURL to EA API with Basic Auth → return JSON response
- Auth: Admin endpoints check `$_SESSION['tuahora_admin']`; public endpoints add CORS headers

**n8n Workflow Pattern:**
- Purpose: Each workflow is a self-contained JSON definition for one notification/automation task
- Examples: `n8n-workflows/WF1-confirmacion.json`, `n8n-workflows/WF3-cancelacion.json`
- Pattern: Trigger → fetch data → conditional logic → action (HTTP call to EA or Baileys)

**Engine Adapter Pattern (OpenWA):**
- Purpose: Pluggable WhatsApp engine backends (`whatsapp-web.js`, future engines)
- Location: `openwa/src/engine/adapters/`
- Pattern: Each engine implements a common interface; selected via `ENGINE_TYPE` env var

## Entry Points

**Public Landing:**
- Location: `landing-salon/index.php`
- Triggers: Browser HTTP GET request
- Responsibilities: Render landing page, fetch services from EA, provide booking UI, embed EA iframe

**Admin Panel:**
- Location: `landing-salon/admin/index.php` → `landing-salon/admin/dashboard.php`
- Triggers: Browser navigation, session-based auth
- Responsibilities: Full business management (services, schedules, appointments, WhatsApp pairing)

**Easy!Appointments REST API:**
- Location: Inside easyappointments container, paths like `/index.php/api/v1/appointments`
- Triggers: HTTP requests from PHP API gateway and n8n
- Responsibilities: All CRUD operations on services, customers, appointments, providers

**n8n Webhook (WhatsApp Incoming):**
- Location: `http://n8n:5678/webhook/whatsapp-cancelacion`, `http://n8n:5678/webhook/whatsapp-reagendado`
- Triggers: POST from Baileys service when WhatsApp message received
- Responsibilities: Process chatbot intents (cancel, reschedule)

**Baileys Service API:**
- Location: `baileys-service/index.js` — endpoints: `/health`, `/qr`, `/qr-image`, `/qr-page`, `/send-text`, `/send-reminder`
- Triggers: HTTP requests from PHP API, n8n, admin panel
- Responsibilities: WhatsApp connection management, message sending, incoming message forwarding

**OpenWA API:**
- Location: `openwa/src/main.ts` — NestJS app on port 2785, prefix `/api`, Swagger docs at `/api/docs`
- Triggers: HTTP requests with `X-API-Key` header
- Responsibilities: WhatsApp session management, message sending, QR generation

## Architectural Constraints

- **Single-provider assumption:** The system hardcodes `providerId=5` throughout (Laura, the sole nail technician). Adding multiple providers would require significant refactoring.
- **Hardcoded credentials:** Easy!Appointments API credentials (`kamiikasee:admin2024`) and admin password are hardcoded in multiple PHP files. No secrets manager is used.
- **No webhook from Easy!Appointments:** n8n cannot receive real-time appointment notifications; it instead polls EA every 2 minutes, introducing up to 2 minutes of notification delay.
- **Inline code base:** The landing and admin PHP files mix HTML, CSS, JS, and PHP in single files. No build/transpilation pipeline exists for the frontend.
- **Co-hosted Apache:** The `landing-salon/` directory is volume-mounted into the Easy!Appointments Apache container, meaning both EA and the custom UI share the same web server process.
- **Dual WhatsApp backends:** Baileys is marked `profiles: [legacy]` in docker-compose; OpenWA is the active/default backend. Both exist in the codebase and could conflict if run simultaneously without the profile guard.
- **Threading:** n8n and Node.js services are single-threaded event loop. Easy!Appointments uses PHP's per-request process model via Apache.

## Anti-Patterns

### Hardcoded Credentials

**What happens:** The same Basic Auth credentials (`kamiikasee:admin2024`) and admin password are repeated across multiple PHP files: `landing-salon/index.php`, `landing-salon/api/servicios.php`, `api/crear-turno.php`, `api/horarios.php`, `api/turnos-admin.php`, `api/admin-servicios.php`, `api/horarios-admin.php`, `landing-salon/admin/index.php`.
**Why it's wrong:** Credential rotation requires editing 8+ files. Secrets are committed to git.
**Do this instead:** Use environment variables (Docker already injects them in `docker-compose.yml` for EA). Reference a single config file or `getenv()`.

### PHP cURL as API Gateway

**What happens:** Every API endpoint in `landing-salon/api/` independently opens a new HTTP connection via cURL to `localhost/index.php/api/v1/` inside the same container.
**Why it's wrong:** Unnecessary HTTP overhead within the same Apache process. cURL error handling is inconsistent across files. The `localhost` target depends on Apache being configured correctly on port 80.
**Do this instead:** Either call EA's internal PHP API directly (include/require), or use a proper API client library with connection reuse.

### Missing Input Validation in Public Endpoints

**What happens:** `landing-salon/api/crear-turno.php` validates required fields but does not sanitize or validate email/phone formats. Phone numbers are used as-is in WhatsApp messaging.
**Why it's wrong:** Invalid phone numbers will cause WhatsApp send failures downstream. XSS/CSRF is not handled in admin endpoints.
**Do this instead:** Validate phone format (country code + digits), validate email format, add CSRF tokens to admin forms.

### Dual WhatsApp Backends Without Clear Migration

**What happens:** Baileys (`baileys-service/index.js`) and OpenWA (`openwa/`) are both present. Baileys is `profiles: [legacy]` but OpenWA references a different session ID (`50999678-917d-44df-97aa-96d54e996b50`). The PHP relay (`whatsapp-relay.php`, `whatsapp-send.php`) and QR endpoint (`whatsapp-qr.php`) are hardcoded to OpenWA.
**Why it's wrong:** The Baileys legacy code exists but the PHP gateway exclusively uses OpenWA. Keeping dead code adds confusion and maintenance burden.
**Do this instead:** Fully migrate to OpenWA, remove Baileys legacy profile, or document an explicit migration timeline.

## Error Handling

**Strategy:** Fail-fast with JSON error responses, minimal retry logic

**Patterns:**
- PHP API endpoints: catch cURL failures, return `http_response_code(500)` or `502` with `{ "error": "...", "detail": "..." }`
- Baileys service: try/catch around all async operations, auto-reconnect on WhatsApp disconnect (5s delay), graceful Redis degradation (queue → direct send fallback)
- n8n workflows: Use n8n's built-in error handling and retry mechanisms per node
- OpenWA: NestJS global exception filters, ValidationPipe with `whitelist` and `forbidNonWhitelisted`

## Cross-Cutting Concerns

**Logging:**
- Baileys: `pino` structured logger at `info` level
- OpenWA: NestJS LoggerModule (configurable log level via `LOG_LEVEL` env)
- PHP: No structured logging; errors go to Apache error log
- n8n: Built-in execution logging with prune after 168 hours

**Validation:**
- PHP endpoints: Manual `if (!...)` checks per field, regex for date format
- OpenWA: NestJS ValidationPipe with DTOs, class-validator decorators
- Baileys: Manual checks on Express route handlers

**Authentication:**
- Admin Panel: PHP session-based, hardcoded password `admin2024` in `landing-salon/admin/index.php`
- Easy!Appointments API: HTTP Basic Auth with hardcoded credentials
- OpenWA: API key header `X-API-Key: dev-admin-key` (configured in `API_MASTER_KEY` env var)
- Baileys: No authentication on its API endpoints (internal Docker network only)
- n8n: No external auth configured; relies on internal Docker network isolation

---

*Architecture analysis: 2026-06-13*
