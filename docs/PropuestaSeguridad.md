# Propuesta de Mejoras de Seguridad — TuAhora

**Fecha**: 2026-06-14
**Autor**: Especialista en Ciberseguridad TuAhora
**Basado en**: [[SecurityAudit-Report]] · [[Arquitectura]] · [[SecurityAudit-Plan]]
**Estado**: ✅ Fase 1 completada · ✅ Fase 2 completada · ⬜ Fase 3 pendiente

---

## Estado de implementación

| Fase | Estado | Fecha | Hallazgos resueltos |
|------|--------|-------|---------------------|
| **Fase 1 (P0)** | ✅ Completada | 2026-06-14 | CR-1, CR-2, CR-3, CR-4, CR-5, CR-9 |
| **Fase 2 (P1)** | ✅ Completada | 2026-06-14 | CR-6, CR-7, CR-8, SF-1, SF-2, SF-3, SF-4, SF-8, SF-10, SF-11, O-7, O-9, O-11, O-12 |
| **Fase 3 (P2-P3)** | ⬜ Pendiente | — | HTTPS, rate limiting, CSP, backups, portabilidad (15 ítems) |

### Cambios aplicados (resumen)

- Creado `.env.example` con todas las variables de entorno necesarias
- Creado `.gitignore` con reglas de seguridad
- Creado `landing-salon/env-loader.php` para cargar vars en PHP
- Creado `landing-salon/api/cors.php` con CORS por origen
- 11 archivos PHP actualizados: credenciales → `$_ENV`, sesión admin en relays
- `docker-compose.yml`: variables `${VAR}`, redis auth, vars nuevas para n8n/OpenWA/EA
- `Dockerfile` (EA): X-Frame-Options restaurado + CSP, imagen 1.4.3
- `Dockerfile` (Baileys): multi-stage build
- `baileys-service/index.js`: auth middleware en rutas POST
- `baileys-service/package-lock.json`: generado (0 vulns)
- `scripts/backup-mysql.ps1`: contraseña de variable de entorno
- `admin/index.php`: password_verify + rate limiting + CSRF
- `openwa/`: npm audit fix → 0 vulnerabilidades
- Puertos mailpit (1025, 8025) y openwa (2785) ocultos del host

---

## Resumen ejecutivo

La auditoría de seguridad estática del 2026-06-14 identificó **9 riesgos críticos (CR)**, **11 hallazgos sospechosos (SF)** y **12 observaciones (O)**. El sistema **no es seguro para desplegar en producción** en su estado actual.

La vulnerabilidad más grave es la presencia de **credenciales hardcodeadas en 13+ ubicaciones** (CR-1), combinada con **7 APIs internas sin autenticación** (CR-3, CR-4, CR-9). Un atacante que obtenga acceso al código fuente —por filtración del repositorio, acceso al servidor, o a la red Docker— obtiene control total: base de datos, envío de WhatsApp, cancelación de turnos, y panel de administración.

El plan de remediación se divide en tres fases. La **Fase 1** (P0) elimina los bloqueantes absolutos para cualquier deploy —credenciales hardcodeadas y endpoints sin autenticación— y debe completarse antes de exponer el sistema a internet. La **Fase 2** (P1) aplica hardening a la superficie de ataque y resuelve vulnerabilidades en dependencias. La **Fase 3** (P2-P3) aborda deuda técnica de seguridad para mejora continua. El contexto es un salón de uñas con tráfico moderado: la seguridad debe ser práctica y proporcional al riesgo, no teóricamente perfecta.

Para un desarrollador trabajando full-time, se estima un esfuerzo total de **46–60 horas** (≈1.5–2 semanas). Las Fases 1 y 2 juntas toman ≈30–40 horas y dejan el sistema en condiciones seguras para un deploy inicial.

---

## Plan de remediación

### Fase 1: Correcciones críticas (P0 — antes de cualquier deploy)

Hallazgos explotables remotamente sin autenticación o que representan compromiso total del sistema.

---

#### CR-1: Credenciales hardcodeadas en 13+ ubicaciones

**Acción concreta**: Centralizar todas las credenciales en un único archivo `.env` en la raíz del proyecto (fuera del repo, en `.gitignore`). Modificar todos los archivos que hoy tienen credenciales hardcodeadas para que lean de variables de entorno.

**Archivos afectados**:

