# Technology Stack

**Project:** TuAhora - Sistema de turnos online para pequeños negocios
**Researched:** 2026-06-13
**Overall confidence:** HIGH

## Recommended Stack

### Core Framework — Booking Engine

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Easy!Appointments | `1.6.0` (latest, May 2026) | Motor de reservas | Open source, API REST, webhooks built-in since 1.5.0, PHP 8.4 support, official Docker image, ya funcionando |
| MySQL | `8.4` (LTS) | Base de datos de turnos | MySQL 8.0 usado actualmente pero 8.4 es LTS con soporte extendido hasta 2032. MySQL 9.7 es demasiado nuevo y no probado con EA. |
| PHP (embedded) | `8.4` (vía Docker image) | Runtime de EA | PHP 8.4 tiene soporte hasta 2028. EA 1.6.0 lo soporta oficialmente. |

**EA upgrade:**
- The project currently uses `mysql:8.0` — upgrade to `mysql:8.4` (LTS, backward-compatible, Easy!Appointments 1.6.0 tested with MySQL 8.x)
- Easy!Appointments 1.6.0 adds **webhooks nativos** (since 1.5.0 with `X-EA-Token` header). This could reduce or eliminate polling in WF-1 (currently polls every 2 minutes). Phase 3 should evaluate switching to native webhooks.

**Source:** GitHub releases — v1.6.0 stable released 2026-05-27. Docker Hub updated 17 days ago. [HIGH confidence]

### WhatsApp Integration

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Baileys | `^6.7.23` (pinned, not `^7.x`) | Bot de WhatsApp (enviar/recibir mensajes) | Open source, sin costo, ideal para MVP. v7.0.0 (rc13, 23 días) tiene BREAKING CHANGES masivos — no actualizar hasta que esté estable y se hayan migrado los patterns. |
| Express | `^4.21.0` (pinned 4.x) | API HTTP del Baileys service | Express 5.x (5.2.1 actual) tiene breaking changes en error handling y `app.param()`. La API es mínima (4 endpoints), no justifica migrar. |
| Redis | `7-alpine` (Docker) + `^4.7.0` (npm client) | Cola de mensajes WA | Redis 7 es estable. Cliente npm 4.x está probado con el Baileys service. v6.0.0 existe pero tiene API changes. Pin en 4.x. |
| qrcode | `^1.5.4` | Generación de QR para vincular WhatsApp | Ligero, sin dependencias pesadas. Única versión actual. |

**What NOT to use for WhatsApp:**
- ❌ **WhatsApp Business API (Meta official)** — Requiere verificación de negocio (Facebook Business Manager), proceso de semanas, costo por conversación ($0.005+ USD después de 1000/mes). Para un salón en Chamical esto es overkill y burocracia innecesaria.
- ❌ **Twilio WhatsApp API** — Mismo proceso de verificación + markup de Twilio. Costo recurrente en USD con tipo de cambio desfavorable.
- ❌ **whatsapp-web.js** — El proyecto tiene `openwa` como alternativa legacy pero usa Puppeteer (500MB+ RAM). Baileys es WebSocket directo, sin browser.

**Baileys v7 migration note:** La versión 7.0.0-rc13 está disponible en npm. NO migrar en MVP. Esperar release estable. Documentación de migración: https://whiskey.so/migrate-latest. Planear migración para v2 si Baileys 7.x llega a stable con soporte comunitario sólido.

**Sources:** npm registry (2026-06-13), Baileys wiki [HIGH confidence]

### Automation — n8n Workflows

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| n8n | `latest` (imagen Docker, built 2026-06-10) | Orquestación de workflows WA | Workflows visuales, nodes: Schedule Trigger (cron), Webhook, HTTP Request, MySQL. Más fácil de mantener que scripts custom. Auto-actualizable con `docker compose pull`. |
| n8n DB | `SQLite` (default) | Almacenamiento de workflows | Para MVP single-instance SQLite es suficiente. No agrega complejidad de Postgres. |

