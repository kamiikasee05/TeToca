# Codebase Concerns

**Analysis Date:** 2026-06-13

## Tech Debt

### Hardcoded Credentials Across Multiple Files

- Issue: Passwords, API keys, and auth credentials are hardcoded in plain text across configuration files, source code, and scripts.
- Files: `easyappointments/docker-compose.yml`, `landing-salon/index.php`, `landing-salon/admin/index.php`, `scripts/backup-mysql.ps1`, `openwa/docker-compose.yml`, `openwa/src/modules/auth/auth.service.ts`
- Impact: Anyone with access to the repository or container logs can obtain production credentials. Rotation requires code changes and redeployment.
- Fix approach: Migrate all secrets to environment variables or a secrets manager (Docker secrets, `.env` files excluded from git). Add `.env` and credentials files to `.gitignore`. Use `docker-compose` secrets block or external secret management.

### Silent Error Handling / Empty Catch Blocks

- Issue: Multiple try/catch blocks silently swallow errors, hiding failures during operation.
- Files: `baileys-service/index.js` (lines 93, 28, 99)
  - Line 93: `} catch {}` — webhook delivery failures silently discarded
  - Line 28: Redis error sets `redisClient = null` without logging reason
  - Line 99: Fatal error only logs message, doesn't notify monitoring
- Impact: Failed message deliveries, Redis outages, and webhook forwarding failures go undetected until user complaints.
- Fix approach: Log all caught errors with context (at minimum `logger.warn`). Add structured logging with error severity. Implement dead-letter queue for failed webhook deliveries.

### Stub / Unimplemented Methods in Production Adapter

- Issue: 11 methods in the primary WhatsApp adapter throw "not yet implemented" errors or return hardcoded empty results, yet they're exposed through the API layer.
- Files: `openwa/src/engine/adapters/whatsapp-web-js.adapter.ts`
  - `postTextStatus` (line 864), `postImageStatus` (line 869), `postVideoStatus` (line 874), `deleteStatus` (line 879)
  - `sendProduct` (line 908), `sendCatalog` (line 913)
  - `getCatalog` (line 887), `getProducts` (line 893), `getProduct` (line 902)
  - `getContactStatuses` (line 850), `getContactStatus` (line 856)
- Impact: Calling these API endpoints in production will throw unhandled exceptions. The Status and Catalog modules exist in `app.module.ts` but their underlying implementation is missing.
- Fix approach: Either implement the missing adapter methods or add feature flags/guards to return 501 Not Implemented gracefully. Document which API endpoints are functional vs. planned.

### Monolithic / Overgrown Files

- Issue: Several source files exceed reasonable length thresholds, making them difficult to maintain, test, and review.
- Files:
  - `openwa/src/engine/adapters/whatsapp-web-js.adapter.ts` (923 lines)
  - `openwa/src/modules/infra/infra.controller.ts` (733 lines)
  - `openwa/src/modules/docker/docker.service.ts` (544 lines)
  - `openwa/src/modules/session/session.service.ts` (540 lines)
  - `openwa/src/modules/message/message.service.ts` (419 lines)
- Impact: High cognitive load, increased risk of merge conflicts, difficult to unit test individual concerns.
- Fix approach: Extract sub-responsibilities into dedicated services. Example: Split the adapter into separate concern files (message adapter, status adapter, catalog adapter). Split `infra.controller.ts` into separate controllers for database config, Redis config, storage config.

### Development Defaults Leaking to Production Configuration

- Issue: Several default values are suitable for development but dangerous for production.
- Files:
  - `openwa/src/config/configuration.ts` (line 25): `synchronize: true` for main database — auto-creates/drops tables
  - `openwa/src/modules/auth/auth.service.ts` (line 31): Defaults to `dev-admin-key` in non-production environments, stored on disk at `data/.api-key`
  - `openwa/docker-compose.dev.yml` (line 18): `DATABASE_SYNCHRONIZE=true`
  - `openwa/traefik/traefik.yml` (line 4): `insecure: true` for Traefik dashboard
- Impact: Accidental schema drops on startup in production. Trivially guessable admin key if NODE_ENV is not set correctly.
- Fix approach: Remove `synchronize: true` default; require explicit opt-in. Generate random API keys always, log them on first run. Disable Traefik insecure dashboard in production profiles.

