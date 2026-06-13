# Codebase Structure

**Analysis Date:** 2026-06-13

## Directory Layout

```
E:\TUAHORA\                              # Project root (git repo)
├── .obsidian/                           # Obsidian vault configuration (plugins, settings)
├── .opencode/                           # OpenCode AI configuration (skills, agents)
├── .planning/                           # GSD workflow artifacts (plans, codebase docs, intel)
│   ├── backups/
│   ├── codebase/                        # ← Generated codebase map documents
│   └── intel/
├── baileys-service/                     # Baileys WhatsApp bot (Node.js, legacy)
│   ├── Dockerfile
│   ├── index.js                         # Single-file Express app (209 lines)
│   └── package.json
├── easyappointments/                    # Easy!Appointments Docker configuration
│   ├── .env                             # Environment variables (presence noted, not read)
│   ├── docker-compose.yml               # Master Compose file (all services, 148 lines)
│   └── Dockerfile                       # Custom EA image (X-Frame-Options removal)
├── landing-salon/                       # Custom PHP frontend + API gateway
│   ├── admin/                           # Admin panel (session-protected)
│   │   ├── dashboard.php                # Main admin SPA (802 lines, 6 tabs)
│   │   ├── index.php                    # Login page
│   │   └── logout.php                   # Session destroy + redirect
│   ├── api/                             # PHP API gateway (cURL proxies)
│   │   ├── admin-servicios.php          # Services CRUD (auth required)
│   │   ├── crear-turno.php              # Public: create appointment
│   │   ├── horarios.php                 # Public: get available time slots
│   │   ├── horarios-admin.php           # Admin: read/write working plan
│   │   ├── servicios.php                # Public: list services (passthrough)
│   │   ├── turnos-admin.php             # Admin: appointments CRUD
│   │   ├── whatsapp-qr.php              # Admin: proxy OpenWA QR status
│   │   ├── whatsapp-relay.php           # POST relay to OpenWA
│   │   └── whatsapp-send.php            # GET relay to OpenWA (with logging)
│   ├── assets/                          # Static assets directory (empty)
│   └── index.php                        # Public landing page (493 lines)
├── n8n-workflows/                       # n8n automation workflow definitions
│   ├── WF1-confirmacion.json            # Polls EA for new appointments → send WhatsApp
│   ├── WF2-recordatorio.json            # 24h reminder before appointment
│   ├── WF3-cancelacion.json             # WhatsApp chatbot: cancel appointment
│   └── WF4-reagendado.json              # WhatsApp chatbot: reschedule appointment
├── openwa/                              # OpenWA WhatsApp API Gateway (NestJS)
│   ├── .github/workflows/               # CI/CD workflows
│   ├── dashboard/                       # OpenWA admin dashboard (React, separate build)
│   │   ├── public/
│   │   └── src/
│   ├── docs/                            # OpenWA documentation
│   │   └── logo/
│   ├── sdk/                             # Client SDKs
│   │   ├── javascript/
│   │   └── python/
│   ├── src/                             # NestJS application source
│   │   ├── main.ts                      # Bootstrap: security headers, CORS, Swagger
│   │   ├── app.module.ts                # Root module (all feature modules)
│   │   ├── common/                      # Shared utilities, filters, interceptors
│   │   │   ├── cache/
│   │   │   ├── filters/
│   │   │   ├── interceptors/
│   │   │   ├── interfaces/
│   │   │   ├── security/
│   │   │   ├── services/                # Logger, shutdown, etc.
│   │   │   ├── storage/
│   │   │   ├── transformers/
│   │   │   └── utils/
│   │   ├── config/                      # Configuration module
│   │   ├── core/                        # Core framework (hooks, plugins)
│   │   │   ├── hooks/
│   │   │   └── plugins/
│   │   ├── database/                    # TypeORM migrations
│   │   │   └── migrations/
│   │   ├── engine/                      # WhatsApp engine abstraction
│   │   │   ├── adapters/
│   │   │   ├── interfaces/
│   │   │   └── types/
│   │   ├── modules/                     # Feature modules (NestJS-style)
│   │   │   ├── audit/                   # Audit logging entities
│   │   │   ├── auth/                    # API key authentication
│   │   │   ├── catalog/                 # WhatsApp Business catalog
│   │   │   ├── channel/                 # Channels/newsletters
│   │   │   ├── contact/                 # Contact management
│   │   │   ├── docker/                  # Docker integration
│   │   │   ├── events/                  # WebSocket real-time events
│   │   │   ├── group/                   # Group management
│   │   │   ├── health/                  # Health check endpoints
│   │   │   ├── infra/                   # Infrastructure settings
│   │   │   ├── label/                   # WhatsApp Business labels
│   │   │   ├── message/                 # Message sending + entities
│   │   │   ├── plugins/                 # Plugin API
│   │   │   ├── queue/                   # BullMQ job queue (conditional)
│   │   │   ├── session/                 # WhatsApp session management
│   │   │   ├── settings/                # Application settings
│   │   │   ├── stats/                   # Statistics dashboard
│   │   │   ├── status/                  # Status/stories API
│   │   │   └── webhook/                 # Webhook configuration + entities
│   │   └── plugins/                     # Plugin system
│   │       └── engines/
│   │           └── whatsapp-web-js/
│   ├── test/                            # Test files
│   ├── traefik/                         # Traefik reverse proxy config
│   ├── .env.example                     # Example env (presence noted)
│   ├── .prettierrc                      # Prettier config
│   ├── eslint.config.mjs                # ESLint config
│   ├── docker-compose.yml               # OpenWA standalone compose
│   ├── docker-compose.dev.yml           # OpenWA dev compose
│   ├── Dockerfile                       # OpenWA Docker image
│   ├── nest-cli.json                    # NestJS CLI config
│   ├── package.json                     # OpenWA dependencies (126 lines)
│   ├── tsconfig.json                    # TypeScript config
│   └── tsconfig.build.json              # TypeScript build config
├── scripts/                             # PowerShell operational scripts
│   ├── backup-mysql.ps1                 # MySQL database backup
│   ├── check-stack.ps1                  # Stack verification (containers + endpoints)
│   └── health-check.ps1                 # Health check with ntfy.sh alerts
├── docs/                                # Obsidian documentation vault (26 notes)
│   ├── README.md                        # Docs index/TOC
│   ├── Arquitectura.md                  # Architecture overview (Mermaid diagram)
│   ├── Baileys.md                       # Baileys service documentation
│   ├── EasyAppointments.md              # EA component documentation
│   ├── n8n.md                           # n8n component documentation
│   ├── LandingSalon.md                  # Landing page documentation
│   ├── OpenWA.md / OpenCodeBrief.md     # OpenWA docs
│   ├── DockerCompose.md                 # Docker stack documentation
│   ├── CloudflareTunnel.md              # Cloudflare tunnel docs
│   ├── EstadoProyecto.md                # Project status tracking
│   ├── Roadmap.md                       # Roadmap
│   ├── GuiaConfiguracion.md             # Configuration guide
│   ├── GuiaDuena.md                     # Owner's guide
│   ├── Monitoreo.md                     # Monitoring docs
│   ├── SecurityAudit-Plan.md            # Security audit plan
│   ├── SecurityAudit-Report.md          # Security audit report
│   ├── SecurityAuditor.md               # Security auditor skill docs
│   ├── Sesion-2026-06-12.md             # Session notes
│   └── WF1-Confirmacion.md through WF4-Reagendado.md  # Workflow docs
├── backups/                             # Backup storage directory
├── AGENTS.md                            # Project documentation workflow rules
├── OPENCODE-BRIEF.md                    # Project brief for AI context
├── README.md                            # Project README (brief)
├── opencode.json                        # OpenCode configuration
├── monitoreo.md                         # Root-level monitoring reference
├── guia-duena.md                        # Root-level owner's guide copy
├── GUIA-CONFIGURACION.md                # Root-level config guide copy
├── cloudflare-tunnel.md                 # Root-level Cloudflare tunnel
├── contexto.md                          # Project context
├── contrato-servicio.md                 # Service contract
├── checklist-configuracion.md           # Configuration checklist
├── estado-proyecto.md                   # Root-level project status
├── propuesta-comercial.md               # Commercial proposal
├── roadmap-etapas.md                    # Roadmap by stages
└── cookies.txt                          # Cookies file (presence noted)
```

