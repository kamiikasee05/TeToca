# Testing Patterns

**Analysis Date:** 2026-06-13

## Test Framework

**Runner:**
- Jest 30.4.2 with `ts-jest` 29.2.5
- Config (unit tests): Inline in `openwa/package.json` under `"jest"` key
- Config (e2e tests): `openwa/test/jest-e2e.json`
- Test environment: `"node"`

**Assertion Library:**
- Jest built-in (`expect`), no separate assertion library

**Mocking:**
- Jest manual mocks with `jest.Mocked<Partial<T>>` types
- No auto-mocking libraries (no `jest-mock-extended`, `ts-mockito`, etc.)

**Run Commands:**
```bash
npm test              # Run all unit tests
npm run test:watch    # Watch mode (jest --watch)
npm run test:cov      # Run with coverage (jest --coverage)
npm run test:debug    # Debug mode with --inspect-brk
npm run test:e2e      # Run e2e tests (jest --config ./test/jest-e2e.json)
```

## Test File Organization

**Location:**
- Co-located with source files — each `.spec.ts` sits next to its `.ts` counterpart
- E2E tests: `openwa/test/` directory

**Naming:**
- Unit tests: `<filename>.spec.ts` — e.g., `auth.service.spec.ts` lives next to `auth.service.ts`
- E2E tests: `<filename>.e2e-spec.ts` — e.g., `app.e2e-spec.ts`

**Structure:**
```
openwa/src/
├── common/services/
│   ├── logger.service.ts
│   └── logger.service.spec.ts          # Unit test
├── modules/
│   ├── auth/
│   │   ├── auth.service.ts
│   │   ├── auth.service.spec.ts        # Unit test
│   │   └── guards/
│   │       ├── api-key.guard.ts
│   │       └── api-key.guard.spec.ts   # Guard test
│   ├── health/
│   │   ├── health.controller.ts
│   │   └── health.controller.spec.ts   # Controller test
│   ├── message/
│   │   ├── message.service.ts
│   │   └── message.service.spec.ts     # Unit test
│   ├── session/
│   │   ├── session.service.ts
│   │   └── session.service.spec.ts     # Unit test
│   └── webhook/
│       ├── webhook.service.ts
│       └── webhook.service.spec.ts      # Unit test
openwa/test/
└── app.e2e-spec.ts                     # E2E test
```

**Existing test files (7 total):**
| File | Type | Lines |
|------|------|-------|
| `common/services/logger.service.spec.ts` | Service unit | 106 |
| `modules/auth/auth.service.spec.ts` | Service unit | 339 |
| `modules/auth/guards/api-key.guard.spec.ts` | Guard unit | 161 |
| `modules/health/health.controller.spec.ts` | Controller unit | 39 |
| `modules/message/message.service.spec.ts` | Service unit | 368 |
| `modules/session/session.service.spec.ts` | Service unit | 396 |
| `modules/webhook/webhook.service.spec.ts` | Service unit | 407 |
| `test/app.e2e-spec.ts` | E2E | 22 |

**No tests exist for:**
- The Dashboard React app (`openwa/dashboard/`) — no test config or test files
- The Baileys service (`baileys-service/`) — no test config or test files
- The n8n workflows — no test config or test files
- Controllers (except health): audit, catalog, channel, contact, group, infra, label, plugins, settings, stats, status, session, webhook, message, auth
- Core modules: hooks, plugins (core), engine, cache, storage, docker, events gateway, queue

## Test Structure

**Suite Organization (service tests):**
```typescript
import { Test, TestingModule } from '@nestjs/testing';
import { getRepositoryToken } from '@nestjs/typeorm';
import { Repository } from 'typeorm';
import { NotFoundException } from '@nestjs/common';
import { AuthService } from './auth.service';
import { ApiKey, ApiKeyRole } from './entities/api-key.entity';

describe('AuthService', () => {
  let service: AuthService;
  let repository: jest.Mocked<Partial<Repository<ApiKey>>>;

  beforeEach(async () => {
    // Setup mocks and create testing module
    repository = {
      find: jest.fn(),
      findOne: jest.fn(),
      create: jest.fn(),
      save: jest.fn(),
      remove: jest.fn(),
    };

    const module: TestingModule = await Test.createTestingModule({
      providers: [
        AuthService,
        { provide: getRepositoryToken(ApiKey, 'main'), useValue: repository },
      ],
    }).compile();

    service = module.get<AuthService>(AuthService);
  });

  describe('createApiKey', () => {
    it('should generate a key with owa_k1_ prefix and save to DB', async () => {
      // Arrange
      const mockSaved = createMockApiKey({ name: 'My Key' });
      (repository.create as jest.Mock).mockReturnValue(mockSaved);
      (repository.save as jest.Mock).mockResolvedValue(mockSaved);

      // Act
      const result = await service.createApiKey({ name: 'My Key' });

      // Assert
      expect(result.rawKey).toMatch(/^owa_k1_[a-f0-9]{64}$/);
      expect(result.apiKey).toBe(mockSaved);
    });
  });
});
```

