# tuahora-scheduler

**✅ IMPLEMENTADO (15 Jun 2026).** Microservicio API-first que reemplazó EasyAppointments + MySQL. Ver [[Sesion-2026-06-15]] para el detalle de la migración.

## Motivación

EasyAppointments se usa al ~5% de su capacidad. De toda su funcionalidad (frontend, multi-provider, Google Calendar, email, auth, categorías), **solo se necesita**:

| Necesidad | Cómo se cubre hoy |
|---|---|
| Persistencia (turnos, clientes, servicios, agenda) | MySQL vía API REST de EA |
| Cálculo de disponibilidad | `horarios.php` (algoritmo propio, no usa EA) |
| CRUD + agendación | PHP relay → EA API |
| Webhooks para n8n | **No existe** → polling cada 2 min |

El resto del stack (PHP 7.4 + Apache + MySQL 8.0) pesa ~500MB de imagen y ~200MB+ de RAM para servir un JSON.

## Arquitectura Propuesta

```
┌──────────────────────────────────────────┐
│  tuahora-scheduler                        │
│  Node.js 22 + node:sqlite (built-in)      │
│  ┌──────────────────────────────────────┐ │
│  │  REST API (v1, compatible EA)        │ │
│  │  GET/POST/PUT/DELETE /appointments   │ │
│  │  GET/POST/PUT/DELETE /customers      │ │
│  │  GET/POST/PUT/DELETE /services       │ │
│  │  GET/PUT /providers/5               │ │
│  │  GET /slots?serviceId&date           │ │  ← algoritmo de horarios.php nativo
│  │  GET /availabilities?                │ │
│  │  POST /webhooks                      │ │  ← nuevo! notifica a n8n en tiempo real
│  └──────────────────────────────────────┘ │
│  ┌──────────────────────────────────────┐ │
│  │  SQLite (scheduler.db)               │ │
│  │  - appointments                      │ │
│  │  - customers                         │ │
│  │  - services                          │ │
│  │  - provider_schedule                 │ │  ← JSON con working plan
│  └──────────────────────────────────────┘ │
│  ┌──────────────────────────────────────┐ │
│  │  Webhook engine                      │ │
│  │  POST /webhooks/new-appointment  ────►│ n8n
│  │  POST /webhooks/cancellation    ────►│ n8n
│  └──────────────────────────────────────┘ │
└──────────────────────────────────────────┘
```

### Stack

- **Runtime:** Node.js 22+ (LTS, `node:22-alpine` Docker image)
- **DB:** SQLite via `node:sqlite` (built-in, **0 dependencias externas**)
- **Framework:** Expres.js (mínimo, sin ORM pesado)
- **Auth:** API Key via header `X-API-Key` (reemplaza HTTP Basic Auth)
- **Docker image:** ~236MB (vs ~500MB de EA + MySQL)

### Base de datos (SQLite, 4 tablas)

```sql
CREATE TABLE customers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  first_name TEXT NOT NULL,
  last_name TEXT DEFAULT '',
  email TEXT DEFAULT '',
  phone TEXT NOT NULL,
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE services (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  duration INTEGER NOT NULL,  -- minutos
  price REAL DEFAULT 0,
  description TEXT DEFAULT '',
  currency TEXT DEFAULT 'ARS'
);

CREATE TABLE appointments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  start TEXT NOT NULL,         -- '2026-06-15 10:00:00'
  end TEXT NOT NULL,
  service_id INTEGER NOT NULL REFERENCES services(id),
  customer_id INTEGER NOT NULL REFERENCES customers(id),
  provider_id INTEGER DEFAULT 5,
  status TEXT DEFAULT 'confirmed',  -- confirmed | cancelled
  notes TEXT DEFAULT '',
  hash TEXT DEFAULT '',
  created_at TEXT DEFAULT (datetime('now'))
);

CREATE TABLE provider_schedule (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider_id INTEGER DEFAULT 5,
  working_plan TEXT NOT NULL,  -- JSON, mismo formato que EA
  timezone TEXT DEFAULT 'America/Argentina/Cordoba'
);
```

### API Endpoints