| Archivo | Cambio requerido |
|---------|-----------------|
| `easyappointments/.env:4,7,10,14` | Mover a `.env` raíz, referenciar vía `${VAR}` en docker-compose |
| `easyappointments/docker-compose.yml:20,23,46` | Reemplazar valores hardcodeados por `${MYSQL_ROOT_PASSWORD}`, etc. |
| `landing-salon/index.php:5` | Leer `$_ENV['EA_API_USER']` y `$_ENV['EA_API_PASS']` |
| `landing-salon/api/servicios.php:8` | Ídem |
| `landing-salon/api/crear-turno.php:32` | Ídem |
| `landing-salon/api/horarios.php:13` | Ídem |
| `landing-salon/api/turnos-admin.php:11` | Ídem |
| `landing-salon/api/horarios-admin.php:12` | Ídem |
| `landing-salon/api/admin-servicios.php:12` | Ídem |
| `landing-salon/api/whatsapp-relay.php:31` | Leer `$_ENV['OPENWA_API_KEY']` |
| `landing-salon/api/whatsapp-send.php:29` | Ídem |
| `landing-salon/admin/index.php:7` | Usar `password_verify()` contra hash en variable de entorno |
| `scripts/backup-mysql.ps1:7` | Leer de variable de entorno `$env:DB_PASSWORD` |
| `n8n-workflows/fix-workflows.js:8-9` | Leer credenciales de variables de entorno |
| `n8n-workflows/WF3-cancelacion.json:158` | Mover `phone` a variable de entorno de n8n |

**Riesgo de la corrección**: Bajo. Es un cambio mecánico. El riesgo principal es romper la conectividad entre servicios si una variable de entorno no se propaga correctamente en docker-compose. Mitigación: probar cada endpoint después del cambio.

**Cómo verificar**:

```powershell
# 1. Confirmar que .env está en .gitignore
Select-String -Pattern '\.env' -LiteralPath '.gitignore'

# 2. Buscar credenciales residuales en el código fuente
rg --no-heading -n 'admin2024|ea_pass_2024|ea_root_secret|kamiikasee|dev-admin-key|tuahora_openwa_2024' --type-not md --type-not json

# 3. Levantar el stack y verificar conectividad
docker compose up -d
# Probar login en landing, creación de turno, envío de WhatsApp
```

**Nota**: Generar nuevas credenciales con `openssl rand -base64 32` para cada servicio. No reutilizar las actuales.

---

#### CR-2: Admin panel — password en texto plano sin hash ni rate limiting

**Acción concreta**: 
1. Almacenar el hash bcrypt del password admin en variable de entorno `ADMIN_PASSWORD_HASH`
2. Reemplazar la comparación `$pass === 'admin2024'` por `password_verify($pass, $_ENV['ADMIN_PASSWORD_HASH'])`
3. Agregar rate limiting básico: máximo 5 intentos fallidos en 15 minutos, usando un archivo de contador en `/tmp/admin_login_attempts.json` o sesiones PHP con timestamp

**Archivos afectados**:
- `landing-salon/admin/index.php:7` — Línea de comparación del password
- Nuevo: bloque de rate limiting a insertar antes de la verificación

**Riesgo de la corrección**: Bajo. `password_verify()` es estándar PHP. El rate limiting basado en archivo es simple pero suficiente para desarrollo/local. Si se escala, migrar a Redis.

**Cómo verificar**:

```powershell
# Generar hash para el nuevo password
php -r "echo password_hash('nuevo_password_seguro', PASSWORD_BCRYPT);"

# Probar login con password correcto e incorrecto
# Verificar que 6 intentos fallidos seguidos bloquean el acceso por 15 min
```

---

#### CR-3: Baileys Service — endpoints `/send-text` y `/send-reminder` sin autenticación

**Acción concreta**: Agregar middleware de autenticación por API key en `baileys-service/index.js`. Verificar header `X-API-Key` contra una variable de entorno `BAILEYS_API_KEY` antes de procesar cualquier request POST.

**Archivos afectados**:
- `baileys-service/index.js:165-200` — Insertar middleware antes de las rutas POST
- `baileys-service/.env` (nuevo) — Variable `BAILEYS_API_KEY`
- `easyappointments/docker-compose.yml` — Pasar `BAILEYS_API_KEY` como environment variable al contenedor baileys

**Código de referencia** (middleware a insertar):

```js
// Insertar después de app.use(express.json()) ~línea 20
const API_KEY = process.env.BAILEYS_API_KEY;
if (!API_KEY) {
    console.error('FATAL: BAILEYS_API_KEY no configurada');
    process.exit(1);
}

const authMiddleware = (req, res, next) => {
    const key = req.headers['x-api-key'];
    if (key !== API_KEY) {
        return res.status(401).json({ error: 'Unauthorized' });
    }
    next();
};

// Usar en rutas:
app.post('/send-text', authMiddleware, async (req, res) => { ... });
app.post('/send-reminder', authMiddleware, async (req, res) => { ... });
```