**Key nodes used by the 4 workflows:**
| Workflow | Trigger Node | Action Nodes | Pattern |
|----------|-------------|-------------|---------|
| WF-1 Confirmación | Schedule Trigger (cada 2 min) | MySQL → HTTP Request (Baileys /send-text) | Polling (→ evaluar webhook nativo EA en Phase 3) |
| WF-2 Recordatorio 24h | Schedule Trigger (cron: `0 8 * * *` — 8 AM diario) | MySQL → Filter → HTTP Request (Baileys /send-reminder) | Batch diario |
| WF-3 Cancelación | Webhook (recibe de Baileys) | MySQL (DELETE appointment) → HTTP Request (Baileys confirmación) | Event-driven |
| WF-4 Reagendado | Webhook (recibe de Baileys) | MySQL (DELETE) → HTTP Request (Baileys envía link) | Event-driven |

**Sources:** n8n Docs (Context7 verified) — Webhook, Schedule Trigger, MySQL nodes confirmed. [HIGH confidence]

### Landing Page & Frontend

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| HTML/CSS/JS | Vanilla (sin framework) | Landing page mobile-first | Máximo rendimiento, deploy gratis en Vercel, <200KB objetivo. Sin build step, sin dependencias npm en frontend. |
| CSS | Modern CSS (Grid, Flexbox, CSS Variables, `@container`) | Layout y diseño responsive | Sin Tailwind (70KB+ solo el reset), sin Bootstrap (150KB+). CSS moderno con variables para theming por cliente. |
| Google Fonts | `font-display: swap` con subset `latin` | Tipografía | Subset mínimo (solo caracteres latinos). Cargar 1-2 weights máximo. |
| Vercel | Hobby tier (gratis) | Hosting y deploy | CDN global con 126 PoPs, región São Paulo (gru1) más cercana a Argentina, SSL automático, deploy con `vercel --prod`. |

**Landing page constraints (hard):**
- Peso total <200KB (sin imágenes externas)
- Mobile-first: probado en 375px (mobile) y 1280px (desktop)
- iframe embed de Easy!Appointments para el booking flow
- Funnel principal: Instagram → Landing → Booking

**What NOT to use for landing:**
- ❌ **React/Next.js** — Agrega 40KB+ de JS solo por el runtime. Overkill para landing estática. Build step innecesario.
- ❌ **Tailwind CSS** — 70KB+ de CSS (incluso purgado). Rompe el presupuesto de 200KB.
- ❌ **Bootstrap** — 150KB+. Pesado, opinado, difícil de personalizar por cliente.
- ❌ **WordPress** — Requiere hosting PHP/MySQL, vulnerable a ataques, lento. La landing es estática, no necesita CMS.
- ❌ **Astro/Svelte/Hugo** — Frameworks de static generation. Útiles si hay muchas páginas o componentes reusables. Para una sola landing page, HTML vanilla es más simple y no agrega tooling.

**If multi-client scaling becomes a need (v2):**
- Considerar Astro (v5.x, static output) para generar landings por cliente con componentes reusables
- Output sigue siendo HTML/CSS estático, mismo deploy en Vercel

**Sources:** Vercel docs — São Paulo region confirmed, 126 PoPs, free tier specs. [HIGH confidence]

### Infrastructure — Docker Compose

| Technology | Version | Purpose | Why |
|------------|---------|---------|-----|
| Docker Engine | 29.2.1 | Contenedores | Versión actual, estable |
| Docker Compose | v5.1.0 | Orquestación local | Compatible con compose spec v3 |
| Mailpit | `latest` | Email de desarrollo | Captura emails de EA sin enviarlos realmente. Puerto 8025 para UI. |
| cloudflared | `latest` (v2) | Tunnel productivo | Exponer servicios locales sin abrir puertos del router. Gratuito. Planificado para v2. |