## Directory Purposes

**`baileys-service/`:**
- Purpose: Standalone Node.js WhatsApp bot using the Baileys library
- Contains: Single Express.js app providing WhatsApp QR pairing, message sending, and incoming message webhook forwarding
- Key files: `index.js` (the entire service), `package.json` (dependencies: `@whiskeysockets/baileys`, `express`, `redis`, `qrcode`, `pino`)

**`easyappointments/`:**
- Purpose: Docker configuration for the core appointment engine
- Contains: Docker Compose file defining the entire service stack (MySQL, Easy!Appointments, Redis, n8n, Mailpit, Baileys, OpenWA), plus a custom Dockerfile
- Key files: `docker-compose.yml` (master orchestration file, 148 lines), `Dockerfile` (removes X-Frame-Options headers for iframe embedding)

**`landing-salon/`:**
- Purpose: Custom PHP frontend — landing page, admin panel, and API gateway layer
- Contains: Public-facing booking page, session-protected admin dashboard, and PHP API endpoints that proxy to Easy!Appointments and OpenWA
- Key files: `index.php` (public landing + booking), `admin/dashboard.php` (admin SPA), `api/crear-turno.php` (appointment creation workflow), `api/turnos-admin.php` (admin appointment management)

**`landing-salon/api/`:**
- Purpose: PHP API gateway — proxies authenticated requests to Easy!Appointments and relays WhatsApp messages to OpenWA
- Contains: 9 standalone PHP files, each handling a specific API endpoint
- Key files: `crear-turno.php` (public booking), `turnos-admin.php` (admin CRUD on appointments), `admin-servicios.php` (admin CRUD on services), `horarios-admin.php` (admin working plan management), `whatsapp-qr.php` (QR polling for admin panel)