### Two Parallel WhatsApp Implementations

- Issue: Both Baileys (`baileys-service/`) and OpenWA (`openwa/`) provide WhatsApp connectivity. Baileys is marked `profiles: ['legacy']` but still built and deployed.
- Files: `easyappointments/docker-compose.yml` (line 103), `baileys-service/index.js`, `openwa/src/engine/adapters/whatsapp-web-js.adapter.ts`
- Impact: Duplicated logic, maintenance burden, unclear which service handles which n8n workflows. Baileys forwards to n8n, OpenWA exposes a full HTTP API — different architectures with overlap.
- Fix approach: Consolidate on one WhatsApp gateway (preferably OpenWA since Baileys is already marked legacy). Migrate n8n workflows to use OpenWA's HTTP API. Remove Baileys service once migration is complete.

### N8N Workflows: No Error Handling

- Issue: All four n8n workflows lack error handling, retry logic, or fallback paths. If Easy!Appointments API is down, workflows silently fail.
- Files: `n8n-workflows/WF1-confirmacion.json`, `n8n-workflows/WF2-recordatorio.json`, `n8n-workflows/WF3-cancelacion.json`, `n8n-workflows/WF4-reagendado.json`
- Impact: Booking confirmations, reminders, cancellations silently fail if Easy!Appointments is unreachable. Customers don't receive notifications.
- Fix approach: Add Error Trigger nodes to each workflow. Implement retry with exponential backoff on HTTP request nodes. Add notification to admin on persistent failures.

### N8N Using Unpinned Latest Image

- Issue: `easyappointments/docker-compose.yml` uses `n8nio/n8n:latest` without a pinned version.
- Files: `easyappointments/docker-compose.yml` (line 70)
- Impact: Automatic upgrades can introduce breaking changes or workflow incompatibilities without warning.
- Fix approach: Pin to a specific version tag (e.g., `n8nio/n8n:1.82.0`). Test upgrades in a staging environment before rolling to production.

## Known Bugs

### Redis Connection Never Recovers in Baileys

- Symptoms: After a Redis restart or network blip, the Baileys service falls back to synchronous HTTP delivery and never reconnects to Redis.
- Files: `baileys-service/index.js` (lines 21-31, 174-184)
- Trigger: Redis container restart, network partition between Baileys and Redis.
- Workaround: Restart the Baileys container manually. No automatic recovery exists.
- Fix: Implement Redis reconnection with exponential backoff. Periodically attempt reconnection when `redisClient` is null. Use Redis client's built-in `reconnectStrategy` option.

### API Key Written to Disk in Plaintext

- Symptoms: The master API key is stored in `data/.api-key` with no encryption, readable by any process with filesystem access.
- Files: `openwa/src/modules/auth/auth.service.ts` (lines 11, 36-41)
- Trigger: First run of OpenWA (any environment).
- Fix: Use a proper secrets mechanism. At minimum, restrict file permissions (`0o600`). Prefer environment variable or Docker secret injection.

## Security Considerations

### Credentials in Version Control

- Risk: Multiple hardcoded credentials committed to the repository expose production systems if the repo is ever leaked.
- Files:
  - `easyappointments/docker-compose.yml`: `MYSQL_ROOT_PASSWORD: ea_root_secret`, `MYSQL_PASSWORD: ea_pass_2024`, `API_MASTER_KEY: tuahora_openwa_2024`
  - `landing-salon/index.php` (line 5): `kamiikasee:admin2024` for Easy!Appointments API basic auth
  - `landing-salon/admin/index.php` (line 7): `admin2024` admin panel password
  - `scripts/backup-mysql.ps1` (line 7): `ea_pass_2024`
  - `openwa/docker-compose.yml` (lines 54-55, 123-124, 163-164): MinIO and PostgreSQL default credentials
- Current mitigation: None — all values are in plain text in tracked files.
- Recommendations:
  1. Move all credentials to `.env` files and add to `.gitignore`
  2. Create `.env.example` templates with placeholder values
  3. Use Docker secrets or external vault for production
  4. Rotate all exposed credentials immediately

### Session Data Committed to Repository

