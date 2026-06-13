# Architecture Patterns

**Domain:** Online appointment booking system for small businesses
**Researched:** 2026-06-13
**Confidence:** HIGH (based on existing running codebase + official API docs)

## Recommended Architecture

TuAhora follows a **hub-and-spoke orchestration pattern** with n8n as the central coordinator and Easy!Appointments as the booking engine of record. The architecture is polyglot (PHP, Node.js, SQLite/MySQL, Redis) and containerized via Docker Compose.

```
┌─────────────────────────────────────────────────────────────────┐
│                     EXTERNAL BOUNDARY                           │
│                                                                 │
│  ┌──────────┐    ┌──────────┐    ┌──────────────────┐          │
│  │ Instagram │    │ WhatsApp │    │  Browser (Client) │          │
│  │  (funnel) │    │  (chat)  │    │  (booking page)  │          │
│  └─────┬─────┘    └────┬─────┘    └────────┬─────────┘          │
│        │               │                   │                    │
│        ▼               │                   ▼                    │
│  ┌──────────────────────┴──────────────────────────┐            │
│  │              LANDING PAGE (Vercel)               │            │
│  │  Static HTML/CSS — iframe embed of EA booking    │            │
│  └──────────────────────┬──────────────────────────┘            │
│                         │                                       │
├─────────────────────────┼───────────────────────────────────────┤
│            DOCKER COMPOSE NETWORK (stack)                        │
│                         │                                       │
│                         ▼                                       │
│  ┌──────────────────────────────────────────────┐               │
│  │        EASY!APPOINTMENTS (:8080)              │               │
│  │        Booking Engine (PHP)                   │               │
│  │        ┌──────────┐  ┌──────────────────┐    │               │
│  │        │ REST API │  │ Public Booking UI │    │               │
│  │        └────┬─────┘  └──────────────────┘    │               │
│  └─────────────┼────────────────────────────────┘               │
│                │                                                │
│                │ HTTP Basic Auth                                │
│                ▼                                                │
│  ┌──────────────────────────────────────────────┐               │
│  │              n8n (:5678)                      │               │
│  │         Workflow Orchestrator                  │               │
│  │                                               │               │
│  │  ┌─────────────────────────────────────────┐ │               │
│  │  │ WF-1: Polling new bookings (every 2min) │ │               │
│  │  │ WF-2: Reminder cron (daily 18:00)       │ │               │
│  │  │ WF-3: Cancel webhook (incoming WA msg)  │ │               │
│  │  │ WF-4: Reschedule webhook (incoming WA)  │ │               │
│  │  └─────────────────────────────────────────┘ │               │
│  └──────┬──────────────────────┬─────────────────┘               │
│         │                      │                                 │
│         │ POST /send-text      │ POST webhook (incoming msgs)    │
│         ▼                      ▼                                 │
│  ┌──────────────────────────────────────────────┐               │
│  │     BAILEYS SERVICE (:3001)                   │               │
│  │     WhatsApp Bridge (Node.js)                 │               │
│  │                                               │               │
│  │  Endpoints: /health, /qr, /send-text,         │               │
│  │             /send-reminder                     │               │
│  │  Events:    messages.upsert → n8n webhook     │               │
│  └────────────────────┬─────────────────────────┘               │
│                       │                                         │
│                       │ lPush (queue)                           │
│                       ▼                                         │
│  ┌──────────────────────────────────────────────┐               │
│  │          REDIS 7 (:6379)                      │               │
│  │          Message Queue + State                 │               │
│  └──────────────────────────────────────────────┘               │
│                                                                 │
│  ┌──────────┐    ┌──────────┐    ┌──────────┐                  │
│  │ MySQL 8  │    │ Mailpit  │    │ OpenWA   │                  │
│  │ (:3306)  │    │(:1025/25)│    │ (:2785)  │                  │
│  │ EA data  │    │ Dev mail │    │ Alt WA   │                  │
│  └──────────┘    └──────────┘    │ bridge   │                  │
│                                   └──────────┘                  │
└─────────────────────────────────────────────────────────────────┘
```

### Component Boundaries

