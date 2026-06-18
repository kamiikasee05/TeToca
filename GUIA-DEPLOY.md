# TeToca — Guía de instalación en producción

## Requisitos previos

- Windows 10/11 con **WSL2** instalado
- **Docker Desktop** corriendo (con integración WSL2)
- Git instalado

---

## Paso 1 — Abrir WSL y clonar el repo

Abrí una terminal de WSL (Ubuntu o la distro que tengas) y ejecutá:

```bash
# Ir al disco D: (WSL lo monta en /mnt/d)
cd /mnt/d

# Clonar el proyecto
git clone https://github.com/kamiikasee05/TeToca.git TETOCA
cd TETOCA
```

---

## Paso 2 — Generar todas las claves y armar el .env

Abrí una terminal WSL y ejecutá estos comandos **uno por uno**. Cada uno genera una clave distinta. Guardá todo en un bloc de notas aparte, que lo vas a necesitar en el paso siguiente.

### 2.1 — Generar las claves aleatorias

```bash
# Clave para proteger la API del scheduler (32 caracteres hex)
echo "SCHEDULER_API_KEY: $(openssl rand -hex 16)"

# Clave para autenticarse contra OpenWA (32 caracteres hex)
echo "OPENWA_API_KEY: $(openssl rand -hex 16)"

# API_MASTER_KEY debe ser IDENTICA a OPENWA_API_KEY. Guardala para usarla después.

# Token para proteger los webhooks de n8n
echo "N8N_WEBHOOK_TOKEN: $(openssl rand -hex 16)"

# Contraseña para Redis
echo "REDIS_PASSWORD: $(openssl rand -hex 16)"
```

### 2.2 — Generar el ID de sesión de WhatsApp

El `OPENWA_SESSION_ID` **no se obtiene de ningún lado** — lo inventás vos ahora. Es un identificador único que va a identificar tu sesión de WhatsApp en OpenWA. Después, en el paso 7, vas a usar este mismo ID para crear la sesión en el dashboard.

```bash
echo "OPENWA_SESSION_ID: $(uuidgen)"
```

Esto te devuelve algo como `b7f1a3d9-4c2e-8f6b-9a0d-1e3f5c7b9a2d`.

### 2.3 — Configurar tu número de WhatsApp

```bash
# Reemplazá 5493826403110 por TU número (código de país + número, sin + ni espacios)
echo "N8N_OWNER_PHONE: 5493826403110"
```

### 2.4 — Generar el hash de la contraseña del admin

Necesitás PHP instalado en WSL. Si no lo tenés, instalalo:

```bash
sudo apt update && sudo apt install php-cli -y
```

Después generá el hash (cambia `tu_contraseña_segura` por la que quieras usar para entrar al panel):

```bash
php -r "echo 'ADMIN_PASSWORD_HASH: ' . password_hash('tu_contraseña_segura', PASSWORD_BCRYPT) . PHP_EOL;"
```

Esto devuelve un hash largo tipo `$2y$10$EixZaYVK1fsbw1ZfbX3OXe.P0jFGnJvfMlL6qNvGkRKlX3cMfSm7u`. **Ese hash entero** (con los `$`) va en la variable. Con esto entrás al dashboard con usuario `admin` y la contraseña que elegiste.

### 2.5 — Configurar CORS

```bash
# La IP de tu server — desde dónde se va a acceder a la landing
echo "CORS_ORIGIN: http://192.168.18.11"
```

### 2.6 — Armar el archivo .env

```bash
cp .env.example .env
nano .env
```

Reemplazá cada placeholder `CAMBIAR_*` con los valores que generaste. **Importante:** `API_MASTER_KEY` debe ser el **mismo valor** que `OPENWA_API_KEY`.

Tu `.env` final debería verse así:

