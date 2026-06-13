# Coding Conventions

**Analysis Date:** 2026-06-13

## Naming Patterns

**Files:**
- Service files: `kebab-case.service.ts` — e.g., `auth.service.ts`, `logger.service.ts`, `message.service.ts`
- Controller files: `kebab-case.controller.ts` — e.g., `health.controller.ts`, `session.controller.ts`
- Module files: `kebab-case.module.ts` — e.g., `auth.module.ts`, `session.module.ts`
- Entity files: `kebab-case.entity.ts` — e.g., `api-key.entity.ts`, `message.entity.ts`
- DTO files: `kebab-case.dto.ts` — e.g., `api-key.dto.ts`, `send-message.dto.ts`
- Interface files: `kebab-case.interface.ts` — e.g., `response.interface.ts`, `whatsapp-engine.interface.ts`
- Guard files: `kebab-case.guard.ts` — e.g., `api-key.guard.ts`
- Spec files: co-located as `<original>.spec.ts` — e.g., `auth.service.spec.ts` next to `auth.service.ts`
- Barrel files: `index.ts` used for re-exporting directories — e.g., `common/cache/index.ts`, `core/hooks/index.ts`
- Dashboard (React): PascalCase component files — e.g., `App.tsx`, `Layout.tsx`, `ErrorBoundary.tsx`

**Functions:**
- camelCase — e.g., `createApiKey()`, `sendText()`, `validateApiKey()`, `buildMediaInput()`
- Private methods prefixed with `private` keyword, same camelCase — e.g., `private hashKey()`, `private isIpAllowed()`
- Factory/helper functions: camelCase — e.g., `createLogger()`, `createMockApiKey()`

**Variables:**
- camelCase — e.g., `rawKey`, `apiKeyRepository`, `sessionService`, `mockEngine`
- Constants at module level: UPPER_SNAKE_CASE — e.g., `API_KEY_FILE`, `REQUIRED_ROLE_KEY`, `PUBLIC_KEY`

**Types/Interfaces:**
- PascalCase — e.g., `ApiResponse<T>`, `ApiError`, `ApiMeta`, `LogContext`, `MessageResponseDto`
- Enums: PascalCase names with string values — e.g., `enum ApiKeyRole { VIEWER = 'viewer', OPERATOR = 'operator', ADMIN = 'admin' }`
- DTO classes: PascalCase with `Dto` suffix — e.g., `CreateApiKeyDto`, `UpdateApiKeyDto`, `SendTextMessageDto`

**Classes:**
- PascalCase — e.g., `AuthService`, `LoggerService`, `MessageService`, `HealthController`
- NestJS decorator classes use `@Injectable()`, `@Controller()`, `@Module()`

## Code Style

**Formatting:**
- Tool: Prettier 3.4.2
- Config file: `openwa/.prettierrc`
- Key settings:
  - `singleQuote: true` — Use single quotes
  - `trailingComma: "all"` — Always trailing commas
  - `printWidth: 120` — Wider than default (120 vs 80)
  - `tabWidth: 2` — 2-space indentation
  - `useTabs: false` — Spaces, not tabs
  - `semi: true` — Always semicolons
  - `bracketSpacing: true` — Spaces inside `{ }`
  - `arrowParens: "avoid"` — Omit parens when single arg: `x => x`
  - `endOfLine: "auto"` — Auto-detect line endings

**Linting:**
- Tool: ESLint 10.4.0 with `typescript-eslint` 8.x
- Config files:
  - Backend: `openwa/eslint.config.mjs` — flat config, `recommendedTypeChecked`, Prettier integration
  - Dashboard: `openwa/dashboard/eslint.config.js` — flat config, React hooks plugin, React refresh plugin
- Key custom rules:
  - `@typescript-eslint/no-explicit-any: 'off'` — `any` is allowed
  - `@typescript-eslint/no-floating-promises: 'warn'` — Warns on unhandled promises
  - `@typescript-eslint/no-unsafe-argument: 'warn'` — Warns on unsafe arguments
  - Inline `eslint-disable-next-line` comments used sparingly for valid exceptions (e.g., `@typescript-eslint/no-unsafe-assignment` in `response.interceptor.ts`)

**TypeScript:**
- Config: `openwa/tsconfig.json`, target ES2023
- Module: `NodeNext` with `NodeNext` module resolution
- Strict settings: `strictNullChecks: true`, `noImplicitAny: true`, `strictBindCallApply: true`, `noFallthroughCasesInSwitch: true`
- Decorators: `emitDecoratorMetadata: true`, `experimentalDecorators: true` (required by NestJS)
- `isolatedModules: true`, `esModuleInterop: true`, `forceConsistentCasingInFileNames: true`