- Risk: `cookies.txt` contains Easy!Appointments session cookies (`ea_session`, `csrf_cookie`) that could be used for session hijacking.
- Files: `cookies.txt`
- Current mitigation: None — file is tracked in git.
- Recommendations: Remove from version control immediately. Add `cookies.txt` and `*.txt` cookie files to `.gitignore`. Rotate the Easy!Appointments session.

### Docker Socket Mounted in OpenWA Container

- Risk: The OpenWA container has access to the Docker socket (`/var/run/docker.sock`), enabling container escape and host compromise.
- Files: `openwa/docker-compose.yml` (line 75), `openwa/Dockerfile` (lines 76-78: explicitly runs as root for this purpose)
- Current mitigation: Socket is mounted read-only (`:ro`). Container runs as root (non-root user disabled via comment).
- Recommendations: Use a Docker socket proxy (e.g., `tecnativa/docker-socket-proxy`) that exposes only necessary API endpoints. Never run as root — use the non-root user and add to `docker` group with socket proxy.

### WebSocket API Key in URL Query Parameters

- Risk: API key is transmitted in WebSocket URL query strings, exposing it in server logs, proxy logs, and browser history.
- Files: `openwa/dashboard/src/hooks/useWebSocket.ts` (lines 58-60)
- Current mitigation: Connection uses HTTPS in production (via Traefik), but the query param is still logged by intermediaries.
- Recommendations: Remove API key from query params. Use only the `auth` handshake property or the `X-API-Key` extra header (both already present). Query param is redundant and dangerous.

### OpenWA Database Synchronize Enabled by Default

- Risk: `synchronize: true` in TypeORM auto-creates/drops tables on every application startup. A misconfigured schema change could wipe production data.
- Files: `openwa/src/config/configuration.ts` (line 25 — main DB), `openwa/src/app.module.ts` (line 106 — data DB for SQLite)
- Current mitigation: PostgreSQL path disables synchronize (line 89). SQLite path has it enabled by default (line 106).
- Recommendations: Default to `synchronize: false` everywhere. Require explicit opt-in with `DATABASE_SYNCHRONIZE=true`. Always run migrations manually in production.

### Traefik Dashboard Exposed Without Authentication

- Risk: Traefik dashboard is enabled with `insecure: true` and exposed on port 8080, revealing internal routing configuration.
- Files: `openwa/traefik/traefik.yml` (lines 3-4)
- Current mitigation: Port 8080 mapped to `127.0.0.1` only (`openwa/docker-compose.yml` line 13).
- Recommendations: Add basic auth middleware to the dashboard. Use Traefik's `api.auth` configuration. Consider disabling dashboard entirely in production.

## Performance Bottlenecks

### Polling-Based Appointment Detection

- Problem: N8n workflows poll Easy!Appointments every 2 minutes for new appointments instead of using webhooks.
- Files: `n8n-workflows/WF1-confirmacion.json` (lines 10-19: `scheduleTrigger` with 120-second interval)
- Cause: Easy!Appointments may not natively support webhooks, requiring polling as a workaround.
- Impact: Up to 2-minute delay in booking confirmations. Constant load on Easy!Appointments API even when idle.
- Improvement path: Implement Easy!Appointments webhook/callback mechanism if available. Alternatively, reduce polling window to 30 seconds and add caching layer.

### SQLite for Concurrent Access

- Problem: OpenWA uses SQLite as the default data store, which has poor concurrent write performance and lacks connection pooling.
- Files: `openwa/src/app.module.ts` (lines 99-110), `easyappointments/docker-compose.yml` (line 130)
- Cause: Zero-config setup preference over performance.
- Impact: Under concurrent API usage (multiple webhook deliveries, message sends), SQLite's single-writer lock becomes a bottleneck.
- Improvement path: Use PostgreSQL for production deployments (already supported in configuration). Make PostgreSQL the recommended default in documentation, not just an option.

### In-Memory Engine Map Without Eviction

- Problem: Active WhatsApp engine instances are stored in an in-memory `Map` with no size limit or idle eviction.
- Files: `openwa/src/modules/session/session.service.ts` (line 32: `engines: Map<string, IWhatsAppEngine>`)
- Cause: No mechanism to offload or destroy idle engines.
- Impact: Memory grows linearly with number of sessions. Each WhatsApp-web.js engine runs a full Puppeteer/Chromium instance consuming ~100-300MB RAM.
- Improvement path: Implement idle timeout eviction (destroy engine after configurable idle period). Add session limits per instance. Document resource requirements per session.