```env
SCHEDULER_API_KEY=a7f3b9c2d1e8f4a6b3c9d2e1f8a4b6c7
OPENWA_API_KEY=b8e4a0d3e2f9c5b7a4d0e3f2c9b5a7d8
API_MASTER_KEY=b8e4a0d3e2f9c5b7a4d0e3f2c9b5a7d8
OPENWA_SESSION_ID=b7f1a3d9-4c2e-8f6b-9a0d-1e3f5c7b9a2d
N8N_WEBHOOK_TOKEN=c9f5b1e4d3a0c6b8e5f1d4a3b0c6e8f9
N8N_OWNER_PHONE=5493826403110
ADMIN_PASSWORD_HASH=$2y$10$EixZaYVK1fsbw1ZfbX3OXe.P0jFGnJvfMlL6qNvGkRKlX3cMfSm7u
REDIS_PASSWORD=d0a6c2f5e4b1d7c9f6a2e5d4b1c7d9e0
CORS_ORIGIN=http://192.168.18.11
```

> **No copies las claves de arriba**, son de ejemplo. Usá las que generaste vos.

---

## Paso 3 — Configurar landing

```bash
# Crear config de landing desde el template
cp landing/config.example.json landing/config.json

# Editar con los datos de tu negocio
nano landing/config.json
```

```json
{
    "brand": {
        "name": "Cuchi Mua",
        "tagline": "Manicura profesional",
        "address": "Mitre 456, Chamical",
        "whatsapp": "5493826403110",
        "instagram": "@cuchi_mua",
        "profesional": "Cecilia Natali Godoy"
    },
    "colors": {
        "primary": "#e8a0a0",
        "secondary": "#f5f0f0",
        "accent": "#b56576",
        "text": "#2d2d2d",
        "background": "#ffffff"
    },
    "logo": "uploads/logo.png",
    "gallery": []
}
```

Hacé lo mismo para el admin:
```bash
cp landing-salon/config.example.json landing-salon/config.json
nano landing-salon/config.json
```

---

## Paso 4 — Construir imagen de OpenWA

OpenWA necesita buildearse localmente (está en `openwa/`):

```bash
cd openwa
docker build -t openwa-openwa:latest .
cd ..
```

> Esto tarda unos minutos, instala Chromium para Puppeteer.

---

## Paso 5 — Levantar el stack

```bash
# Dar permisos de ejecución al script
chmod +x deploy.sh

# Desplegar
./deploy.sh
```

El script:
1. Valida que `.env` tenga todas las variables
2. Baja las imágenes públicas (nginx, n8n, redis, mailpit)
3. Buildea las locales (scheduler, landing-admin)
4. Levanta los 7 servicios
5. Muestra el estado y logs

---

## Paso 6 — Verificar

```bash
# Ver que estén todos corriendo
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps

# Ver logs en tiempo real
docker compose -f docker-compose.yml -f docker-compose.prod.yml logs -f
```

Abrí en el navegador:

| URL | Qué es |
|---|---|
| `http://192.168.18.11` | Landing pública |
| `http://192.168.18.11/admin` | Dashboard admin |
| `http://192.168.18.11:2785` | OpenWA (WhatsApp) |

Credenciales del admin: usuario `admin`, password el que pusiste en `ADMIN_PASSWORD_HASH`.

---

## Paso 7 — Vincular WhatsApp

1. Entrá a `http://192.168.18.11:2785` → se abre el **Dashboard de OpenWA**
2. Andá a **Sessions** y creá una sesión **nueva**
3. En el campo **Session ID** poné el mismo `OPENWA_SESSION_ID` que generaste en el paso 2.2 (ej: `b7f1a3d9-4c2e-8f6b-9a0d-1e3f5c7b9a2d`)
4. Poné **Start session** — va a aparecer un **código QR**
5. Abrí WhatsApp en tu celular → **Dispositivos vinculados** → **Vincular un dispositivo**
6. Escaneá el QR. Cuando conecte, el dashboard muestra "Connected"
7. En el mismo dashboard, andá a **Webhooks** y configurá:
   - **URL**: `http://n8n:5678/webhook/whatsapp-inbound`
   - **Evento**: `message.received`
   - **Header X-API-Key**: la misma clave que pusiste en `OPENWA_API_KEY`
   - Guardá

---

## Comandos útiles

```bash
# Reiniciar todo
docker compose -f docker-compose.yml -f docker-compose.prod.yml restart

# Bajar todo
docker compose -f docker-compose.yml -f docker-compose.prod.yml down

# Ver logs de un servicio específico
docker compose logs scheduler

# Reconstruir una imagen después de cambios
docker compose -f docker-compose.yml -f docker-compose.prod.yml build scheduler
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d scheduler
```
