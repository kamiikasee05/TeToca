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

## Paso 2 — Configurar variables de entorno

```bash
# Crear .env desde el template
cp .env.example .env

# Editar con tus valores reales
nano .env
```

Llená **todas** las variables. Las críticas son:

| Variable | Qué poner |
|---|---|
| `SCHEDULER_API_KEY` | Una clave larga aleatoria (32+ chars) |
| `OPENWA_API_KEY` | Otra clave aleatoria (32+ chars) |
| `API_MASTER_KEY` | La misma que OPENWA_API_KEY |
| `OPENWA_SESSION_ID` | Un UUID (generalo con `uuidgen` en WSL) |
| `N8N_WEBHOOK_TOKEN` | Otra clave aleatoria |
| `N8N_OWNER_PHONE` | Tu número de WhatsApp con código de país |
| `ADMIN_PASSWORD_HASH` | Hash bcrypt de tu password |
| `REDIS_PASSWORD` | Otra clave aleatoria |
| `CORS_ORIGIN` | `http://192.168.18.11` (la IP del server) |

Para generar el hash del password admin:
```bash
# En WSL, si tenés php instalado:
php -r "echo password_hash('tu_password', PASSWORD_BCRYPT);"

# O podés usar el hash por defecto para "admin2024" y cambiarlo después:
# $2y$10$EixZaYVK1fsbw1ZfbX3OXe.P0jFGnJvfMlL6qNvGkRKlX3cMfSm7u
```

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

1. Entrá a `http://192.168.18.11:2785` → OpenWA Dashboard
2. Creá una nueva sesión con el `OPENWA_SESSION_ID` que pusiste en `.env`
3. Escaneá el QR con tu WhatsApp
4. Configurá el webhook en OpenWA:
   - **URL**: `http://n8n:5678/webhook/whatsapp-inbound`
   - **Evento**: `message.received`
   - **API Key**: la que pusiste en `OPENWA_API_KEY`

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