**Patterns observed:**
- `describe()` wraps the class under test
- Nested `describe()` for each public method
- `beforeEach()` creates a fresh `TestingModule` for isolation
- Arrange-Act-Assert structure in each `it()` block
- `jest.Mocked<Partial<T>>` type for all mocked dependencies
- Mock factories defined as plain functions: `createMockApiKey()`, `createMockEngine()`, `createMockSession()`, `createMockContext()`
- Manual mock objects using object literals with `jest.fn()`
- Repository token injection uses `getRepositoryToken(Entity, connectionName)`

## Mocking

**Framework:** Jest manual mocks (`jest.fn()`, `jest.spyOn()`)

**Standard mock setup pattern:**
```typescript
// Mock a repository
repository = {
  count: jest.fn(),
  find: jest.fn(),
  findOne: jest.fn(),
  create: jest.fn().mockImplementation((data) => ({ id: 'uuid', ...data })),
  save: jest.fn().mockImplementation(msg => Promise.resolve(msg)),
  remove: jest.fn(),
};

// Mock a service dependency
sessionService = {
  getEngine: jest.fn().mockReturnValue(mockEngine),
  findOne: jest.fn().mockResolvedValue({ id: 'sess-1', phone: '628123456789' }),
};

// Mock hook manager (returns { continue, data } shape)
hookManager = {
  execute: jest.fn().mockResolvedValue({ continue: true, data: { ... } }),
};
```

**Factory function pattern (for test data):**
```typescript
function createMockApiKey(overrides: Partial<ApiKey> = {}): ApiKey {
  return {
    id: 'uuid-1',
    name: 'Test Key',
    keyHash: hashKey('test-key'),
    keyPrefix: 'test-key-pre',
    role: ApiKeyRole.OPERATOR,
    allowedIps: null,
    allowedSessions: null,
    isActive: true,
    expiresAt: null,
    lastUsedAt: null,
    usageCount: 0,
    createdAt: new Date(),
    updatedAt: new Date(),
    ...overrides,  // Allow customization per test
  };
}
```

**What to Mock:**
- All external dependencies (repositories, services, gateways)
- TypeORM `Repository<T>` via `getRepositoryToken()`
- Engine adapters (`EngineFactory`, `IWhatsAppEngine`)
- Hook system (`HookManager`)
- WebSocket gateway (`EventsGateway`)
- `DataSource.transaction()` via `getDataSourceToken()`
- `fetch()` via `global.fetch = jest.fn()` (for webhook dispatch tests)
- Console methods via `jest.spyOn(console, 'log').mockImplementation()` (for logger tests)

**What NOT to Mock:**
- The class under test (always instantiated via DI)
- Plain data structures (use real objects)
- Utility/helper functions internal to the module
- Crypto functions (real `createHash` used in auth spec)

## Fixtures and Factories

**Test Data:**
- Factory functions are the primary pattern: `createMockApiKey()`, `createMockEngine()`, `createMockSession()`, `createMockWebhook()`
- Each factory accepts `Partial<T> overrides` for test-specific customizations
- Constants for repeated values: `mockEngineResult = { id: 'wa-msg-1', timestamp: 1706868000 }`
- Helpers extracted at top of file: `const hashKey = (key) => createHash('sha256').update(key).digest('hex')`

**Location:**
- Factory functions defined at the top of each spec file (before `describe()`)
- No shared fixture directory or test utilities module

## Coverage

**Requirements:**
- Config in `openwa/package.json` under `jest.coverageThreshold`:
  - `branches: 10` (minimum 10%)
  - `functions: 12` (minimum 12%)
  - `lines: 15` (minimum 15%)
  - `statements: 15` (minimum 15%)