**Riesgo de la corrección**: Medio. Los servicios que llaman a Baileys (n8n, relays PHP) deben actualizarse para incluir el header `X-API-Key`. Si se omite alguno, el envío de WhatsApp fallará silenciosamente. Probar todo el flujo después del cambio.

**Cómo verificar**:

```powershell
# Sin API key → debe devolver 401
curl -X POST http://localhost:3001/send-text -H "Content-Type: application/json" -d '{"phone":"123","message":"test"}'

# Con API key → debe funcionar
curl -X POST http://localhost:3001/send-text -H "Content-Type: application/json" -H "X-API-Key: NUEVA_CLAVE" -d '{"phone":"123","message":"test"}'
```

---

#### CR-4: n8n webhooks sin autenticación ✅ Parcial

**Acción concreta**: Agregar autenticación por header a los webhooks de n8n (WF3-cancelacion y WF4-reagendado). En n8n, usar un node "Webhook" con el parámetro `authentication: "headerAuth"` y verificar un header `X-Webhook-Token` con un valor secreto configurado en variables de entorno de n8n (`N8N_WEBHOOK_TOKEN`).

**Archivos afectados**:
- `n8n-workflows/WF3-cancelacion.json:4-14` — Agregar `authentication` al webhook node
- `n8n-workflows/WF4-reagendado.json:4-14` — Ídem
- `easyappointments/docker-compose.yml:83` — Agregar `N8N_WEBHOOK_TOKEN` a las variables de entorno de n8n

**Riesgo de la corrección**: Medio. El webhook de WhatsApp entrante (Baileys → n8n) debe incluir este token en cada callback. La configuración del webhook en Baileys debe actualizarse para enviar el header. Probar el flujo de cancelación completo (mensaje WhatsApp → n8n procesa → EA actualiza).

**Cómo verificar**:

```powershell
# Webhook sin token → debe devolver 401
curl -X POST http://localhost:5678/webhook/whatsapp -H "Content-Type: application/json" -d '{}'

# Webhook con token → debe procesar
curl -X POST http://localhost:5678/webhook/whatsapp -H "Content-Type: application/json" -H "X-Webhook-Token: TOKEN_SECRETO" -d '{"test":true}'
```

**Acción concreta**: Agregar autenticación por header a los webhooks de n8n (WF3-cancelacion y WF4-reagendado). En n8n, usar un node "Webhook" con el parámetro `authentication: "headerAuth"` y verificar un header `X-Webhook-Token` con un valor secreto configurado en variables de entorno de n8n (`N8N_WEBHOOK_TOKEN`).

**Archivos afectados**:
- `n8n-workflows/WF3-cancelacion.json:4-14` — Agregar `authentication` al webhook node
- `n8n-workflows/WF4-reagendado.json:4-14` — Ídem
- `easyappointments/docker-compose.yml:83` — Agregar `N8N_WEBHOOK_TOKEN` a las variables de entorno de n8n

**Riesgo de la corrección**: Medio. El webhook de WhatsApp entrante (Baileys → n8n) debe incluir este token en cada callback. La configuración del webhook en Baileys debe actualizarse para enviar el header. Probar el flujo de cancelación completo (mensaje WhatsApp → n8n procesa → EA actualiza).

**Cómo verificar**:

```powershell
# Webhook sin token → debe devolver 401
curl -X POST http://localhost:5678/webhook/whatsapp -H "Content-Type: application/json" -d '{}'

# Webhook con token → debe procesar
curl -X POST http://localhost:5678/webhook/whatsapp -H "Content-Type: application/json" -H "X-Webhook-Token: TOKEN_SECRETO" -d '{"test":true}'
```

---

#### CR-5: Tokens de sesión reales commiteados en `cookies.txt`

**Acción concreta**:
1. Agregar `cookies.txt` a `.gitignore`
2. Remover `cookies.txt` del tracking de git: `git rm --cached cookies.txt`
3. Regenerar las sesiones de EasyAppointments (cerrar sesión y volver a iniciar en el navegador) para invalidar los tokens expuestos
4. Hacer commit del cambio

**Archivos afectados**:
- `.gitignore` — Agregar entrada `cookies.txt`
- `cookies.txt` — Remover del repositorio

**Riesgo de la corrección**: Ninguno. Es una operación de limpieza. El archivo puede seguir existiendo localmente (estará ignorado), pero no se commitea más.

**Cómo verificar**:

```powershell
git rm --cached cookies.txt
git status  # cookies.txt no debe aparecer como tracked
# Confirmar que cookies.txt está en .gitignore
```

---