| Component | Responsibility | Communicates With | Protocol | Direction |
|-----------|---------------|-------------------|----------|-----------|
| **Landing Page (Vercel)** | Marketing + booking iframe host | Easy!Appointments (iframe embed) | HTTPS → HTTP | External → Internal |
| **Easy!Appointments** | Booking engine of record: manages appointments, customers, services, providers, schedules | MySQL (storage), n8n (API consumer), Landing (iframe) | HTTP REST (Basic Auth) | Bidirectional (API) / Read from n8n |
| **MySQL 8.0** | Persistent storage for all EA data | Easy!Appointments only | MySQL protocol | Internal |
| **n8n** | Workflow orchestrator: all automation logic lives here | Easy!Appointments (polling/API), Baileys (outbound WA), Baileys webhook (inbound WA) | HTTP REST | Both directions (poll EA → send WA ← receive WA) |
| **Baileys Service** | WhatsApp connection: maintains WA WebSocket, translates HTTP ↔ WA messages | WhatsApp Cloud (WebSocket), n8n (outbound HTTP + inbound webhook), Redis (queue) | WS + HTTP | Bidirectional |
| **Redis 7** | Message queue for WhatsApp sends (buffers outgoing messages) | Baileys Service only | Redis protocol | Write-only from Baileys |
| **Mailpit** | Development email capture (not production) | Easy!Appointments (SMTP) | SMTP | Receive-only |
| **OpenWA** | Alternative WhatsApp bridge (whatsapp-web.js based, legacy profile) | n8n (current workflows point here) | HTTP REST | Bidirectional |

### Critical Architecture Decision: Baileys vs OpenWA Duality

**Current state:** The n8n workflows (`WF1-confirmacion.json`, `WF3-cancelacion.json`, etc.) reference `tuahora_openwa:2785` with OpenWA's API format (`/api/sessions/{id}/messages/send-text`), NOT the Baileys service. However, the Baileys service at `tuahora_baileys:3001` is the documented WhatsApp bridge with its own REST API (`/send-text`, `/send-reminder`).

**Implication:** The system currently has **two WhatsApp bridges** and the n8n workflows use OpenWA while documentation describes Baileys. This must be consolidated to one bridge.

**Recommendation:** Consolidate on **Baileys** (lighter, no Puppeteer, active maintenance). Update all n8n workflow URLs to `http://tuahora_baileys:3001/send-text` format. Remove OpenWA from docker-compose.yml. [See PITFALLS.md for details.]

### Data Flow

#### Flow 1: Booking → Confirmation (Outbound)

```
Customer (Browser) → Landing Page → Easy!Appointments iframe (POST booking)
                                        │
                         [MySQL stores appointment with auto-increment ID]
                                        │
                   ┌────────────────────┘
                   ▼
n8n WF-1 (Schedule every 2min):
  GET /api/v1/appointments?sort=-id&length=1&with=customer,service,provider
  Compare ID > lastProcessedId (stored in $workflow.staticData)
  If NEW → Extract: name, phone, service, date, time, provider
         → Format WhatsApp message (Spanish template)
         → POST to Baileys /send-text { phone, message }
                   │
                   ▼
Baileys Service:
  Accepts POST, enqueues via Redis (redisClient.lPush('wa_queue', ...))
  Worker dequeues and sends via Baileys WebSocket
                   │
                   ▼
Customer's WhatsApp ← message arrives
```

#### Flow 2: Reminder (Outbound, Scheduled)

```
n8n WF-2 (Cron: 0 18 * * * — daily at 6pm ART):
  Compute tomorrow's date
  GET /api/v1/appointments?with=customer,service,provider (all)
  Filter in Code node: appointments where start date = tomorrow
  For each match → format reminder message
                → POST to Baileys /send-reminder { phone, clientName, service, date, time }
                   │
                   ▼
Customer's WhatsApp ← reminder arrives
```

#### Flow 3: Cancel (Inbound → Outbound)

```
Customer sends "CANCELAR" on WhatsApp
                   │
                   ▼
Baileys Service (messages.upsert event):
  Detects non-self message → extracts phone + text
  POSTs to N8N_WEBHOOK_URL (comma-separated list of URLs)
                   │
                   ▼
n8n WF-3 (Webhook trigger: /webhook/whatsapp-cancelacion):
  IF text contains "CANCELAR" →
    GET /api/v1/customers?q={phone}        ← find customer by phone
    IF found →
      GET /api/v1/appointments?with=...     ← all appointments
      Filter: customerId matches + start > now (future only)
      IF future appointment found →
        DELETE /api/v1/appointments/{id}    ← cancel in EA
        POST to Baileys: confirmation to customer
        POST to Baileys: notification to owner (hardcoded number)
      ELSE → reply "No encontré ningún turno activo"
    ELSE → reply "No encontré ningún turno activo"
  ELSE → (no-op, message not a cancel command)
```