**What NOT to use for hosting (MVP):**
- ❌ **VPS (DigitalOcean/Linode/Hostinger)** — Costo en USD, mantenimiento de servidor, backups manuales. Solo justifica si hay 5+ clientes pagando.
- ❌ **n8n Cloud** — $20/mes base. Para MVP local con Docker es suficiente.
- ❌ **AWS/GCP/Azure** — Complejidad y costo desproporcionados para Argentina. Facturación en USD.

**Production evolution (v2, when paying clients exist):**
| Scale | Hosting | Cost (ARS/mes) |
|-------|---------|----------------|
| 1-3 clientes | Docker en PC local + Cloudflare Tunnel | $0 + electricidad |
| 5-10 clientes | VPS en Hostinger/Brasil (~$25 USD/mes) | ~$8.500 ARS |
| 10+ clientes | VPS dedicado + backups automáticos | ~$15.000+ ARS |

**Sources:** Docker Hub, Cloudflare docs. [MEDIUM confidence on pricing — exchange rate fluctuates]

## Alternatives Considered

| Category | Recommended | Alternative | Why Not |
|----------|-------------|-------------|---------|
| Booking engine | Easy!Appointments 1.6.0 | Cal.com | Cal.com es más moderno (Next.js, TypeScript) pero pesa 5x más en recursos, requiere Node.js + Postgres, y su capa gratuita self-hosted tiene features limitadas. EA es PHP liviano, MySQL, 4.2k estrellas, 10+ años de desarrollo. Ya está funcionando en el proyecto. |
| Booking engine | Easy!Appointments 1.6.0 | Calendly | SaaS cerrado, $10/usuario/mes en USD. No viable para modelo agente local. |
| Booking engine | Easy!Appointments 1.6.0 | SimplyBook.me | SaaS, plan gratuito limitado a 50 turnos. Mercado argentino no es target. |
| WhatsApp | Baileys 6.7.23 (library) | whatsapp-web.js | Requiere Puppeteer/Chromium (~500MB RAM), más lento. Baileys es WebSocket directo. |
| WhatsApp | Baileys 6.7.23 (library) | Meta WhatsApp Cloud API | Burocracia de verificación, costo recurrente, depende de servidores Meta. Baileys es gratuito e independiente. |
| Automation | n8n (Docker) | Scripts Node.js custom | n8n tiene UI visual, manejo de errores, reintentos, logs. Scripts custom requieren escribir todo eso desde cero. |
| Automation | n8n (Docker) | Zapier/Make | SaaS con costo mensual en USD. No compite con n8n self-hosted gratuito. |
| Landing page | HTML/CSS vanilla | Astro | Astro agrega build step y dependencias. Para UNA landing, HTML es más simple. Para multi-cliente (v2), Astro empieza a tener sentido. |
| Landing hosting | Vercel (Hobby) | Cloudflare Pages | Similar (CDN global, SSL, gratis). Vercel tiene mejor DX para deploy manual (`vercel --prod`). |
| Landing hosting | Vercel (Hobby) | Netlify | Similar. Vercel tiene región en São Paulo, Netlify no publica regiones LatAm claramente. |
| Database | MySQL 8.4 | MariaDB | EA está testeado con MySQL. MariaDB puede funcionar pero no es el target oficial. |
| Database | MySQL 8.4 | PostgreSQL | EA no soporta Postgres. Requiere MySQL/MariaDB. |
| Cache/Queue | Redis 7 | RabbitMQ | Redis es suficiente para cola simple de mensajes. RabbitMQ agrega complejidad de operación innecesaria. |
| Email (dev) | Mailpit | MailHog | MailHog no recibe updates desde 2020. Mailpit es activo, compatible, misma API. |
| Auth (Baileys) | MultiFileAuthState (archivos locales) | PostgreSQL/SQLite auth store | Para MVP con 1 instancia, archivos locales son suficientes. En producción multi-instancia se necesita DB shared. |