| Endpoint | Método | Reemplaza a EA |
|---|---|---|
| `GET /api/v1/appointments` | GET | EA `/appointments` |
| `GET /api/v1/appointments/:id` | GET | EA `/appointments/:id` |
| `POST /api/v1/appointments` | POST | EA `/appointments` |
| `PUT /api/v1/appointments/:id` | PUT | EA `/appointments/:id` |
| `DELETE /api/v1/appointments/:id` | DELETE | EA `/appointments/:id` |
| `GET /api/v1/customers` | GET | EA `/customers` |
| `POST /api/v1/customers` | POST | EA `/customers` |
| `GET /api/v1/customers?q={phone}` | GET | EA `/customers?q=` |
| `GET /api/v1/services` | GET | EA `/services` |
| `GET /api/v1/services/:id` | GET | EA `/services/:id` |
| `POST /api/v1/services` | POST | EA `/services` |
| `PUT /api/v1/services/:id` | PUT | EA `/services/:id` |
| `DELETE /api/v1/services/:id` | DELETE | EA `/services/:id` |
| `GET /api/v1/providers/5` | GET | EA `/providers/5` |
| `PUT /api/v1/providers/5` | PUT | EA `/providers/5` |
| `GET /api/v1/availabilities?providerId=5&serviceId=X&date=Y` | GET | EA `/availabilities` |
| `GET /api/v1/slots?serviceId=X&date=Y` | GET | **NUEVO** (algoritmo `horarios.php`) |

### Webhooks (nuevo)

| Evento | Payload | Disparo |
|---|---|---|
| `appointment.created` | `{ id, start, end, serviceId, customerId, customer: { name, phone } }` | POST a URL configurada |
| `appointment.cancelled` | `{ id, customerPhone }` | POST a URL configurada |
| `appointment.rescheduled` | `{ id, oldStart, newStart }` | POST a URL configurada |

Esto elimina la necesidad de polling en n8n WF1 (confirmación inmediata).

### Autenticación

- **API:** Header `X-API-Key: <key>` en cada request
- **Pública (landing page):** Solo `GET /services`, `GET /slots`, `GET /availabilities` sin auth
- **Admin:** API Key para el relay PHP (desde env var)

## Migración: Fase 0 → Fase 3

### Fase 0: Coexistencia (día 1-2)

```
Estado actual:
  easyappointments:8080  ← n8n + PHP apuntan acá
  mysql:3306

Cambios:
  1. Deploy tuahora-scheduler en el Docker stack como `scheduler:3000`
  2. Apunta el PHP relay a `http://scheduler:3000/api/v1/` en vez de `http://localhost/index.php/api/v1/`
  3. El script de migración exporta MySQL → SQLite
  4. Ambos sistemas corren en paralelo
```

**Script de migración** (una vez):

```bash
node migrate-ea-to-scheduler.js \
  --mysql-host mysql \
  --mysql-db easyappointments \
  --mysql-user ea_user \
  --mysql-pass "$MYSQL_PASSWORD" \
  --sqlite ./data/scheduler.db
```

Exporta: `ea_appointments`, `ea_users` (customers), `ea_services`, `ea_providers` (solo ID 5) → SQLite.

### Fase 1: n8n apunta al scheduler (día 2-3)

```
  1. Cambiar credenciales HTTP de n8n de "EA Cred" (HTTP Basic Auth) a API Key
  2. Actualizar URLs en WF1-WF4:
     http://easyappointments:80/index.php/api/v1/appointments
       → http://scheduler:3000/api/v1/appointments
  3. WF1 cambia de polling a webhook (appointment.created)
  4. Verificar que WF3 y WF4 funcionan con DELETE/GET customers
```

### Fase 2: Landing page apunta al scheduler (día 3-4)

```
  1. Cambiar env var EA_BASE_URL a http://scheduler:3000/api/v1
     (o refactorizar PHP para que no use más localhost/index.php/api/v1)
  2. Verificar crear-turno.php, horarios.php, servicios.php, cancel-appointment.php
  3. Verificar admin (turnos-admin.php, horarios-admin.php, admin-servicios.php)
```

### Fase 3: Retirar EA + MySQL (día 4-5)

```
  1. Detener servicios easyappointments y mysql del compose
  2. Opcional: dejar el container de landing-page? 
     - El landing page PHP corre dentro del EA container
     - Opción A: Migrar landing page a un container PHP independiente (sencillo, mismo código)
     - Opción B: Dejar el landing page dentro del EA container pero apuntando al scheduler
     - Opción C: Futuro: reemplazar landing page con frontend estático (HTML/JS) que hable directo al scheduler
  3. Clean up: eliminar ea_mysql_data volume, easyappointments Dockerfile