#### CR-9: WhatsApp Relay PHP sin autenticación (`whatsapp-relay.php`, `whatsapp-send.php`)

**Acción concreta**: Proteger ambos endpoints con verificación de sesión de administrador (`$_SESSION['tuahora_admin']`). Si no hay sesión activa, devolver 401. Como estos endpoints son llamados desde el frontend admin (que ya tiene sesión), esto cierra el agujero sin afectar funcionalidad.

**Archivos afectados**:
- `landing-salon/api/whatsapp-relay.php:1-5` — Agregar `session_start()` y verificación de `$_SESSION['tuahora_admin']`
- `landing-salon/api/whatsapp-send.php:1-5` — Ídem
- `landing-salon/api/whatsapp-send.php:31` — Remover `X-API-Key: dev-admin-key` hardcodeado, usar variable de entorno

**Riesgo de la corrección**: Bajo. Si el panel admin llama a estos endpoints sin sesión (por ejemplo, desde JS sin cookies), fallará. Verificar que el admin panel envía las cookies de sesión en las peticiones fetch/XHR.

**Cómo verificar**:

```powershell
# Sin sesión → 401
curl -X POST http://localhost/api/whatsapp-relay.php -H "Content-Type: application/json" -d '{"chatId":"123","text":"test"}'

# Con sesión de admin → debe funcionar
# (Probar desde el navegador con sesión iniciada en el panel admin)
```

---

### Fase 2: Hardening (P1 — esta iteración)

Hallazgos que requieren acceso a red interna o código fuente para ser explotados. Se resuelven en la misma iteración.

---

#### CR-6: X-Frame-Options removido intencionalmente — riesgo de clickjacking

**Acción concreta**: En lugar de simplemente remover `X-Frame-Options`, implementar una política de iframe controlada vía `Content-Security-Policy: frame-ancestors 'self' https://tudominio.com`. Esto permite iframe solo desde el mismo origen y el dominio legítimo del salón, bloqueando clickjacking desde sitios maliciosos.

**Archivos afectados**:
- `easyappointments/Dockerfile:3-4` — Reemplazar los dos `sed` por:
  ```dockerfile
  RUN echo 'header("Content-Security-Policy: frame-ancestors 'self';");' >> /var/www/html/application/hooks/security_headers.php
  ```
- Opcional: agregar CSP también en `.htaccess` de `landing-salon/`

**Riesgo de la corrección**: Bajo. Si el iframe se usa desde un dominio externo (ej: `instagram.com`), el CSP debe incluir ese dominio explícitamente. Verificar qué dominios embeben la landing actualmente.

**Cómo verificar**:

```powershell
# Verificar headers
curl -I http://localhost:8080
# Debe aparecer: Content-Security-Policy: frame-ancestors 'self'
```

---

#### CR-7: CORS wildcard en todas las APIs PHP

**Acción concreta**: Reemplazar `Access-Control-Allow-Origin: *` por el origen específico de la landing page. Centralizar en un archivo de configuración compartido.

**Archivos afectados**:
- `landing-salon/api/servicios.php:3`
- `landing-salon/api/crear-turno.php:3`
- `landing-salon/api/horarios.php:3`
- `landing-salon/api/turnos-admin.php:10`
- `landing-salon/api/horarios-admin.php:9`
- `landing-salon/api/admin-servicios.php:9`

**Implementación**: Crear `landing-salon/api/config.php` con:

```php
<?php
define('ALLOWED_ORIGIN', $_ENV['CORS_ORIGIN'] ?? 'http://localhost');
header("Access-Control-Allow-Origin: " . ALLOWED_ORIGIN);
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, X-API-Key");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
```

Reemplazar el header CORS individual en cada API por `require_once __DIR__ . '/config.php';`.

**Riesgo de la corrección**: Bajo. Si la landing page y las APIs están en dominios distintos, el CORS debe reflejar el dominio correcto (`CORS_ORIGIN` en `.env`). Para desarrollo local, usar `http://localhost`.

**Cómo verificar**:

```powershell
curl -I -H "Origin: http://localhost" http://localhost/api/servicios.php
# Access-Control-Allow-Origin debe ser exactamente http://localhost, no *
```

---

#### CR-8: OpenWA — 21 vulnerabilidades npm (2 críticas, 10 altas)

**Acción concreta**: 
1. Actualizar dependencias con `npm audit fix` en `openwa/`
2. Para las que no se resuelven automáticamente, actualizar manualmente:
   - `shell-quote`: forzar ≥1.8.5 en `overrides` de `package.json`
   - `protobufjs`: actualizar a ≥7.4.0
   - `tar`: evaluar si la breaking change de `sqlite3@6.0.1` afecta; si no es viable, aislar el impacto (se usa solo en devDependencies de `concurrently`)
