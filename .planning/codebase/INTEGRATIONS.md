# External Integrations

**Analysis Date:** 2026-06-13

## APIs & External Services

### WhatsApp Messaging (Two Engines)

**OpenWA (whatsapp-web.js engine) — Primary:**
- Service: Self-hosted WhatsApp HTTP API Gateway
- SDK/Client: `whatsapp-web.js` ^1.26.1-alpha.3 (Puppeteer-based)
- Container: `tuahora_openwa` (port 2785) — `easyappointments/docker-compose.yml`
- Auth: API Master Key header (`X-API-Key`) — env: `API_MASTER_KEY`
- Session: Local filesystem (`/app/data/sessions`) via Docker volume `openwa_data`
- Multi-session support with UUID-based session IDs (e.g., `50999678-917d-44df-97aa-96d54e996b50` for confirmations, `c26cd109-f437-4e22-9413-0af14539411c` for reminders/cancellations)
- Browser: Chromium headless (installed in Docker image)

**Baileys (wa-socket engine) — Legacy:**
- Service: Direct WhatsApp Web multi-device socket connection
- SDK/Client: `@whiskeysockets/baileys` ^6.7.23
- Container: `tuahora_baileys` (port 3001, profile: `legacy`) — `easyappointments/docker-compose.yml`
- Auth: QR code pairing (scanned via WhatsApp mobile) — sessions stored in `/app/auth` (Docker volume `baileys_sessions`)
- Health endpoint: `http://localhost:3001/health`
- QR display: `http://localhost:3001/qr-page` (HTML auto-refresh)

### Workflow Automation

**n8n:**
- Service: Visual workflow automation engine
- Container: `n8n` (port 5678) — `easyappointments/docker-compose.yml`
- Image: `n8nio/n8n:latest`
- Storage: SQLite (`DB_TYPE: sqlite`, persisted to `n8n_data` volume)
- Host: `http://localhost:5678` (internal); `WEBHOOK_URL: http://localhost:5678`
- Data retention: 7-day execution history (`EXECUTIONS_DATA_MAX_AGE: 168` hours)
- 4 workflows deployed:
  - **WF1-Confirmacion:** Schedule-triggered (every 120s) → polls Easy!Appointments API → sends WhatsApp confirmation via OpenWA — `n8n-workflows/WF1-confirmacion.json`
  - **WF2-Recordatorio:** Cron-triggered (daily 18:00) → polls Easy!Appointments → sends 24h reminder via OpenWA — `n8n-workflows/WF2-recordatorio.json`
  - **WF3-Cancelacion:** Webhook-triggered (`/webhook/whatsapp-cancelacion`) → processes CANCELAR keyword → deletes appointment in Easy!Appointments → notifies owner — `n8n-workflows/WF3-cancelacion.json`
  - **WF4-Reagendado:** Webhook-triggered (`/webhook/whatsapp-reagendado`) → processes CAMBIAR/REAGENDAR keywords → deletes appointment → sends rebooking link — `n8n-workflows/WF4-reagendado.json`

### Booking System

**Easy!Appointments:**
- Service: Open-source appointment scheduling (PHP)
- Container: `easyappointments` (port 8080, mapped to `:80` internally) — `easyappointments/docker-compose.yml`
- Image: `alextselegidis/easyappointments:latest` (customized via `easyappointments/Dockerfile`)
- API Base: `http://easyappointments:80/index.php/api/v1/` (Docker network internal)
- API Endpoints used:
  - `GET /appointments` — list appointments (with `?sort=-id`, `?with=customer,service,provider`)
  - `DELETE /appointments/{id}` — cancel appointment
  - `GET /customers` — list/search customers
  - `POST /customers` — create customer
  - `POST /appointments` — create appointment
  - `GET /services` — list services
  - `GET /services/{id}` — get service details (for duration)
- Auth: HTTP Basic Auth (`kamiikasee:admin2024`)
- Base URL: `http://localhost:8080` (external); `http://localhost:80/index.php` (internal from landing page `landing-salon/index.php`)

## Data Storage