**`landing-salon/admin/`:**
- Purpose: Session-protected admin dashboard for business management
- Contains: Login page, main SPA dashboard, and logout handler
- Key files: `dashboard.php` (802-line monolithic SPA with 6 tabs: Dashboard, Servicios, Horarios, Calendario, Turnos, WhatsApp)

**`n8n-workflows/`:**
- Purpose: n8n workflow definitions for automation
- Contains: 4 JSON workflow files for appointment notifications and WhatsApp chatbot
- Key files: `WF1-confirmacion.json` (polling-based confirmation), `WF3-cancelacion.json` (chatbot-driven cancellation)

**`openwa/`:**
- Purpose: Open Source WhatsApp API Gateway — full-featured NestJS application providing REST API for WhatsApp
- Contains: NestJS backend source, React dashboard, client SDKs, Docker configs, tests
- Key files: `src/main.ts` (bootstrap with security, CORS, Swagger), `src/app.module.ts` (root module wiring all features), `package.json` (NestJS 11 + TypeORM + BullMQ + helmet)

**`scripts/`:**
- Purpose: Operational scripts for monitoring, health checks, and backups
- Contains: 3 PowerShell scripts
- Key files: `health-check.ps1` (container + endpoint health with ntfy.sh alerts), `check-stack.ps1` (stack verification with summary), `backup-mysql.ps1` (database backup)

**`docs/`:**
- Purpose: Obsidian documentation vault — interconnected markdown notes
- Contains: 26 markdown files documenting all components, workflows, architecture, and project status
- Key files: `README.md` (index), `Arquitectura.md` (system diagram), `EstadoProyecto.md` (project status), `SecurityAudit-Report.md` (security findings)

**`.planning/codebase/`:**
- Purpose: Generated codebase analysis documents consumed by GSD workflow commands
- Contains: Architecture maps, stack analysis, conventions, concerns
- Key files: `ARCHITECTURE.md`, `STRUCTURE.md` (and other focus-area documents)

**`backups/`:**
- Purpose: Backup storage directory for MySQL dumps
- Contains: Database backup files
- Generated: Yes (by `scripts/backup-mysql.ps1`)
- Committed: Depends on `.gitignore`

## Key File Locations

**Entry Points:**
- `landing-salon/index.php`: Public landing page and booking form (the primary user-facing entry)
- `landing-salon/admin/index.php`: Admin login page
- `openwa/src/main.ts`: OpenWA NestJS application bootstrap (port 2785)
- `baileys-service/index.js`: Baileys bot service (port 3001)

**Configuration:**
- `easyappointments/docker-compose.yml`: Master Docker Compose file defining all services, networks, volumes, and environment variables
- `easyappointments/Dockerfile`: Custom Easy!Appointments image build
- `openwa/package.json`: OpenWA dependencies and scripts
- `openwa/tsconfig.json`: TypeScript compilation settings
- `openwa/nest-cli.json`: NestJS CLI project config
- `openwa/.prettierrc`: Code formatting rules
- `openwa/eslint.config.mjs`: Linting rules
- `opencode.json`: OpenCode AI agent configuration

**Core Logic:**
- `landing-salon/api/crear-turno.php`: Appointment creation workflow (service lookup → customer find/create → appointment create, 138 lines)
- `landing-salon/api/turnos-admin.php`: Admin appointment management (GET filtered list, PUT reschedule, DELETE cancel, 152 lines)
- `landing-salon/api/horarios.php`: Available time slot calculation (service duration × provider working plan − conflicts, 125 lines)
- `landing-salon/admin/dashboard.php`: Admin SPA dashboard (all business management UI, 802 lines)
- `baileys-service/index.js`: WhatsApp bot (connection, QR, send/receive, 209 lines)
- `openwa/src/app.module.ts`: NestJS root module importing all feature modules
- `n8n-workflows/WF1-confirmacion.json`: Immediate confirmation workflow

