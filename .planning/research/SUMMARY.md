# Project Research Summary

**Project:** TuAhora — Sistema de turnos online para pequeños negocios
**Domain:** Online appointment booking for Argentine small businesses with WhatsApp-first communication
**Researched:** 2026-06-13
**Confidence:** HIGH

## Executive Summary

TuAhora is a **managed appointment booking service** for small Argentine businesses (pilot: nail salon in Chamical, La Rioja). Instead of asking non-technical business owners to configure Calendly or manage a Google Calendar, an "agente local" (developer) sets up and maintains the entire stack. The core differentiator is **WhatsApp-first automation** — Argentina runs on WhatsApp, and TuAhora brings booking confirmations, reminders, cancellations, and rescheduling into that channel rather than email or a separate app.

The recommended architecture is a **hub-and-spoke pattern centered on n8n** as the workflow orchestrator, with **Easy!Appointments 1.6.0** as the booking engine of record (REST API, MySQL 8.4, PHP 8.4), **Baileys v6.7.23** (pinned, NOT v7) as the WhatsApp bridge, and a **vanilla HTML/CSS landing page** (<200KB, Vercel-hosted, mobile-first) serving the Instagram → landing → booking funnel. Everything runs in Docker Compose with persistent named volumes. The stack avoids SaaS dependencies (no Twilio, no Meta WhatsApp Cloud API, no VPS for MVP) keeping operational costs near zero until paying clients exist.