**Databases:**

| Service | Type | Image/Client | Connection |
|---------|------|-------------|------------|
| Easy!Appointments | MySQL 8.0 | `mysql:8.0` container (`ea-mysql`) | `DB_HOST: mysql`, `DB_NAME: easyappointments`, auth: `ea_user` / `ea_pass_2024` |
| OpenWA (primary) | SQLite | `sqlite3` ^5.1.7 via TypeORM | `DATABASE_NAME: /app/data/openwa.sqlite`, `DATABASE_TYPE: sqlite` |
| OpenWA (optional) | PostgreSQL 16 | `postgres:16-alpine` container (profile: `postgres`) | `DATABASE_HOST`, `DATABASE_PORT: 5432`, auth via env vars |
| n8n | SQLite | Built-in (n8n image) | `DB_TYPE: sqlite`, stored in `n8n_data` volume |
| Redis | Redis 7-alpine | `redis:7-alpine` container | `redis://redis:6379` (Docker network internal) |

**File Storage:**
- Local filesystem (default for OpenWA) — `STORAGE_TYPE: local`, `STORAGE_LOCAL_PATH: /app/data/media`
- MinIO (S3-compatible, optional) — `minio/minio` container (profile: `minio`), SDK: `@aws-sdk/client-s3` ^3.1048.0
- S3/AWS (optional) — same SDK, env: `S3_ENDPOINT`, `S3_ACCESS_KEY`, `S3_SECRET_KEY`, `S3_BUCKET`

**Caching:**
- Redis (optional in OpenWA, enabled for Baileys message queue) — `REDIS_ENABLED` env flag
- Baileys uses Redis for message queuing: `redisClient.lPush('wa_queue', ...)` in `baileys-service/index.js`

## Authentication & Identity

**Auth Provider:**
- API Key authentication for OpenWA — `X-API-Key` header, env: `API_MASTER_KEY` (set to a shared secret in `easyappointments/docker-compose.yml`)
- HTTP Basic Auth for Easy!Appointments API — credentials embedded in n8n workflows (`genericCredentialType` / `httpBasicAuth`) and PHP relay scripts
- No OAuth, JWT, or identity provider integration detected
- Easy!Appointments has its own user management for the backend dashboard (`/index.php/backend`)

## Monitoring & Observability

**Error Tracking:**
- None detected (no Sentry, Bugsnag, etc.)

**Logs:**
- pino (`pino` ^9.5.0) for Baileys service — structured JSON logging at `info` level
- NestJS default logger for OpenWA — level controlled by `LOG_LEVEL` env (error|warn|info|debug)
- Docker container logs (stdout/stderr) as primary log transport
- n8n has built-in execution logging

**Health Checks:**
- OpenWA: `GET /api/health` (Docker HEALTHCHECK + monitoring scripts)
- Baileys: `GET /health` (returns `{ status: 'ok', whatsapp: '<connectionState>' }`)
- n8n: `GET /healthz`
- Stack-wide: `scripts/health-check.ps1` checks all containers and endpoints
- External alerts: `ntfy.sh` push notifications for health failures via `health-check.ps1 -NtfyTopic <topic>`

**Mail Testing:**
- Mailpit (`axllent/mailpit:latest`) — SMTP sandbox on port 1025, web UI on port 8025
- All emails from Easy!Appointments routed to Mailpit in development (`MAIL_SMTP_HOST: mailpit`, `MAIL_SMTP_PORT: 1025`)

## CI/CD & Deployment

**Hosting:**
- Self-hosted (Docker host — Windows PC or Linux VPS)
- Public exposure planned via Cloudflare Tunnel (`cloudflared` client) — see `cloudflare-tunnel.md`
- Domain plan: `tuahora.com.ar` with subdomains `booking.tuahora.com.ar` (Easy!Appointments), `admin.tuahora.com.ar` (n8n)

**CI Pipeline:**
- None detected (no GitHub Actions, GitLab CI, Jenkins, etc.)

