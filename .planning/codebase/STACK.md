# Technology Stack

**Analysis Date:** 2026-06-13

## Languages

**Primary:**
- TypeScript 5.7-5.9 - OpenWA API backend (`openwa/src/`), Dashboard frontend (`openwa/dashboard/`), SDK (`openwa/sdk/javascript/`)
- JavaScript (ES2023) - Baileys WhatsApp service (`baileys-service/index.js`), n8n workflow Code nodes (`n8n-workflows/*.json`)
- PHP - Landing page (`landing-salon/index.php`), API relay scripts (`landing-salon/api/`), Easy!Appointments core

**Secondary:**
- PowerShell 5.1 - Health check scripts (`scripts/health-check.ps1`), stack verification (`scripts/check-stack.ps1`), MySQL backup (`scripts/backup-mysql.ps1`)
- YAML - Docker Compose files, Traefik configuration, Cloudflare Tunnel config
- JSON - n8n workflow definitions, OpenCode configuration

## Runtime

**Environment:**
- Node.js 22 (OpenWA API, Dashboard) — `openwa/Dockerfile`
- Node.js 20 (Baileys service) — `baileys-service/Dockerfile`
- PHP 8.x (Apache-based, via Easy!Appointments Docker image `alextselegidis/easyappointments:latest`) — `easyappointments/Dockerfile`
- Docker (container orchestration) — all services containerized

**Package Manager:**
- npm (primary, per `package.json` in each service)
- Lockfile: `package-lock.json` (present in all Node.js services)

## Frameworks

**Core:**
- NestJS 11 (`@nestjs/core` ^11.1.21) - OpenWA API backend framework — `openwa/package.json`
- React 19 - OpenWA Dashboard frontend (`openwa/dashboard/package.json`)
- Vite 8 - Dashboard build tool — `openwa/dashboard/package.json`
- Express 4 (`express` ^4.21.0) - Baileys WhatsApp service HTTP layer — `baileys-service/package.json`
- Easy!Appointments (PHP) - Booking/reservation system, custom Docker image — `easyappointments/Dockerfile`

**Automation:**
- n8n (`n8nio/n8n:latest`) - Workflow automation engine, triggered via schedules + webhooks — `easyappointments/docker-compose.yml` (n8n service)

**Testing:**
- Jest 30 - OpenWA test runner, configured in `openwa/package.json`
- ts-jest 29 - TypeScript preprocessor for Jest
- Supertest 7 - HTTP assertions in tests

**Build/Dev:**
- TypeScript compiler (`tsc`) - Build for OpenWA API, Dashboard, SDK
- NestJS CLI (`@nestjs/cli` ^11.0.0) - NestJS code generation and build
- concurrently - Run API + dashboard in dev mode
- source-map-support - Debug source maps in production

## Key Dependencies

**Critical (OpenWA API):**
- `whatsapp-web.js` ^1.26.1-alpha.3 - Puppeteer-based WhatsApp Web engine — `openwa/package.json`
- `typeorm` ^0.3.29 - ORM for SQLite/PostgreSQL data layer
- `@nestjs/typeorm` ^11.0.0 - NestJS-TypeORM integration
- `@nestjs/swagger` ^11.4.3 - OpenAPI/Swagger documentation
- `@nestjs/throttler` ^6.5.0 - Rate limiting
- `helmet` ^8.0.0 - HTTP security headers
- `class-validator` ^0.15.1 / `class-transformer` ^0.5.1 - DTO validation
- `bullmq` ^5.76.10 - Redis-backed job queues
- `@nestjs/bullmq` ^11.0.4 - NestJS-BullMQ integration
- `socket.io` ^4.8.3 / `@nestjs/platform-socket.io` ^11.1.21 - WebSocket real-time events
- `dockerode` ^5.0.0 - Docker API client for container orchestration
- `archiver` ^8.0.0 / `tar-stream` ^3.2.0 - Backup/export utilities

**Critical (Baileys Service):**
- `@whiskeysockets/baileys` ^6.7.23 - WhatsApp Web multi-device socket library — `baileys-service/package.json`
- `qrcode` ^1.5.4 - QR code generation for WhatsApp pairing
- `redis` ^4.7.0 - Redis client for message queuing
- `pino` ^9.5.0 - Structured JSON logging

**Critical (Dashboard):**
- `@tanstack/react-query` ^5.100.10 - Server state management
- `@tanstack/react-table` ^8.21.3 - Data table component
- `react-router-dom` ^7.15.1 - Client-side routing
- `i18next` ^26.2.0 / `react-i18next` ^17.0.8 - Internationalization
- `lucide-react` ^1.16.0 - Icon library
- `socket.io-client` ^4.8.3 - WebSocket client for real-time updates

**Infrastructure:**
- `pg` ^8.21.0 - PostgreSQL driver (optional, for PostgreSQL mode)
- `sqlite3` ^5.1.7 - SQLite native bindings
- `ioredis` ^5.9.3 - Redis client (OpenWA)
- `@aws-sdk/client-s3` ^3.1048.0 - S3-compatible storage (MinIO/AWS)

## Configuration

**Environment:**
- Environment variables loaded in priority order (highest to lowest): Process env → `.env` → `data/.env.generated` — `openwa/src/main.ts`
- Configuration centralized in `openwa/src/config/configuration.ts` using `@nestjs/config` ^4.0.2
- Default env values provided inline in `configuration.ts` for all settings
- `.env.example` and `.env.minimal` reference files in `openwa/`

**Build:**
- `tsconfig.json` — `openwa/tsconfig.json` (target ES2023, module nodenext, decorators enabled)
- `tsconfig.json` — `openwa/dashboard/tsconfig.json` (React JSX, ESM modules)
- `tsconfig.json` — `openwa/sdk/javascript/tsconfig.json` (library output)
- `jest` config inline in `openwa/package.json`
- ESLint flat config (v10) used in all TypeScript projects

**Docker:**
- `docker-compose.yml` at `easyappointments/docker-compose.yml` — main stack: MySQL, Easy!Appointments, Redis, n8n, Mailpit, Baileys (legacy), OpenWA
- `docker-compose.yml` at `openwa/docker-compose.yml` — OpenWA with profiles: traefik, postgres, redis, minio
- `docker-compose.dev.yml` at `openwa/docker-compose.dev.yml` — development minimal setup

## Platform Requirements

**Development:**
- Docker Engine (all services)
- Node.js 20+ (for local development outside Docker)
- npm 9+ (package management)
- PowerShell 5.1+ (for management scripts on Windows)

**Production:**
- Docker host (VPS or local server)
- Domain with DNS management (planned: `tuahora.com.ar`)
- Cloudflare Tunnel (`cloudflared` client) for secure public exposure without open ports — see `cloudflare-tunnel.md`
- External mail relay (Mailpit for dev only; production needs real SMTP)

---

*Stack analysis: 2026-06-13*