## Fragile Areas

### WhatsApp-Web.js Adapter — Core Integration Point

- Files: `openwa/src/engine/adapters/whatsapp-web-js.adapter.ts` (923 lines), `openwa/src/engine/engine.factory.ts`, `openwa/src/engine/interfaces/whatsapp-engine.interface.ts`
- Why fragile: Depends on unofficial WhatsApp Web protocol (`whatsapp-web.js@^1.26.1-alpha.3` — alpha version). WhatsApp frequently changes their web client, which can break the library without notice. The adapter is the single point of failure for all messaging functionality.
- Safe modification: Never modify the adapter without testing against a real WhatsApp session. Keep the interface (`whatsapp-engine.interface.ts`) stable and adapter-specific logic contained.
- Test coverage: Zero tests for the adapter, engine factory, or engine interface. Only webhook and auth services have partial test coverage.

### Baileys Service — Single-File Node.js Microservice

- Files: `baileys-service/index.js` (209 lines — entire service in one file)
- Why fragile: All logic (Express server, WhatsApp client, Redis queue, n8n webhook forwarding) in a single file with no separation of concerns. No tests. No graceful shutdown handling. Depends on `@whiskeysockets/baileys@^6.7.23` — another unofficial WhatsApp library.
- Safe modification: Add logging before any change. Test with a staging WhatsApp number. Avoid changing the message forwarding format (n8n workflows depend on `{ phone, text, from }` JSON structure).
- Test coverage: None.

### Docker Compose Inter-Project Dependency

- Files: `easyappointments/docker-compose.yml` (baileys build context references `../baileys-service`)
- Why fragile: The primary `docker-compose.yml` in `easyappointments/` orchestrates the entire stack but references files outside its directory via relative paths. Moving or restructuring directories breaks the build.
- Safe modification: Keep `baileys-service/` and `landing-salon/` at their current locations relative to `easyappointments/`. Consider using Docker images instead of build contexts for cross-directory references.

### N8N Workflow Data Persistence

- Files: `easyappointments/docker-compose.yml` (line 83: `DB_TYPE: sqlite`)
- Why fragile: N8n uses SQLite for execution data. If the `n8n_data` volume is lost, all workflow configurations, credentials, and execution history are lost permanently.
- Safe modification: Regularly export workflows as JSON files (already done in `n8n-workflows/`). Back up the n8n data volume as part of the backup strategy. Consider using PostgreSQL for n8n in production.
- Test coverage: No automated testing of workflows exists.

## Scaling Limits

### WhatsApp Session Memory

- Current capacity: Each WhatsApp session (Puppeteer + Chromium) consumes ~150-300MB RAM. No documented limit enforced.
- Limit: On a 2GB RAM VPS, approximately 3-5 concurrent sessions before OOM.
- Scaling path: Move to a dedicated session server with more RAM. Implement per-session resource monitoring. Add configurable max sessions limit.

### Easy!Appointments MySQL

- Current capacity: Single MySQL 8.0 instance with no replication or connection pooling beyond defaults.
- Limit: Suitable for single-salon use (current scope). Would need optimization for multi-tenant or high-traffic scenarios.
- Scaling path: Add connection pooling. Implement read replicas if needed. Current architecture is adequate for the stated use case.

### SQLite Write Contention

- Current capacity: Single-writer lock limits concurrent API writes to ~50-100 writes/second.
- Limit: Becomes bottleneck under heavy concurrent webhook processing or bulk messaging.
- Scaling path: Migrate to PostgreSQL (already supported in `data-source.ts`). Add Redis queue (already supported via `QUEUE_ENABLED` flag) to buffer writes.

## Dependencies at Risk

### whatsapp-web.js (Alpha Version)

- Package: `whatsapp-web.js@^1.26.1-alpha.3`
- Risk: Alpha/unstable release. WhatsApp actively blocks unofficial clients. Library may stop working after WhatsApp protocol changes.
- Files: `openwa/package.json` (line 68), `openwa/src/engine/adapters/whatsapp-web-js.adapter.ts`
- Impact: Complete loss of WhatsApp messaging capability if the library breaks.
- Migration plan: Monitor upstream releases closely. Have a fallback plan (the legacy Baileys service can serve as backup). Consider WhatsApp Business API as an official alternative for production.