**Deployment:**
- Manual Docker Compose (`docker compose up -d` from `easyappointments/docker-compose.yml`)
- Check script: `scripts/check-stack.ps1` verifies all containers and endpoints after deploy
- Backup script: `scripts/backup-mysql.ps1` (MySQL data backup)

## Environment Configuration

**Required env vars (critical):**

| Variable | Service | Purpose |
|----------|---------|---------|
| `API_MASTER_KEY` | OpenWA | Master API key for all endpoints |
| `DATABASE_TYPE` | OpenWA | `sqlite` or `postgres` |
| `DATABASE_NAME` | OpenWA | Path to SQLite file or PostgreSQL DB name |
| `ENGINE_TYPE` | OpenWA | `whatsapp-web.js` (or `baileys` future) |
| `SESSION_DATA_PATH` | OpenWA | WhatsApp session credential storage |
| `DB_HOST/NAME/USERNAME/PASSWORD` | Easy!Appointments | MySQL connection |
| `MAIL_*` | Easy!Appointments | SMTP configuration |
| `REDIS_URL` | Baileys | Redis connection string |
| `N8N_WEBHOOK_URL` | Baileys | Comma-separated n8n webhook URLs |
| `N8N_HOST/WEBHOOK_URL` | n8n | n8n host/webhook configuration |

**Secrets location:**
- Credentials hardcoded in `easyappointments/docker-compose.yml` environment variables (MySQL passwords, API keys, Easy!Appointments auth)
- `.env` file for Easy!Appointments present at `easyappointments/.env`
- `openwa/.env.example` and `openwa/.env.minimal` are templates only
- OpenWA uses progressive env loading: system env > `.env` > `data/.env.generated`

## Webhooks & Callbacks

**Incoming (n8n listens):**
- `POST /webhook/whatsapp-cancelacion` — receives WhatsApp messages forwarded by Baileys/OpenWA for cancellation processing (WF3)
- `POST /webhook/whatsapp-reagendado` — receives WhatsApp messages for rescheduling (WF4)
- n8n production URL: `http://localhost:5678` (Docker internal); exposed externally via `WEBHOOK_URL`

**Outgoing (n8n/systems call):**
- n8n → OpenWA: `POST http://tuahora_openwa:2785/api/sessions/{sessionId}/messages/send-text` (sends WhatsApp messages)
- n8n → Easy!Appointments: `GET/POST/DELETE http://easyappointments:80/index.php/api/v1/{resource}` (CRUD bookings)
- Baileys → n8n: Forwards incoming WhatsApp messages via HTTP POST to configured `N8N_WEBHOOK_URL` endpoints — `baileys-service/index.js` lines 84-95
- Landing page → Easy!Appointments: PHP cURL calls to `http://localhost/index.php/api/v1/` for service listing and appointment creation — `landing-salon/index.php`, `landing-salon/api/crear-turno.php`
- Landing page → OpenWA: PHP cURL relay to `http://tuahora_openwa:2785/api/sessions/{sessionId}/messages/send-text` — `landing-salon/api/whatsapp-relay.php`

**Webhook Configuration (OpenWA):**
- Timeout: 10000ms (`WEBHOOK_TIMEOUT`)
- Max retries: 3 (`WEBHOOK_MAX_RETRIES`)
- Retry delay: 5000ms (`WEBHOOK_RETRY_DELAY`)

## Proxy & Networking

**Traefik (OpenWA optional):**
- Service: Traefik v3.0 reverse proxy — `openwa/docker-compose.yml` (profile: `with-proxy`, `full`)
- Config: `openwa/traefik/traefik.yml` (static), `openwa/traefik/dynamic.yml` (dynamic)
- Ports: Dashboard port (DASHBOARD_PORT) mapped to container:80; Traefik dashboard on :8080
- Purpose: SSL termination, routing, load balancing (not currently used in TuAhora main stack)

**Docker Network:**
- All TuAhora services share Docker network `stack` — `easyappointments/docker-compose.yml`
- OpenWA uses its own network `openwa-network` in standalone deployment — `openwa/docker-compose.yml`

---

*Integration audit: 2026-06-13*