3. Ejecutar `npm audit` nuevamente para confirmar 0 vulnerabilidades críticas/altas

**Archivos afectados**:
- `openwa/package.json` — Agregar sección `overrides`
- `openwa/package-lock.json` — Se regenera automáticamente

**Riesgo de la corrección**: Medio. Actualizar `protobufjs` puede romper compatibilidad con `@whiskeysockets/baileys`. Probar el flujo completo de WhatsApp (QR login, envío, recepción) después de la actualización. Si algo se rompe, evaluar actualizar Baileys a una versión más reciente que use dependencias actualizadas.

**Cómo verificar**:

```powershell
cd openwa
npm audit fix
npm audit  # Debe mostrar 0 critical, 0 high
# Levantar OpenWA y probar envío/recepción de mensajes
```

---

#### SF-1 / O-12: Imágenes Docker con tag `latest`

**Acción concreta**: Fijar versiones específicas en todas las imágenes Docker del proyecto.

**Archivos afectados**:
- `easyappointments/docker-compose.yml:69` — `n8nio/n8n:1.92.0` (o última estable verificada)
- `easyappointments/docker-compose.yml:88` — `axllent/mailpit:1.23`
- `easyappointments/Dockerfile:1` — `alextselegidis/easyappointments:1.4.3`

**Riesgo de la corrección**: Ninguno. Solo cambia el tag. Las versiones específicas deben verificarse contra Docker Hub para confirmar que existen.

**Cómo verificar**:

```powershell
docker compose pull  # Debe descargar las versiones específicas sin errores
docker compose up -d  # El stack debe levantar normalmente
```

---

#### SF-3: Build tools en producción (Baileys Dockerfile)

**Acción concreta**: Usar multi-stage build en `baileys-service/Dockerfile`. Instalar `python3 make g++ git` solo en la etapa de build, y copiar solo los artefactos compilados a la imagen final.

**Archivos afectados**:
- `baileys-service/Dockerfile` — Reestructurar a multi-stage

**Riesgo de la corrección**: Bajo. La reestructuración a multi-stage puede requerir ajustes en paths de node_modules. Probar que la imagen resultante ejecuta Baileys correctamente.

**Cómo verificar**:

```powershell
docker build -t baileys-test ./baileys-service
docker run --rm baileys-test node -e "console.log('OK')"
# Confirmar que no hay gcc, g++, make, git en la imagen:
docker run --rm baileys-test which gcc  # Debe devolver error/empty
```

---

#### SF-4: Puerto de Mailpit expuesto al host

**Acción concreta**: Comentar o remover el mapeo de puertos `1025:1025` y `8025:8025` en `docker-compose.yml`. Mailpit solo necesita ser accesible desde la red Docker interna (`stack`) para que EasyAppointments envíe emails. Para acceder a la UI durante desarrollo, descomentar temporalmente.

**Archivos afectados**:
- `easyappointments/docker-compose.yml:91-94` — Comentar los `ports:` de mailpit

**Riesgo de la corrección**: Ninguno en producción. En desarrollo, si se necesita ver la UI de Mailpit, acceder vía `docker compose exec` o descomentar los puertos localmente.

**Cómo verificar**:

```powershell
docker compose up -d
# 8025 no debe responder desde el host
curl http://localhost:8025  # Debe fallar
```

---

#### SF-8: Baileys sin `package-lock.json`

**Acción concreta**: 
1. Ejecutar `npm install` en `baileys-service/` para generar `package-lock.json`
2. Agregarlo al repositorio
3. Ejecutar `npm audit` para identificar vulnerabilidades en dependencias de Baileys

**Archivos afectados**:
- `baileys-service/package-lock.json` — Nuevo archivo generado
- `.gitignore` — Confirmar que NO ignora `package-lock.json`

**Riesgo de la corrección**: Ninguno. El lockfile garantiza builds determinísticas.

**Cómo verificar**:

```powershell
cd baileys-service
npm install
Test-Path "package-lock.json"  # Debe existir
npm audit  # Revisar si hay vulnerabilidades nuevas
```

---

#### SF-10: Puerto OpenWA expuesto sin restricción

**Acción concreta**: Remover el mapeo `2785:2785` del docker-compose. OpenWA solo necesita ser accesible desde la red interna `stack` (por n8n y los relays PHP). La landing page no necesita acceso directo.

**Archivos afectados**:
- `easyappointments/docker-compose.yml:103` — Comentar o remover `"2785:2785"`