## Installation

```bash
# Core stack (Phase 0 — already running)
docker compose -f easyappointments/docker-compose.yml up -d

# Landing page deploy (Phase 1)
cd landing-salon
# Static files — deploy with Vercel CLI
vercel --prod

# Baileys service dependencies (Phase 0 — already installed)
cd baileys-service
npm install   # express@^4.21.0, @whiskeysockets/baileys@^6.7.23, redis@^4.7.0, qrcode@^1.5.4, pino@^9.5.0
```

## Version Pinning Strategy

**Principle: Pin conservative versions for MVP stability. Upgrade path defined for v2.**

| Package | Current (project) | Latest (npm, June 2026) | Recommendation | Rationale |
|---------|-------------------|------------------------|----------------|-----------|
| @whiskeysockets/baileys | `^6.7.23` | `7.0.0-rc13` | **Pin `^6.7.23`** | v7 tiene breaking changes, rc aún (release candidate). No arriesgar MVP. Migrar en v2 cuando sea stable. |
| express | `^4.21.0` | `5.2.1` | **Pin `^4.21.0`** | v5 tiene breaking changes (error handling middleware, `app.param()` removido). API mínima no justifica migración. |
| redis (npm) | `^4.7.0` | `6.0.0` | **Pin `^4.7.0`** | v5+ tiene API changes. Baileys service es simple, no necesita features nuevas. |
| pino | `^9.5.0` | `10.3.1` | **Pin `^9.5.0`** | Logger. v10 tiene cambios menores pero no aporta valor crítico. |
| qrcode | `^1.5.4` | `1.5.4` | **Keep `^1.5.4`** | Ya está en la última versión estable. |
| mysql (Docker) | `8.0` | `9.7.0` (latest), `8.4.9` (LTS) | **Upgrade a `mysql:8.4`** | 8.4 es LTS hasta 2032. 8.0 ya no recibe actualizaciones de features. 9.x no está probado con EA. |
| redis (Docker) | `7-alpine` | `7-alpine` | **Keep `7-alpine`** | Redis 7 es estable, alpine reduce tamaño de imagen. |
| n8n (Docker) | `n8nio/n8n:latest` | `latest` (built 10 Jun 2026) | **Keep `latest`** | n8n tiene buen track record de estabilidad en latest. Auto-actualizable. |

## Sources

- **Easy!Appointments 1.6.0 release** — https://github.com/alextselegidis/easyappointments/releases/tag/1.6.0 (2026-05-27) [HIGH]
- **Easy!Appointments Docker Hub** — https://hub.docker.com/r/alextselegidis/easyappointments (updated 17 days ago) [HIGH]
- **Baileys npm** — https://www.npmjs.com/package/@whiskeysockets/baileys (v7.0.0-rc13, 23 days ago) [HIGH]
- **Baileys Context7 docs** — `/whiskeysockets/baileys` (sendMessage API, connection.update events, useMultiFileAuthState) [HIGH]
- **n8n Context7 docs** — `/n8n-io/n8n-docs` (Webhook node, Schedule Trigger cron expressions, MySQL node) [HIGH]
- **n8n Docker Hub** — image built 2026-06-10, 3 days ago [HIGH]
- **Vercel Edge Network** — https://vercel.com/docs/edge-network/regions (São Paulo gru1 region, 126 PoPs) [HIGH]
- **MySQL Docker Hub** — https://hub.docker.com/_/mysql (8.4.9 LTS, 9.7.0 latest) [HIGH]
- **npm registry** — express@5.2.1, redis@6.0.0, pino@10.3.1, qrcode@1.5.4 (2026-06-13) [HIGH]
- **Docker version** — Engine 29.2.1, Compose v5.1.0 [HIGH]
- **Node.js** — v24.16.0 [HIGH]
