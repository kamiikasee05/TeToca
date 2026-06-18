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

## Paso 2 — Ejecutar el setup interactivo

```bash
chmod +x setup.sh
./setup.sh
```

El script te va a preguntar:

| Pregunta | Ejemplo |
|---|---|
| Nombre del negocio | Cuchi Mua |
| Frase corta | Manicura profesional en Chamical |
| Dirección | Mitre 456, Chamical |
| Nombre del profesional | Cecilia Natali Godoy |
| Instagram | @cuchi_mua |
| Número de WhatsApp | 5493826403110 |
| Colores (primario, secundario, acento, texto) | #e8a0a0, #f5f0f0, #b56576, #2d2d2d |
| Contraseña del panel admin | (la que elijas, mínimo 6 chars) |
| IP o dominio del servidor | 192.168.18.11 |

**Todo lo demás lo genera solo:** claves aleatorias, UUID de sesión, hash de la contraseña, archivos `.env` y `config.json`.

Al terminar te muestra un resumen con el Session ID y la API Key — **guardalos** que los vas a necesitar en el paso 7 para vincular WhatsApp.

---

## Paso 3 — Listo, la configuración ya está hecha

El `setup.sh` del paso 2 ya generó todo. Si necesitás cambiar algún dato después, editá:

```bash
nano .env                          # claves, contraseñas, IP
nano landing/config.json           # nombre, dirección, colores, fotos
nano landing-salon/config.json     # lo mismo para el panel admin

---

## Paso 4 — Clonar y buildear OpenWA

```bash
chmod +x clone-openwa.sh
./clone-openwa.sh
```

Esto clona el motor de WhatsApp desde GitHub y buildea la imagen Docker. Tarda unos minutos (instala Chromium).

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