**Riesgo de la corrección**: Ninguno si los servicios internos se comunican por nombre de contenedor (`openwa:2785`). Confirmar que n8n y los PHP relays usan el hostname docker, no `localhost:2785`.

**Cómo verificar**:

```powershell
docker compose up -d
curl http://localhost:2785  # Debe fallar (puerto no expuesto)
docker compose exec n8n curl http://openwa:2785  # Debe responder (red interna)
```

---

#### SF-11: API key `dev-admin-key` como clave por defecto

**Acción concreta**: Eliminar toda referencia a `dev-admin-key`. Generar una nueva API key para OpenWA y configurarla como variable de entorno en docker-compose. Actualizar los relays PHP para leer esta variable.

**Archivos afectados**:
- `landing-salon/api/whatsapp-relay.php:31` — Ya resuelto en CR-1 (lee de `$_ENV`)
- `landing-salon/api/whatsapp-send.php:29` — Ya resuelto en CR-1
- `easyappointments/docker-compose.yml` — Agregar `OPENWA_API_KEY` al contenedor de la landing

**Riesgo de la corrección**: Bajo. Depende de CR-1 estar completado.

**Cómo verificar**:

```powershell
rg --no-heading -n 'dev-admin-key' --type-not md
# Debe devolver 0 resultados
```

---

#### O-7: Redis sin autenticación

**Acción concreta**: Configurar `requirepass` en Redis mediante variable de entorno en docker-compose o archivo de configuración montado. Actualizar la URL de conexión en Baileys a `redis://:PASSWORD@redis:6379`.

**Archivos afectados**:
- `easyappointments/docker-compose.yml` — Agregar `command: redis-server --requirepass ${REDIS_PASSWORD}` al servicio redis
- `baileys-service/index.js:8` — Cambiar `REDIS_URL` a `redis://:${REDIS_PASSWORD}@redis:6379`

**Riesgo de la corrección**: Bajo. Si la password de Redis no se sincroniza entre docker-compose y Baileys, las sesiones de WhatsApp no persistirán (se regenera QR cada vez que Baileys reinicia).

**Cómo verificar**:

```powershell
docker compose exec redis redis-cli -a $REDIS_PASSWORD PING  # Debe devolver PONG
docker compose exec redis redis-cli PING  # Sin password → debe devolver NOAUTH
```

---

#### O-11: Admin panel sin protección CSRF

**Acción concreta**: Generar un token CSRF al iniciar sesión, almacenarlo en sesión, incluirlo como campo hidden en formularios POST, y verificarlo en cada acción que modifique estado (cambio de configuración, envío de WhatsApp, etc.).

**Archivos afectados**:
- `landing-salon/admin/index.php:7` — Al establecer `$_SESSION['tuahora_admin']`, generar `$_SESSION['csrf_token'] = bin2hex(random_bytes(32))`
- Formularios en `landing-salon/admin/` — Agregar `<input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">`
- Endpoints que procesan POST — Verificar `$_POST['csrf_token'] === $_SESSION['csrf_token']` antes de ejecutar la acción

**Riesgo de la corrección**: Bajo. Es un patrón estándar. Asegurarse de que todos los formularios POST en el admin panel incluyan el token.

**Cómo verificar**:

```powershell
# POST sin token CSRF → debe ser rechazado
# POST con token CSRF válido → debe funcionar
```

---

#### SF-2: Teléfono de la dueña hardcodeado en workflow

**Acción concreta**: Mover el número de teléfono (`3826405610`) a una variable de entorno de n8n (`N8N_OWNER_PHONE`). Modificar el workflow WF3 para leer de `{{ $env.N8N_OWNER_PHONE }}` en lugar del valor hardcodeado.

**Archivos afectados**:
- `n8n-workflows/WF3-cancelacion.json:158` — Reemplazar valor por `{{ $env.N8N_OWNER_PHONE }}`
- `easyappointments/docker-compose.yml:83` — Agregar `N8N_OWNER_PHONE` a environment de n8n

**Riesgo de la corrección**: Muy bajo. Solo cambia la fuente del valor.

**Cómo verificar**:

```powershell
rg --no-heading -n '3826405610'
# Debe devolver 0 resultados (excepto en este documento y el reporte de auditoría)
```

---

#### O-9: OpenWA session ID hardcodeado en relays PHP

**Acción concreta**: Definir `OPENWA_SESSION_ID` como variable de entorno y leerla en `whatsapp-relay.php:19` y `whatsapp-send.php:17`.

**Archivos afectados**:
- `landing-salon/api/whatsapp-relay.php:19` — `$sessionId = $_ENV['OPENWA_SESSION_ID'];`
- `landing-salon/api/whatsapp-send.php:17` — Ídem