- Documentation target (`docs/09-testing-strategy.md`): >80% code coverage (aspirational)

**Current state:**
- ~5-8% coverage per the testing strategy document
- Only 7 spec files covering 6 modules out of ~20+

**View Coverage:**
```bash
npm run test:cov           # Generates coverage report in ../coverage/
```

**Coverage collection config:**
```json
"collectCoverageFrom": ["**/*.(t|j)s"]
```

## Test Types

**Unit Tests:**
- Scope: Individual services, controllers, guards
- Pattern: Mock all dependencies, test one method at a time
- Each `it()` tests a single behavior or edge case
- Located: `openwa/src/**/*.spec.ts`

**Integration Tests:**
- Not yet implemented. The testing strategy document specifies integration tests should cover 30% of the codebase.
- Would test: database operations with real TypeORM, full request lifecycle, queue processing

**E2E Tests:**
- Framework: `supertest` 7.0.0
- Config: `openwa/test/jest-e2e.json`
- Existing test: `openwa/test/app.e2e-spec.ts` — minimal smoke test (`GET /` → 200)
- Pattern: Creates full `NestApplication` from `AppModule`, uses `request(app.getHttpServer()).get('/')`
- Not covering: authentication, session management, message sending, webhook dispatch, etc.

**Dashboard Tests:**
- No tests exist for the React dashboard
- No test runner configured (`package.json` lacks `vitest` or `jest` for the dashboard)
- React Testing Library not in dependencies

## Common Patterns

**Async Testing:**
```typescript
it('should throw NotFoundException if key not found', async () => {
  (repository.findOne as jest.Mock).mockResolvedValue(null);
  
  await expect(service.findOne('nonexistent')).rejects.toThrow(NotFoundException);
});
```

**Error Testing (service throws):**
```typescript
it('should throw BadRequestException when plugin blocks sending', async () => {
  (hookManager.execute as jest.Mock).mockResolvedValueOnce({ continue: false, data: {} });

  await expect(
    service.sendText('sess-1', { chatId: 'test@c.us', text: 'blocked' })
  ).rejects.toThrow('Message sending blocked by plugin');
});
```

**Mock call verification:**
```typescript
expect(repository.create).toHaveBeenCalledWith(
  expect.objectContaining({
    name: 'My Key',
    role: ApiKeyRole.OPERATOR,
  }),
);
expect(mockEngine.sendTextMessage).toHaveBeenCalledWith('628123456789@c.us', 'Hello');
expect(repository.save).toHaveBeenCalledTimes(2);
```

**Guard testing (mocking ExecutionContext):**
```typescript
function createMockContext(headers = {}, params = {}): ExecutionContext {
  const request = { headers, params, ip: '127.0.0.1', socket: { remoteAddress: '127.0.0.1' } };
  return {
    switchToHttp: () => ({ getRequest: () => request }),
    getHandler: () => ({}),
    getClass: () => ({}),
  } as unknown as ExecutionContext;
}

it('should allow access to @Public() routes without API key', async () => {
  reflector.getAllAndOverride.mockReturnValueOnce(true);
  const context = createMockContext();
  const result = await guard.canActivate(context);
  expect(result).toBe(true);
});
```

**Console mocking (logger tests):**
```typescript
let consoleSpy: jest.SpyInstance;
beforeEach(() => {
  consoleSpy = jest.spyOn(console, 'log').mockImplementation();
  jest.spyOn(console, 'warn').mockImplementation();
  jest.spyOn(console, 'error').mockImplementation();
});
afterEach(() => {
  jest.restoreAllMocks();
});
```

**Testing multiple conditions via factory overrides:**
```typescript
it('should throw UnauthorizedException for revoked key', async () => {
  const key = createMockApiKey({ isActive: false, keyHash: hashKey('revoked') });
  (repository.findOne as jest.Mock).mockResolvedValue(key);
  await expect(service.validateApiKey('revoked')).rejects.toThrow('API key is revoked');
});
```

## Running Tests

**All unit tests:**
```bash
cd openwa && npm test
```

**Single file:**
```bash
cd openwa && npx jest -- src/modules/auth/auth.service.spec.ts
```

**Watch mode:**
```bash
cd openwa && npm run test:watch
```

**Coverage:**
```bash
cd openwa && npm run test:cov
```

**E2E:**
```bash
cd openwa && npm run test:e2e
```

---

*Testing analysis: 2026-06-13*