## Import Organization

**Order (observed in service files):**
1. NestJS core imports (`@nestjs/common`, `@nestjs/typeorm`, etc.)
2. Third-party dependencies (`typeorm`, `crypto`, `helmet`, etc.)
3. Local relative imports (`../../engine/`, `../dto`, etc.)

**Example from `auth.service.ts`:**
```typescript
import { Injectable, NotFoundException, UnauthorizedException, OnModuleInit } from '@nestjs/common';
import { InjectRepository } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { createHash, randomBytes } from 'crypto';
import { existsSync, writeFileSync, readFileSync } from 'fs';
import { join } from 'path';
import { ApiKey, ApiKeyRole } from './entities/api-key.entity';
import { CreateApiKeyDto, UpdateApiKeyDto } from './dto';
import { createLogger } from '../../common/services/logger.service';
```

**Path Aliases:**
- Not used. All imports are relative paths (e.g., `../../common/services/logger.service`).
- Barrel files (`index.ts`) in dto/, hooks/, plugins/, cache/ directories allow importing without specifying individual files: `'./dto'` instead of `'./dto/api-key.dto'`.

**Dashboard import pattern:**
```typescript
import { useState, useEffect, lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Layout } from './components/Layout';
import { RoleProvider, useRole, type UserRole } from './hooks/useRole';
```

## Error Handling

**Patterns:**
- NestJS built-in exceptions used consistently:
  - `NotFoundException` — entity not found (e.g., `findOne()` methods)
  - `UnauthorizedException` — auth failures (e.g., `validateApiKey()`)
  - `BadRequestException` — invalid input or precondition (e.g., `sendText()` when engine not ready)
  - `ConflictException` — duplicate resource (e.g., duplicate session name)
- Exception messages are descriptive string literals with interpolated values: `` `Session with id '${id}' not found` ``
- Service methods throw exceptions rather than returning null/error objects
- `try/catch` blocks used at the method body level for operations that can fail (e.g., engine calls in `message.service.ts`)
- On catch, status is updated (e.g., `message.status = MessageStatus.FAILED`) and the error is re-thrown
- Error details captured in logger: `this.logger.warn('IP not allowed', { keyId, action: 'ip_rejected' })`
- The global `ValidationPipe` strips unknown properties (`whitelist: true`, `forbidNonWhitelisted: true`)
- HTTP exception filter configured in `openwa/src/common/filters/http-exception.filter.ts`
- Response interceptor (`response.interceptor.ts`) wraps all successful responses in `{ success: true, data, meta: { timestamp, requestId } }`

**Retry/recovery pattern (from `session.service.ts`):**
- Exponential backoff with jitter for reconnect attempts
- `catch` block captures error, logs, then schedules retry via `scheduleReconnect()`

## Logging

**Framework:** Custom `LoggerService` in `openwa/src/common/services/logger.service.ts`

**Patterns:**
- Each service gets a logger via `createLogger('ServiceName')` — creates a transient-scoped logger with context
- `this.logger.log(message, context)` — for info
- `this.logger.warn(message, { ...metadata })` — for warnings
- `this.logger.error(message, trace?, { ...metadata })` — for errors
- `this.logger.debug(message, { ...metadata })` — for debug
- Log output is structured JSON: `{ timestamp, level, context, message, ...metadata }`
- Metadata includes `sessionId`, `action`, `error`, `attempt`, `delayMs` etc.
- Log levels: `ERROR > WARN > INFO > DEBUG > VERBOSE`
- Default level: INFO (set by `LoggerService.setLogLevel()`)

**Bootstrap logging in `main.ts`:**
- `console.log('[Bootstrap] Loading .env from:', userEnvPath)` — bracket-prefixed for startup messages
- `console.log('🚀 OpenWA is running on:', url)` — emoji-prefixed runtime banners

## Comments

**When to Comment:**
- JSDoc on public methods with clear purpose — e.g., `/** Save incoming message (called from session webhook dispatch) */`
- Phase markers for feature grouping — e.g., `// ========== Phase 3: Extended Messaging ==========`
- Section dividers in larger files — e.g., `// ── createApiKey ──────────────────────────────────────────────────`
- Security rationale comments — e.g., `// Phase 3 Security Audit: Support both exact match and CIDR notation`
- Bootstrap configuration comments — extensive inline comments in `main.ts` explaining config loading order
- TODO comments: Not detected in scanned files