**Testing:**
- `openwa/test/`: OpenWA test files (Jest-based, NestJS testing utilities)
- No test files detected for `landing-salon/` or `baileys-service/`

**Documentation:**
- `docs/README.md`: Documentation index
- `docs/Arquitectura.md`: System architecture with Mermaid diagram
- `README.md`: Project root README

## Naming Conventions

**Files:**
- **PHP files:** `kebab-case.php` for API endpoints (`crear-turno.php`, `horarios-admin.php`), `lowercase.php` for entry pages (`index.php`, `logout.php`, `dashboard.php`)
- **Node.js/TS files:** `kebab-case.ts` for NestJS modules (`app.module.ts`, `shutdown.service.ts`), `camelCase.ts` for main entry (`main.ts`)
- **n8n workflows:** `WF{number}-{description}.json` (e.g., `WF1-confirmacion.json`)
- **PowerShell scripts:** `kebab-case.ps1` (`health-check.ps1`, `check-stack.ps1`)
- **Documentation:** `PascalCase.md` with Spanish names (`Arquitectura.md`, `EstadoProyecto.md`) in docs/
- **Root markdown:** Mix of `kebab-case.md` (English-named) and `kebab-case.md` (Spanish-named)

**Directories:**
- **Application code:** `kebab-case` (`baileys-service/`, `landing-salon/`, `n8n-workflows/`)
- **NestJS modules:** `lowercase` with subdirectories matching NestJS conventions (`src/modules/session/`, `src/common/services/`)
- **Documentation:** `lowercase` (`docs/`)

## Where to Add New Code

**New Feature (e.g., a new notification type):**
- Primary code: Add a new n8n workflow file `n8n-workflows/WF5-{description}.json`
- If it needs a new API endpoint: Add a new PHP file in `landing-salon/api/{feature}.php`
- Documentation: Add `docs/WF5-{Description}.md`

**New Admin Panel Tab:**
- Implementation: Add code in `landing-salon/admin/dashboard.php` (add a new `.tab-btn`, `.tab-content` div, and JS functions)
- API endpoint (if needed): Add new file in `landing-salon/api/{feature}-admin.php`

**New WhatsApp Engine (OpenWA):**
- Implementation: Add new adapter in `openwa/src/engine/adapters/{engine-name}/`
- Configuration: Update `openwa/src/engine/engine.module.ts`

**New NestJS Feature Module (OpenWA):**
- Implementation: Create new module in `openwa/src/modules/{feature}/`
- Pattern: Follow existing module structure — `{feature}.module.ts`, `{feature}.service.ts`, `{feature}.controller.ts`, `dto/`, `entities/`
- Register: Import in `openwa/src/app.module.ts`

**Utilities:**
- Shared PHP helpers: None currently exist; add `landing-salon/api/includes/` directory
- Shared JS helpers (OpenWA): `openwa/src/common/utils/`
- Shared PowerShell functions: Add to existing scripts or create new in `scripts/`

**New Docker Service:**
- Add service definition in `easyappointments/docker-compose.yml` under `services:`
- Add named volume if data persistence is needed
- Connect to the `stack` network

## Special Directories

**`.obsidian/`:**
- Purpose: Obsidian vault configuration (plugins, workspace settings)
- Generated: Yes (by Obsidian)
- Committed: Yes

**`.opencode/`:**
- Purpose: OpenCode AI agent configuration (skills, agents, permissions)
- Generated: Yes (by OpenCode)
- Committed: Yes

**`.planning/`:**
- Purpose: GSD workflow artifacts (phase plans, codebase analysis, intelligence)
- Generated: Yes (by `/gsd-*` commands)
- Committed: Yes

**`backups/`:**
- Purpose: MySQL database dumps from `scripts/backup-mysql.ps1`
- Generated: Yes
- Committed: Depends on `.gitignore`

**`openwa/dist/` and `openwa/node_modules/`:**
- Purpose: Build output (dist) and dependencies (node_modules)
- Generated: Yes
- Committed: No (`.gitignore`)

**`landing-salon/assets/`:**
- Purpose: Static assets for the landing page (images, fonts, etc.)
- Generated: No (user-managed)
- Committed: Yes (currently empty)

---

*Structure analysis: 2026-06-13*