```

## docker-compose final (target)

```yaml
services:
  scheduler:
    build: ./scheduler
    container_name: scheduler
    restart: unless-stopped
    volumes:
      - scheduler_data:/app/data
    environment:
      API_KEY: ${SCHEDULER_API_KEY}
      N8N_WEBHOOK_URL: http://n8n:5678/webhook/
    # no ports exposed to host (internal only)
    networks:
      - stack

  n8n:
    # sin cambios, solo URLs apuntan a scheduler

  openwa:
    # sin cambios

  landing:
    build: ./landing
    container_name: landing
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      SCHEDULER_API_KEY: ${SCHEDULER_API_KEY}
    # Sin EA, sin MySQL
    networks:
      - stack

volumes:
  scheduler_data:  # ~10MB vs 1GB+ de ea_mysql_data
```

## Beneficios

| Aspecto | Hoy (EA+MySQL) | Con tuahora-scheduler |
|---|---|---|
| Imagen Docker | ~500MB (PHP+Apache+MySQL) | ~30MB (Node 22 Alpine) |
| RAM | ~200MB+ | ~30-50MB |
| Dependencias externas | MySQL 8.0 | SQLite (built-in en Node) |
| Backup | `mysqldump` | `cp scheduler.db` + gzip |
| Webhooks | ❌ Polling cada 2 min | ✅ Push en tiempo real |
| API | EA v1 (endpoints limitados) | API compatible + mejoras |
| Código legacy | PHP 7.4 | TypeScript (mismo stack que OpenWA) |
| Startup time | ~30s (MySQL healthcheck) | <2s |

## Riesgos y mitigaciones

1. **SQLite en escritura concurrente:** Cuando n8n + PHP relay + admin escriben simultáneamente. SQLite usa WAL (Write-Ahead Logging) que maneja concurrencia moderada sin problemas. Para un solo profesional con ~20-30 turnos/día, no hay riesgo de contención.

2. **API Key en lugar de Basic Auth:** Los workflows de n8n usan "genericCredentialType/httpBasicAuth". Habría que actualizar las credenciales de n8n para usar Header Auth. Es un cambio único.

3. **Landing page dentro del EA container:** Hoy `landing-salon/` está copiado dentro del `easyappointments` image. Habría que independizarlo. Se puede hacer un container PHP con Apache mínimo o, mejor, un Nginx con PHP-FPM de ~50MB.

4. **Migración de datos:** Los IDs de EA se preservan. La migración es un script que lee MySQL y escribe SQLite. Se puede validar comparando counts.

## Estado Actual — ✅ MIGRACIÓN COMPLETADA (15 Jun 2026)

✅ **Código del scheduler** — `scheduler/` completo con:
- Express + `node:sqlite` (0 dependencias nativas)
- API compatible con EA v1 (appointments, customers, services, providers, slots, availabilities)
- Algoritmo de slots portado de `horarios.php`
- Webhooks para n8n (appointment-created, cancelled, rescheduled)
- Auth dual: API Key + HTTP Basic Auth (compatible n8n)
- Docker image: ~236MB (`node:24-alpine`)

✅ **docker-compose** — Servicio `scheduler` agregado con build automático.

✅ **PHP relay** — Todos los endpoints PHP migrados al scheduler.

✅ **n8n workflows** — URLs y credenciales actualizadas (API Key).

✅ **Migración de datos** — Datos migrados: 7 servicios, 17 clientes, turnos activos, config de providers.

✅ **EA + MySQL retirados** — Contenedores eliminados, volumen `ea_mysql_data` borrado (~1.1GB liberados), servicios removidos de docker-compose.yml.

✅ **.env limpiado** — Variables viejas de EA/MySQL eliminadas.

### Próximos pasos (completados)

1. ✅ Deploy scheduler en el stack (Fase 0)
2. ✅ Migrar datos desde EA (ejecutar script de migración)
3. ✅ Actualizar n8n workflows (URLs + auth)
4. ✅ Actualizar PHP relay
5. ✅ Retirar EA + MySQL

> Ver [[Sesion-2026-06-15]] para el detalle completo de la migración.

## Conclusión

**Viable y recomendado.** El esfuerzo es ~3-5 días hábiles para un MVP completo. El proyecto ya tiene el 80% de la lógica de negocio en los PHP relays (`horarios.php`, `crear-turno.php`, etc.), solo falta reemplazar la capa de persistencia + API.

El mayor impacto inmediato es eliminar el polling de WF-1 (confirmación en tiempo real) y reducir la superficie de infraestructura a la mitad.