#### Flow 4: Reschedule (Inbound → Outbound)

```
Customer sends "CAMBIAR" on WhatsApp
                   │
                   ▼
n8n WF-4 (Webhook trigger: /webhook/whatsapp-reagendado):
  IF text contains "CAMBIAR" or "REAGENDAR" →
    Same lookup flow as WF-3 (find customer → find future appointments)
    IF found →
      DELETE /api/v1/appointments/{id}     ← cancel current
      POST to Baileys: reply with booking link URL
    ELSE → reply "No encontré ningún turno activo"
  ELSE → (no-op)
```

### Key Integration Patterns

#### Pattern 1: Polling for New Records (No Webhook Available)
**What:** n8n Schedule Trigger polls EA API every 2 minutes for the latest appointment, compares ID against `$workflow.staticData.lastProcessedId`.

**Why this pattern:** Easy!Appointments has no native webhook/callback support. The built-in email notifications go through SMTP (captured by Mailpit in dev). Rather than parsing emails, polling the REST API is more reliable.

**When to use:** Any integration where the source system lacks webhooks but has a REST API with monotonic IDs or timestamps.

**Risk:** 2-minute polling means up to 2 minutes of latency for confirmation messages. Acceptable for this use case (confirmation, not real-time chat).

**Improvement path:** Add a PHP callback in EA's controller (hook into appointment creation) that fires a webhook to n8n. This eliminates polling latency entirely.

#### Pattern 2: Incoming Message Fan-Out via Webhooks
**What:** Baileys detects incoming WhatsApp messages and POSTs them to multiple n8n webhook URLs (comma-separated in `N8N_WEBHOOK_URL` env var). Each webhook is a separate n8n workflow specialized for a different command (cancel, reschedule).

**Why:** Single incoming message → multiple workflows with independent logic. Decouples command handling. Adding a new command = new n8n workflow + add its webhook URL to the comma-separated list.

**Risk:** Every incoming message fires ALL webhooks (wasteful). The If nodes in each workflow filter non-matching messages, but the HTTP calls still happen.

**Improvement path:** Route messages in n8n with a single webhook workflow that branches based on keyword detection, then calls sub-workflows.

#### Pattern 3: Redis Queue for WhatsApp Delivery
**What:** Baileys writes outgoing messages to Redis list (`lPush 'wa_queue'`), then a consumer dequeues and sends via WhatsApp WebSocket. Fallback: direct send if Redis unavailable.

**Why:** Decouples HTTP request handling from WhatsApp WebSocket delivery. Prevents HTTP timeout if WA connection is slow. Enables retry logic.

**Risk:** Currently only enqueues — no visible consumer/dequeue loop in index.js. The `sendMessage` function is called directly for non-queue path, but the queue consumer isn't explicitly coded. This needs verification.

#### Pattern 4: Static Data as Workflow State
**What:** n8n Code nodes use `$workflow.staticData` to persist the last processed appointment ID across workflow executions.