### @whiskeysockets/baileys (Unofficial WhatsApp Library)

- Package: `@whiskeysockets/baileys@^6.7.23`
- Risk: Unofficial library subject to WhatsApp detection and blocking. Version `^6.7.23` may have breaking changes in future minor releases.
- Files: `baileys-service/package.json` (line 10), `baileys-service/index.js`
- Impact: Loss of the legacy WhatsApp fallback path.
- Migration plan: Since this is already marked `profiles: ['legacy']`, prioritize consolidating on OpenWA. Test OpenWA thoroughly before removing Baileys dependency.

### N8N Latest Tag

- Package: `n8nio/n8n:latest`
- Risk: Unpinned Docker image can introduce breaking workflow changes or API incompatibilities.
- Files: `easyappointments/docker-compose.yml` (line 70)
- Impact: Workflows may silently break after a `docker compose pull && docker compose up -d`.
- Migration plan: Pin to a specific version. Test upgrades in a staging environment. Export workflows as JSON before any upgrade.

## Missing Critical Features

### No Backup Verification

- Problem: `backup-mysql.ps1` creates backups but never verifies them. There is no automated restore test.
- Files: `scripts/backup-mysql.ps1`
- Blocks: Confidence in disaster recovery. Silent backup corruption would only be discovered during an actual emergency.
- Recommendation: Add a `--verify` flag that restores the backup to a temporary database and runs integrity checks. Schedule a weekly verification.

### No Health Alerting for Baileys Service

- Problem: `health-check.ps1` checks if the Baileys container is running but does not verify WhatsApp connectivity.
- Files: `scripts/health-check.ps1` (line 95), `baileys-service/index.js` (lines 112-114)
- Blocks: Ability to detect WhatsApp disconnection. The `/health` endpoint returns `{ status: 'ok', whatsapp: connectionState }` but the health check script only checks HTTP 200, not the whatsapp state field.
- Recommendation: Parse the health response JSON and alert if `whatsapp !== 'connected'`. Add WhatsApp state to monitoring dashboards.

### No Centralized Logging

- Problem: Each service logs independently. Baileys uses `pino`, OpenWA uses a custom NestJS logger, n8n uses its own logging. No aggregation point.
- Blocks: Debugging cross-service issues (e.g., "n8n → Baileys → WhatsApp" message flow).
- Recommendation: Implement a log aggregation stack (e.g., Loki + Grafana, or ELK). At minimum, ensure all services log in JSON format with consistent fields (timestamp, service, traceId).

## Test Coverage Gaps

### Critical Services With Zero Tests

- What's not tested:
  - `openwa/src/engine/adapters/whatsapp-web-js.adapter.ts` (923 lines, core messaging — 0 tests)
  - `openwa/src/modules/session/session.service.ts` (540 lines, session lifecycle — 1 empty spec file `session.service.spec.ts` with 313 lines of test infrastructure but no substantive tests)
  - `openwa/src/modules/docker/docker.service.ts` (544 lines — 0 tests)
  - `openwa/src/modules/infra/infra.controller.ts` (733 lines — 0 tests)
  - `openwa/src/modules/message/message.service.ts` (419 lines — 1 empty spec file)
  - `openwa/src/engine/engine.factory.ts` (134 lines — 0 tests)
  - `baileys-service/index.js` (209 lines — 0 tests)
  - All 4 n8n workflows (0 tests)
  - `landing-salon/` PHP files (0 tests)
- Risk: Changes to core messaging, session management, or infrastructure orchestration can break silently. No safety net for refactoring.
- Priority: **High** for `whatsapp-web-js.adapter.ts` and `session.service.ts`. Medium for `message.service.ts`.

### Very Low Coverage Thresholds

- Files: `openwa/package.json` (lines 116-123)
- Current thresholds: branches 10%, functions 12%, lines 15%, statements 15%
- Risk: Thresholds are set so low that they effectively enforce no coverage requirements. Any regression passes.
- Priority: Medium — gradually raise thresholds as test coverage improves. Start with critical modules at 60-80%.

---

*Concerns audit: 2026-06-13*