**Riesgo de la corrección**: Ninguno. Es un cambio mecánico.

**Cómo verificar**: Probar envío de WhatsApp desde el admin panel después del cambio.

---

### Fase 3: Mejoras continuas (P2-P3 — próximas iteraciones)

Observaciones, deuda técnica y mejores prácticas que no bloquean el deploy.

---

#### P2 — Próxima iteración (1–2 sprints)

| ID | Acción | Archivos | Esfuerzo |
|----|--------|----------|----------|
| **SF-5** | Extraer base URL de EA a variable de entorno (`EA_BASE_URL`). Reemplazar `http://localhost/index.php/api/v1` en todos los PHP. | `landing-salon/index.php:2`, `api/crear-turno.php:33`, `api/horarios.php:14`, `api/turnos-admin.php:12` | 2h |
| **SF-6** | Mejorar email placeholder: usar `no-email-{hash}@tuahora.com.ar` con hash del phone para evitar identificación por email predecible. | `landing-salon/api/crear-turno.php:69` | 0.5h |
| **SF-7** | Sanitizar mensajes de error expuestos al cliente. Envolver en mensajes genéricos y loguear el detalle internamente. | `landing-salon/api/crear-turno.php:88,126`, `api/turnos-admin.php:125`, `api/horarios-admin.php:77`, `api/admin-servicios.php` | 2h |
| **O-1** | Configurar HTTPS. Opción recomendada: Cloudflare Tunnel (gratuito, sin exponer puertos). Alternativa: Traefik/Caddy como reverse proxy con Let's Encrypt. | `docker-compose.yml`, nuevo `docker-compose.prod.yml` | 4h |
| **O-2** | Agregar rate limiting en endpoints públicos. PHP: contador basado en IP en archivo temporal. N8n: usar node RateLimit si disponible. Baileys: usar `express-rate-limit`. | `landing-salon/api/*.php`, `baileys-service/index.js` | 3h |
| **O-3** | Agregar header `Content-Security-Policy` en todas las páginas HTML/PHP. | `landing-salon/*.php`, `easyappointments/Dockerfile` | 2h |
| **O-8** | Separar paths de webhooks n8n: WF3 → `whatsapp-cancelacion`, WF4 → `whatsapp-reagendado`. | `n8n-workflows/WF3-cancelacion.json`, `WF4-reagendado.json` | 0.5h |
| **O-12** | Pin de `easyappointments` image (cubierto en SF-1) | Ya listo en Fase 2 | — |

---

#### P3 — Backlog / mejora continua

| ID | Acción | Archivos | Esfuerzo |
|----|--------|----------|----------|
| **SF-9** | Refactorizar `fix-workflows.js`: declarar dependencias en `package.json`, mover rutas a variables de entorno, quitar credenciales embebidas. | `n8n-workflows/fix-workflows.js`, `n8n-workflows/package.json` (nuevo) | 1.5h |
| **O-5** | Configurar backup automático de SQLite de n8n. Script que copie `~/.n8n/database.sqlite` a volumen persistente cada N horas. | `scripts/backup-n8n.ps1` (nuevo) | 2h |
| **O-6** | Hacer portable el script de backup: detectar 7-Zip automáticamente o usar `Compress-Archive` nativo de PowerShell. | `scripts/backup-mysql.ps1:38` | 1h |
| **O-10** | Implementar rotación de logs en `/tmp/whatsapp-relay.log`: limitar a 10MB, rotar cada 7 días, o usar stdout/stderr y dejar que Docker maneje los logs. | `landing-salon/api/whatsapp-send.php:9` | 1h |
| **O-2bis** | Implementar rate limiting robusto con Redis (aprovechando que ya está en el stack). Reemplazar rate limiting basado en archivo. | `landing-salon/admin/index.php`, `baileys-service/index.js` | 2h |
| **General** | Agregar healthcheck a todos los contenedores en docker-compose. | `easyappointments/docker-compose.yml` | 1h |
| **General** | Ejecutar `docker compose config` y `docker scout` para validar configuración e imágenes. | — | 0.5h |

---

## Estimación de esfuerzo

| Fase | Hallazgos | Horas estimadas | Entregable |
|------|-----------|-----------------|------------|
| **Fase 1** (P0) | 6 (CR-1, CR-2, CR-3, CR-4, CR-5, CR-9) | **12–16 h** | Sistema sin credenciales hardcodeadas ni endpoints abiertos |
| **Fase 2** (P1) | 12 (CR-6, CR-7, CR-8, SF-1, SF-2, SF-3, SF-4, SF-8, SF-10, SF-11, O-7, O-11, O-9, O-12) | **18–24 h** | Sistema hardenizado, dependencias actualizadas, listo para deploy |
| **Fase 3** (P2) | 8 | **12–16 h** | HTTPS, rate limiting, CSP, sanitización de errores |
| **Fase 3** (P3) | 7 | **9–12 h** | Backups, portabilidad, monitoreo |
| **Total** | **33** | **51–68 h** | ≈ 1.5–2 semanas (1 dev full-time) |