**JSDoc Patterns:**
```typescript
/**
 * Check if an IPv4 address is within a CIDR range
 * @param ip - Client IP address (e.g., "192.168.1.100")
 * @param cidr - CIDR notation (e.g., "192.168.1.0/24")
 */
```

**Dashboard comments:**
- Section banners: `// =============================================================================` ... `// Types`
- Inline type comments: `// Only returned on creation`

## Function Design

**Size:**
- Most methods are 5–30 lines
- Largest methods are in `whatsapp-web-js.adapter.ts` (31KB) and `infra.controller.ts` (28KB) — these are exception areas
- Generally well-decomposed into small private helpers

**Parameters:**
- DTO objects preferred over multiple positional args — e.g., `createApiKey(dto: CreateApiKeyDto)` rather than `createApiKey(name, role, ips, sessions, expires)`
- Destructured options: `async getMessages(sessionId: string, options: GetMessagesOptions = {})` with `const { chatId, limit = 50, offset = 0 } = options;`
- Optional parameters use `?` notation and default values

**Return Values:**
- Service methods return typed Promises — e.g., `Promise<ApiKey>`, `Promise<MessageResponseDto>`
- CRUD methods return the entity directly (not wrapped in ApiResponse — the interceptor handles wrapping)
- `void` for fire-and-forget operations — e.g., `async delete(id: string): Promise<void>`
- `undefined` returned only for nullable lookups: `getEngine(id): IWhatsAppEngine | undefined`

## Module Design

**Exports:**
- NestJS modules export their service and any shared providers
- Barrel files (`index.ts`) re-export public API — e.g., `common/cache/index.ts` exports `CacheModule`
- DTOs have `index.ts` barrel files — e.g., `modules/auth/dto/index.ts`

**NestJS Module Structure (standard pattern):**
```
modules/<name>/
├── <name>.module.ts          # NestJS module definition
├── <name>.controller.ts      # HTTP endpoints
├── <name>.service.ts         # Business logic
├── <name>.service.spec.ts    # Unit tests
├── dto/                      # Data Transfer Objects
│   ├── index.ts              # Barrel export
│   └── <dto-name>.dto.ts
├── entities/                 # TypeORM entities (if needed)
│   └── <entity>.entity.ts
├── guards/                   # Route guards (auth module)
├── decorators/               # Custom decorators (auth module)
└── utils/                    # Utilities (webhook module)
```

**Dashboard structure:**
```
dashboard/src/
├── App.tsx                   # Root component with routing
├── main.tsx                  # Vite entry point
├── components/               # Shared UI components
├── hooks/                    # Custom React hooks
├── pages/                    # Route-level pages
├── services/                 # API client layer
├── i18n/                     # Internationalization
└── types/                    # Shared TypeScript types
```

**DTO Validation (class-validator):**
- DTOs use `class-validator` decorators: `@IsString()`, `@IsOptional()`, `@IsEnum()`, `@IsArray()`, `@MinLength(3)`, `@MaxLength(100)`
- Swagger decorators on all DTO properties: `@ApiProperty()`, `@ApiPropertyOptional()`

## React Dashboard Conventions

**State Management:**
- React Query (`@tanstack/react-query`) for server state — queries defined in `hooks/queries.ts`
- `useState` for local UI state
- `sessionStorage` for API key persistence (`openwa_api_key`)

**Component Patterns:**
- Lazy loading with `lazy()` + `Suspense` for code splitting — e.g., `const Login = lazy(() => import('./pages/Login'))`
- CSS modules or co-located `.css` files — e.g., `App.css`, `Layout.css`
- Function components only, no class components
- Named exports for pages, default export for `App`

**API Layer:**
- `openwa/dashboard/src/services/api.ts` — centralized API client
- Resource-based API objects: `sessionApi.list()`, `webhookApi.create()`, etc.
- Generic `request<T>()` function handles fetch, headers, auth, error handling

**TypeScript:**
- Interface definitions in services/api.ts and types/role.ts
- No `any` usage observed; strict typing throughout

## Baileys Service Conventions (JavaScript)

- File: `baileys-service/index.js` — single Express app file
- CommonJS `require()` syntax, not ES modules
- `const` for all variables, no `let`/`var` except for mutable state
- Express-style request handlers with `(req, res)` callbacks
- Error-first patterns with try/catch
- Inline HTML template strings for the QR page
- Pino logger for structured logging

---

*Convention analysis: 2026-06-13*