**Why:** No external database needed for simple counter state. Survives n8n restarts (stored in n8n's SQLite database). Minimal complexity.

**Limitation:** Only works for single-instance, single-tenant. Multi-tenant would need per-tenant state tracking.

### Data Storage Responsibilities

| Store | Owner | Data | Why Here |
|-------|-------|------|----------|
| MySQL 8.0 | Easy!Appointments | Appointments, customers, services, providers, settings, users | EA's native storage |
| SQLite (n8n internal) | n8n | Workflow definitions, execution history, `$workflow.staticData` | n8n's default embedded DB |
| Redis 7 | Baileys Service | Message queue (`wa_queue`), session tokens | In-memory queue for WA delivery |
| Filesystem (Docker volume: baileys_sessions) | Baileys | WhatsApp auth credentials (MultiFileAuthState) | Required by Baileys for session persistence |
| Static HTML files | Landing (Vercel) | Marketing pages, CSS, images | Static hosting, no backend needed |

### Component Dependency Graph (Build Order)

```
Level 0 (No dependencies):
  MySQL 8.0
  Redis 7
  Docker network (stack)

Level 1 (Depends on Level 0):
  Easy!Appointments (needs MySQL)
  Mailpit (standalone)

Level 2 (Depends on Level 1):
  n8n (needs network to reach EA)
  Baileys Service (needs Redis for queue, needs network)
  OpenWA (standalone but needs network to talk to n8n)

Level 3 (Depends on Level 2):
  n8n Workflows (WF-1..WF-4) — imported after n8n is running
  Landing Page deployment (independent of Docker stack, but needs EA URL for iframe)

Level 4 (Depends on Level 3):
  End-to-end testing (all components running + workflows active)
```

### Scalability Considerations

| Concern | At 1 client (pilot) | At 10 clients (multi-tenant) | At 100 clients |
|---------|---------------------|------------------------------|----------------|
| Easy!Appointments | Single instance, 1 provider | Need separate EA instances OR multi-provider config | Per-client EA instances + reverse proxy |
| n8n | Single instance, 4 workflows | n8n can handle — add per-client workflow copies | Queue mode + Redis for n8n execution scaling |
| Baileys WA | Single WA session | Max 1 WA session per phone number. Multiple clients = separate Baileys instances | Phone number per client. Multiple Baileys containers |
| MySQL | Single DB, <1000 rows | Separate DB per EA instance or schema-per-client | Per-client DB or managed MySQL |
| Polling (WF-1) | 2-min interval, <10 appointments/day | 2-min interval still fine at 10x volume | Consider EA webhook callback to eliminate polling |
| Redis | Single instance | Single instance fine | Separate instances or namespaces per client |

> **Note:** Multi-tenancy is explicitly out of scope for v1. The pilot is a single salon. Architecture decisions optimize for simplicity now, with known upgrade paths documented.

## Anti-Patterns to Avoid

### Anti-Pattern 1: Shared Database Access
**What:** n8n directly querying MySQL instead of going through EA's REST API.

**Why bad:** Bypasses EA's business logic (validation, availability checks, audit trail). Creates tight coupling to EA's schema. Breaks when EA schema changes.

**Instead:** Always use EA's REST API for CRUD operations. n8n is an API consumer, not a database client.

### Anti-Pattern 2: Workflow Logic in Baileys Service
**What:** Putting business logic (message formatting, scheduling logic, customer matching) in the Baileys Node.js service.

**Why bad:** The Baileys service's job is WhatsApp connectivity only. Business logic in the bridge creates a monolithic service that's hard to change. n8n exists precisely to handle orchestration.

**Instead:** Baileys service = translation layer (HTTP ↔ WhatsApp WebSocket). All business rules, message formatting, workflow logic in n8n workflows.

### Anti-Pattern 3: Hardcoded Phone Numbers and URLs
**What:** n8n workflows contain hardcoded owner phone (`3826405610@c.us`), OpenWA session IDs, and dev API keys in workflow JSON.

**Why bad:** Cannot switch between dev/prod without editing workflows. Secrets in version control.

**Instead:** Use n8n credentials/variables for phone numbers, URLs, and API keys. Use environment-aware configuration.

### Anti-Pattern 4: Two WhatsApp Bridges
**What:** Both Baileys (port 3001, `@whiskeysockets/baileys` v6) and OpenWA (port 2785, `whatsapp-web.js` via Docker) running simultaneously with n8n workflows pointing to OpenWA while documentation references Baileys.

**Why bad:** Confusion, resource waste, two points of failure, unclear which bridge handles actual messages.

**Instead:** Pick one. Recommendation: **Baileys** (no headless browser required, actively maintained, lighter memory footprint). Remove OpenWA service from docker-compose.yml. Update all n8n workflow endpoints.

## Sources

- [Easy!Appointments REST API docs](https://github.com/alextselegidis/easyappointments/blob/main/docs/rest-api.md) — HIGH confidence (official, current for v1.6.0)
- [n8n Docs via Context7](https://github.com/n8n-io/n8n-docs) — HIGH confidence (official, schedule/webhook/HTTP node documentation)
- [Baileys library](https://github.com/WhiskeySockets/Baileys) — MEDIUM confidence (v6.7.23 used, official GitHub)
- [Existing architecture docs](E:\TUAHORA\docs\Arquitectura.md) — HIGH confidence (project documentation)
- [Docker Compose file](E:\TUAHORA\easyappointments\docker-compose.yml) — HIGH confidence (running configuration)
- [n8n workflow files](E:\TUAHORA\n8n-workflows\) — HIGH confidence (actual workflow JSON exports)
- [Baileys service code](E:\TUAHORA\baileys-service\index.js) — HIGH confidence (actual implementation)