**Nota**: El esfuerzo asume 1 desarrollador con conocimiento del stack (PHP, Node.js, Docker, n8n). Las estimaciones incluyen prueba y verificación post-cambio.

---

## Dependencias

### Antes de iniciar Fase 1
- Acceso al repositorio y permisos de escritura
- Docker Desktop funcionando localmente
- Stack actual corriendo (`docker compose up -d` exitoso)
- Conocer los dominios desde donde se embeberá la landing (si usa iframe)

### Antes de iniciar Fase 2
- **Fase 1 completada** (las credenciales ya no están hardcodeadas; si se hardenan endpoints que dependen de API keys, estas ya existen como variables de entorno)
- `openssl` disponible en el sistema (viene con Git for Windows)

### Antes de iniciar Fase 3
- **Fase 1 y Fase 2 completadas**
- P2: Decisión sobre método de HTTPS (Cloudflare Tunnel vs reverse proxy)
- P2: Dominio configurado (si aplica)
- P3: Definir política de backup y retención

---

## Verificación post-remediación

### Checklist de verificación — Fase 1

- [ ] `rg "admin2024|ea_pass_2024|ea_root_secret|kamiikasee|dev-admin-key" --type-not md --type-not json` devuelve 0 resultados
- [ ] `cookies.txt` no aparece en `git status` (está en .gitignore)
- [ ] `.env` está en `.gitignore` y NO commiteado
- [ ] `docker compose up -d` levanta todos los servicios sin errores
- [ ] Login al admin panel funciona con el nuevo password hasheado
- [ ] POST a `baileys-service:3001/send-text` sin header `X-API-Key` devuelve 401
- [ ] POST a `baileys-service:3001/send-text` CON header `X-API-Key` correcto funciona
- [ ] POST a n8n webhooks sin token devuelve error
- [ ] GET a `whatsapp-send.php` sin sesión admin devuelve 401
- [ ] Flujo completo funcional: landing → reserva → WhatsApp confirmación → cancelación

### Checklist de verificación — Fase 2

- [ ] `curl -I http://localhost:8080` incluye `Content-Security-Policy: frame-ancestors 'self'`
- [ ] `curl -I -H "Origin: http://evil.com" http://localhost/api/servicios.php` NO devuelve `*` en ACAO
- [ ] `npm audit` en `openwa/` muestra 0 críticas y 0 altas
- [ ] `npm audit` en `baileys-service/` muestra resultados auditables
- [ ] `docker images` muestra tags específicos (no `latest`) para n8n, mailpit, easyappointments
- [ ] `docker run --rm baileys-service which gcc` falla (no build tools en imagen)
- [ ] Puerto `8025` (Mailpit UI) no responde desde el host
- [ ] Puerto `2785` (OpenWA) no responde desde el host
- [ ] `redis-cli` sin password devuelve `NOAUTH`
- [ ] Formularios del admin panel incluyen campo `csrf_token`
- [ ] POST al admin panel sin CSRF token es rechazado

### Verificación de configuración Docker

```powershell
# Validar sintaxis de docker-compose
docker compose config --quiet

# Verificar variables de entorno sin resolver (posibles leaks)
docker compose config | Select-String "PASSWORD|SECRET|KEY"

# Listar puertos expuestos (deben ser mínimos)
docker compose ps --format "table {{.Name}}\t{{.Ports}}"
```

### Comando para re-ejecutar auditoría

```powershell
# Cargar skill y re-auditar
# En OpenCode: "Ejecutá la auditoría de seguridad"
# O manualmente:
npm audit --prefix openwa
npm audit --prefix baileys-service
rg --no-heading -n 'admin2024|ea_pass_2024|kamiikasee|dev-admin-key|tuahora_openwa_2024' --type-not md --type-not json
docker scout quickview
```

---

## Relacionado

- [[SecurityAudit-Report]] — Reporte completo de hallazgos (origen de todos los IDs)
- [[SecurityAudit-Plan]] — Criterios de auditoría y cuándo re-ejecutar
- [[Arquitectura]] — Diagrama del stack y flujo de datos
- [[EstadoProyecto]] — Actualizar con el estado de esta propuesta
- [[Roadmap]] — Incluir las fases como milestones de seguridad