The **existential risk is WhatsApp account bans** (Baileys is reverse-engineered, unsupported by Meta). This is mitigated by: dedicated phone number (never the owner's personal number), 2-4 week manual warm-up period, strict rate limiting (10-20 messages/minute), session persistence, and a documented fallback manual mode so the business can still operate if the bot goes down. Another critical finding is that **Easy!Appointments natively supports webhooks** via `/api/v1/webhooks` (18 action types including `appointment_save`) — the current polling-based WF-1 design (every 2 minutes) is unnecessary and should be replaced with real-time webhook triggers during Phase 2/3.

## Key Findings

### Recommended Stack

The stack prioritizes stability over novelty. All version pins are conservative: Baileys stays on v6 (v7 has breaking changes in release candidate), Express stays on v4 (v5 changes error handling), Redis npm client stays on v4 (v6 changes API). MySQL should upgrade from 8.0 to 8.4 LTS (supported through 2032). n8n uses `latest` Docker image with good stability track record. The landing page deliberately avoids React, Tailwind, Bootstrap, and static site generators — vanilla HTML/CSS with system fonts keeps the page under 200KB on slow Argentine mobile networks.

**Core technologies:**
- **Easy!Appointments 1.6.0**: Booking engine (REST API, webhooks, Spanish UI, ALTCHA CAPTCHA) — mature open-source PHP app, already running, 10+ years development
- **Baileys v6.7.23 (pinned)**: WhatsApp WebSocket bridge — lightweight (no headless browser), no cost, active community. v7 migration deferred to v2
- **n8n (latest Docker)**: Workflow orchestrator — visual workflow editor, built-in Schedule/Webhook/MySQL/HTTP nodes, easier to maintain than custom scripts
- **MySQL 8.4 (LTS)**: Booking data persistence — upgrade from existing 8.0, LTS support to 2032
- **Redis 7 (Alpine)**: Message queue for WhatsApp delivery — decouples HTTP from WebSocket, enables retry logic
- **Vanilla HTML/CSS on Vercel (Hobby)**: Landing page — <200KB target, mobile-first, no build step, no JS frameworks, São Paulo CDN edge

### Expected Features

All 14 table stakes are covered by Easy!Appointments out of the box — no custom code needed, only proper configuration. The value proposition lives in the WhatsApp differentiators.

**Must have (MVP — table stakes + 2 differentiators):**
- Service catalog (services with name, duration, price in ARS) — EA native
- Availability discovery and booking flow — EA native, embedded via iframe
- Booking confirmation via WhatsApp (WF-1) — THE killer feature, turns silent booking into immediate reassurance
- WhatsApp 24h reminder (WF-2) — directly reduces no-shows, WA messages read within minutes in Argentina
- Provider configuration (working hours, breaks) — EA native
- Booking rules (minimum notice, future limit, phone required) — EA native
- Spanish interface + ARS currency — EA supports 30+ languages
- Mobile-responsive booking page (Instagram-optimized) — primary traffic source
- Landing page deployed on Vercel — professional online presence without owner effort

**Should have (post-MVP differentiators):**
- WhatsApp cancellation (WF-3) — client texts "CANCELAR" to cancel, zero friction. Currently handled manually by owner
- WhatsApp rescheduling (WF-4) — client texts "CAMBIAR" to reschedule. Most complex workflow, highest edge case count
- Service combos/packages — upsells without owner intervention
- Branded landing page per client — professional presence, Instagram-optimized

**Defer to v2 or later:**
- Online payments (MercadoPago, Stripe) — Argentina runs on cash/transfer, not expected by pilot clients
- Multiple professionals per business — explicitly out of scope for pilot
- Custom domains per client — DNS/SSL infrastructure not needed when link goes in Instagram bio
- Native mobile app — small business clients won't download an app, they use WhatsApp
- Complex analytics dashboard — salon owner doesn't want metrics, wants to stop managing appointments manually
- Google Calendar sync — requires owner to have/configure Google account, breaks zero-config promise
- Jitsi/Google Meet video links — irrelevant for physical-service businesses

### Architecture Approach

TuAhora follows a **hub-and-spoke orchestration pattern** with n8n as the central coordinator and Easy!Appointments as the source of truth. This is a polyglot, containerized architecture (Docker Compose) optimized for single-tenant simplicity with documented multi-tenant upgrade paths. Four key integration patterns emerge: (1) **webhook-driven confirmation** (replace polling), (2) **incoming message fan-out** via Baileys webhook posting to multiple n8n endpoints, (3) **Redis queue** for WhatsApp delivery decoupling, and (4) **n8n staticData** for simple per-workflow state.

**Major components:**
1. **Easy!Appointments (:8080)** — Booking engine of record. Manages appointments, customers, services, providers. Exposes REST API with Basic Auth + webhooks. Embedded via iframe in landing page.
2. **n8n (:5678)** — Workflow orchestrator. All automation logic lives here. 4 workflows: WF-1 (confirmation via webhook, not polling), WF-2 (daily reminder cron), WF-3 (cancel webhook), WF-4 (reschedule webhook). Communicates with EA API and Baileys HTTP endpoints.
3. **Baileys Service (:3001)** — WhatsApp bridge. Thin translation layer (HTTP ↔ WhatsApp WebSocket). No business logic. Endpoints: `/health`, `/qr`, `/send-text`, `/send-reminder`. Forwards incoming messages to n8n webhooks.
4. **MySQL 8.4 (:3306)** — Persistent storage for all EA data. Accessed ONLY by Easy!Appointments (never directly by n8n — anti-pattern).
5. **Redis 7 (:6379)** — Message queue for WhatsApp delivery. Decouples HTTP request from WebSocket delivery, enables retry.
6. **Landing Page (Vercel)** — Static HTML/CSS marketing page. Embeds EA booking iframe. Instagram-optimized. Completely decoupled from Docker stack.

**Critical architecture decision to resolve:** The system currently has TWO WhatsApp bridges running (Baileys on port 3001 AND OpenWA on port 2785). n8n workflows reference OpenWA's API format while documentation describes Baileys. **Consolidate on Baileys** (no Puppeteer, lighter memory, active maintenance). Remove OpenWA from docker-compose.yml. Update all n8n workflow URLs.

### Critical Pitfalls

Research identified 12 pitfalls across 4 severity levels. The top 5 that could kill the project:

1. **WhatsApp Account Ban (EXISTENTIAL)** — Baileys is reverse-engineered, unsupported. Meta bans detected-server-pattern accounts. Prevention: dedicated phone number (never owner's personal), 2-4 week manual warm-up, strict rate limiting (10-20 msg/min), session persistence via Docker volumes, documented contingency plan with manual mode fallback.

2. **Polling When Webhooks Exist (UNNECESSARY COMPLEXITY)** — Easy!Appointments has native webhooks via `/api/v1/webhooks` with 18 action types including `appointment_save`. The current WF-1 polls every 2 minutes adding latency, load (720 queries/day), and fragile state tracking. Prevention: Replace WF-1 with webhook trigger during Phase 2/3 configuration.

3. **Non-Technical Owner Cannot Self-Recover (SUPPORT HELL)** — Any failure requires developer intervention. If dev is unavailable (weekend, vacation), business can't accept automated bookings. Prevention: graceful degradation (EA works without bot), documented manual emergency mode for owner, health dashboard showing component status, Docker auto-restart (`restart: unless-stopped`), proactive error alerting.

4. **Phone Number as Identifier Without Validation** — Argentine phone formats are inconsistent. Client enters invalid number, WhatsApp message fails silently, neither client nor owner knows. Prevention: frontend phone validation (10 digits without prefix or 13 with +54), server-side normalization in n8n, delivery verification with retry, email confirmation as safety net.

5. **Two WhatsApp Bridges Active** — Resource waste, confusion, two points of failure. Prevention: Consolidate on Baileys. Remove OpenWA. Update all n8n workflow endpoints.

**Additional moderate pitfalls requiring attention:**
- Timezone drift (Docker UTC vs Argentina `America/Argentina/La_Rioja`, no DST)
- EA customization breaking future upgrades (use admin panel, not file edits)
- Session/Redis data loss on Docker restart (named volumes for ALL stateful services)
- n8n workflow state without persistence (solved by migrating to webhooks)
- Instagram WebView compatibility (test in REAL WebView, not just Chrome DevTools)
- Keyword commands conflicting with natural language (use case-insensitive regex + two-step confirmation)

## Implications for Roadmap

Based on research, suggested phase structure aligned with dependency graph and pitfall prevention schedule:

### Phase 0: Foundation (Stack Hardening)
**Rationale:** The stack is partially running but has several configuration gaps that must be closed before anything else. Persistent volumes, timezone alignment, and tunnel access are prerequisites for the pilot client to use the system even during testing.

**Delivers:**
- docker-compose.yml with named volumes for ALL stateful services (mysql_data, redis_data, baileys_auth, n8n_data)
- `TZ=America/Argentina/La_Rioja` on all services
- MySQL upgrade from 8.0 to 8.4 LTS
- Development tunnel (ngrok or Cloudflare) so pilot client can access EA booking page and admin panel
- Weekly backup script (MySQL dump + Baileys auth backup)
- Docker healthchecks with `restart: unless-stopped` on all services
- OpenWA service REMOVED from docker-compose.yml

**Avoids:** Pitfall #5 (timezone), Pitfall #7 (volume data loss), Pitfall #12 (no tunnel), Two-bridge confusion

### Phase 1: Booking Surface (Landing + Booking Funnel)
**Rationale:** The booking page is the customer-facing surface. It must work perfectly on mobile Instagram WebView before any automation is built. This phase is independent of the Docker stack and can run in parallel to Phase 0.

**Delivers:**
- Mobile-first landing page (HTML/CSS vanilla, <200KB, Vercel deploy)
- iframe embed of Easy!Appointments booking page
- Instagram WebView compatibility verified (test on REAL WebView, not just Chrome DevTools)
- "Abrir en navegador" fallback button for WebView issues
- System fonts (no Google Fonts) to stay under 200KB on slow mobile networks
- Spanish-only interface verified
- End-to-end booking flow: Instagram bio link → landing → service selection → date/time → contact info → confirmation in EA

**Implements:** LAND-01 through LAND-06 features

**Avoids:** Pitfall #9 (Instagram WebView), Pitfall #11 (landing weight), Pitfall #6 (EA customization — use admin panel only)

### Phase 2: Configuration (EA Setup + Rules + Webhooks)
**Rationale:** The booking engine needs proper configuration before automation can be layered on top. This is where the critical webhook discovery is implemented — replacing the polling design before any workflows are built.

**Delivers:**
- Service catalog configured (manicura, pedicura, kapping, nail art, combos with ARS prices)
- Provider configured (Laura's working hours, breaks, days off)
- Booking rules (minimum notice, future limit, phone mandatory, cancellation window)
- ALTCHA CAPTCHA enabled for spam protection
- **EA webhook configured**: `appointment_save` → n8n webhook endpoint with `secretToken` authentication
- Phone field validation in booking form (10 or 13 digit format)
- Spanish translation completeness verified across all labels
- ARS currency display verified
- Anti-features explicitly NOT configured (no Google Calendar, no Jitsi/Meet, no email-only notifications)

**Implements:** All 14 table stakes features via EA configuration

**Avoids:** Pitfall #2 (polling → webhook), Pitfall #4 (phone validation), Pitfall #5 (timezone), Pitfall #6 (EA code modifications)

### Phase 3: WhatsApp Automation (The Differentiator)
**Rationale:** This is the core value proposition. WF-1 and WF-2 are must-have for MVP; WF-3 and WF-4 can be deferred to post-MVP. The WhatsApp ban risk must be mitigated BEFORE automation activates. The webhook setup from Phase 2 eliminates polling complexity.

**Delivers (MVP — Must have):**
- Baileys WhatsApp connection established with dedicated phone number
- 2-4 week manual warm-up period completed before enabling automation
- Rate limiting: `createBufferedFunction`, max 10-20 msg/minute
- Session persistence verified (auth survives Docker restart)
- **WF-1 (webhook-based confirmation)**: EA `appointment_save` webhook → n8n → format Spanish message → Baileys sends confirmation. Real-time, no polling, no state to maintain
- **WF-2 (daily reminder)**: n8n Schedule Trigger (cron: daily 8 AM ART) → query tomorrow's appointments → Baileys sends reminder
- Phone number normalization (any format → `54XXXXXXXXXX` international)
- Message delivery verification (sent, delivered, read status)
- Error workflow: alerts developer if Baileys disconnects, session expires, or messages fail

**Delivers (Post-MVP — Deferred):**
- WF-3 (cancel by WhatsApp): keyword "CANCELAR" with case-insensitive regex + two-step confirmation
- WF-4 (reschedule by WhatsApp): keyword "CAMBIAR" with same pattern, sends booking link after cancellation

**Implements:** WHAT-01, WHAT-02 (MVP); WHAT-03, WHAT-04 (deferred)

**Avoids:** Pitfall #1 (WhatsApp ban — dedicated number, warm-up, rate limiting), Pitfall #4 (phone validation), Pitfall #8 (webhooks eliminate state persistence problem), Pitfall #10 (keyword matching — two-step confirmation, regex)

### Phase 4: Business Artifacts + Operations
**Rationale:** Independent of all other phases. The local agent model needs professional deliverables and the owner needs troubleshooting documentation. This can be built in parallel to any phase.

**Delivers:**
- 1-page owner guide: how to use the system, how to check if it's working, manual emergency mode
- Service contract template for future clients
- Commercial proposal PDF
- Health dashboard (simple green/red component status page for owner)
- Documented contingency plan: what to do if bot banned, how to migrate to new number, pre-written client notification message
- Troubleshooting checklist: common failure scenarios and recovery steps

**Implements:** NEGO-01 through NEGO-04 features

**Avoids:** Pitfall #3 (owner cannot self-recover — addressed by guide + dashboard + manual mode)

### Phase Ordering Rationale

- **Phase 0 first** because persistent volumes, timezone alignment, and tunnel access are prerequisites for ANY real usage by the pilot client. Fixing these after building on top risks data loss and timezone bugs that are hard to debug.
- **Phase 1 can run parallel to Phase 0** because it's independent (static files on Vercel, no Docker dependency beyond knowing EA's URL for the iframe).
- **Phase 2 must precede Phase 3** because WhatsApp automation depends on a properly configured booking engine, and the webhook should be set up during EA configuration, not retrofitted.
- **Phase 3 splits into MVP (WF-1, WF-2) and post-MVP (WF-3, WF-4)** based on FEATURES.md recommendation: confirmation and reminders are the killer features; cancellation and rescheduling are nice-to-haves that the owner already handles manually.
- **Phase 4 is independent** and can run anytime. Recommended to run in parallel with Phase 3 so business artifacts are ready when automation goes live.
- **Phase 3 warm-up period (2-4 weeks)** is a hard constraint. Automation cannot go live until the WhatsApp number has established human-use patterns.

### Research Flags

**Phases needing deeper research during planning (`/gsd-research-phase`):**

- **Phase 3 (WhatsApp Automation):**
  - Baileys anti-ban middleware (`kobie3717/baileys-antiban`) — evaluate integration approach during planning
  - Easy!Appointments webhook payload format — verify exact JSON structure sent for `appointment_save` events
  - WhatsApp phone number format for Argentina (`54XXXXXXXXXX@s.whatsapp.net`) — verify Baileys accepts this format
  - Baileys v6 `createBufferedFunction` API — verify the exact API for rate limiting implementation
  - n8n webhook trigger with `secretToken` authentication — verify configuration pattern

- **Phase 1 (Landing):**
  - Instagram WebView limitations for iframes — specific CSS/JS features blocked, form POST behavior
  - Vercel deploy workflow for static HTML — verify `vercel --prod` command and project configuration

**Phases with standard patterns (skip research-phase):**

- **Phase 0 (Foundation):** Well-documented Docker Compose patterns, MySQL upgrade is standard, ngrok/Cloudflare Tunnel are well-documented. Standard infrastructure.

- **Phase 2 (Configuration):** Easy!Appointments admin panel is point-and-click. REST API is well-documented (Context7). Webhook setup follows standard REST patterns. No novel research needed — just correct configuration.

- **Phase 4 (Business Artifacts):** Non-technical deliverables. No software research needed. Templates and business writing.

## Confidence Assessment

| Area | Confidence | Notes |
|------|------------|-------|
| Stack | HIGH | All technologies verified via official sources (npm registry, Docker Hub, GitHub releases, Context7 docs). Version numbers confirmed current as of 2026-06-13. Baileys v6 pinning, MySQL upgrade path, and n8n stability all verified. |
| Features | HIGH | Easy!Appointments feature set verified via official site, v1.6.0 release notes, demo instance, and REST API docs. WhatsApp workflows validated against PROJECT.md and existing n8n workflow files. Argentina-specific insights from domain knowledge (MEDIUM confidence on market behavior). |
| Architecture | HIGH | Existing running codebase confirms component boundaries. REST API docs and docker-compose.yml provide concrete verification. Two-bridge issue confirmed via actual workflow files and container inspection. Data flows verified against working code. |
| Pitfalls | HIGH | WhatsApp ban risk documented via 3 active GitHub issues (#1869, #2309, #2340). Webhook discovery verified via official Easy!Appointments API docs. Timezone, session persistence, and phone validation pitfalls confirmed against Docker/PHP documentation. |

**Overall confidence: HIGH.** All critical findings have been verified against official sources (Context7 docs, GitHub releases, npm registry, Docker Hub). The project has an existing running codebase that validates the architecture. The only MEDIUM confidence areas are Argentina market behavior and pricing estimates (subject to exchange rate fluctuation) — neither of which are blocked by research uncertainty.

### Gaps to Address

- **Easy!Appointments webhook payload format for `appointment_save`:** The webhook feature is confirmed to exist and have 18 action types, but the exact JSON payload structure needs to be verified during Phase 2 planning. Mitigation: test webhook delivery in dev environment with Mailpit capturing the POST body, or inspect EA source code for webhook controller.

- **Baileys anti-ban middleware effectiveness:** `kobie3717/baileys-antiban` is community-maintained and its effectiveness against current Meta detection patterns is unverified. Mitigation: evaluate during Phase 3 planning, implement core rate limiting and warm-up regardless of middleware choice.

- **Instagram WebView iframe behavior:** Instagram's WebView restrictions change with app updates and vary between Android (Chrome WebView) and iOS (WKWebView). Cannot be fully validated via documentation — requires real-device testing. Mitigation: include real-device Instagram WebView testing in Phase 1 before deploy.

- **MySQL 8.0 → 8.4 upgrade path for existing EA data:** The project currently runs MySQL 8.0 with live data. While 8.4 is backward-compatible, the upgrade procedure (dump/restore or in-place upgrade) needs to be tested. Mitigation: backup existing MySQL data before upgrade, test on copy first.

- **Cloudflare Tunnel vs ngrok decision for dev/prod:** Research didn't definitively pick one. ngrok is simpler for development but has session time limits on free tier. Cloudflare Tunnel is free and permanent but requires Cloudflare account and domain setup. Mitigation: use ngrok for Phase 0 development, evaluate Cloudflare Tunnel for production in v2 planning.

## Sources

### Primary (HIGH confidence)
- **Context7: Easy!Appointments REST API docs** (`/alextselegidis/easyappointments`) — Full API surface, webhook endpoint confirmed with 18 action types including `appointment_save`, `appointment_delete`, `customer_save`
- **Context7: Baileys library docs** (`/whiskeysockets/baileys`) — `sendMessage` API, `connection.update` events, `createBufferedFunction` for rate limiting, `useMultiFileAuthState` for session persistence, disconnect reason codes
- **Context7: n8n documentation** (`/n8n-io/n8n-docs`) — Webhook trigger node, Schedule Trigger cron expressions, MySQL node, Code node with `$workflow.staticData`, error workflows
- **Easy!Appointments v1.6.0 Release** (GitHub, 2026-05-27) — ALTCHA CAPTCHA, PHP 8.4 support, Jitsi/Google Meet, GDPR, CalDAV improvements
- **Easy!Appointments Docker Hub** (updated 17 days ago) — Official Docker image, verified deployment
- **npm registry** (2026-06-13) — All package versions confirmed current: `@whiskeysockets/baileys@6.7.23`, `express@4.21.0`, `redis@4.7.0`, `pino@9.5.0`, `qrcode@1.5.4`
- **MySQL Docker Hub** — `mysql:8.4` LTS confirmed, `mysql:9.7` latest available
- **Vercel Edge Network docs** — São Paulo (gru1) region confirmed, 126 PoPs, Hobby tier specs
- **Existing project files** — `docker-compose.yml`, n8n workflow JSON exports, `baileys-service/index.js`, `docs/Arquitectura.md`
- **GitHub WhiskeySockets/Baileys issues** — #1869 (mass ban wave October 2025), #2309 (permanent ban on production server), #2340 (session rotation causing bans)

### Secondary (MEDIUM confidence)
- **Domain knowledge: Argentina small business** — WhatsApp penetration (95%+), Instagram funnel dominance, ARS payment preferences (cash/transfer), Chamical/La Rioja connectivity limitations
- **Pricing estimates** — VPS costs in ARS, exchange rate assumptions (subject to fluctuation)
- **Multi-tenancy scaling paths** — Inferred from architecture patterns, not production-tested for this specific stack

### Tertiary (LOW confidence — needs validation)
- **Baileys anti-ban middleware** (`kobie3717/baileys-antiban`) — Community package, unverified effectiveness against current Meta detection
- **Instagram WebView behavior** — Specific to app version and OS, requires real-device testing

---

*Research completed: 2026-06-13*
*Ready for roadmap: yes*
*Next: `/gsd-roadmap` (roadmap creation from research)*
