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

## Paso 2 — Ejecutar el setup (hace TODO)

```bash
chmod +x setup.sh
./setup.sh
```

El script hace **todo** de una sola pasada:

1. Verifica e instala dependencias (openssl, php, git, docker)
2. Te pregunta los datos del negocio (nombre, dirección, teléfono, colores, IP)
3. Genera todas las claves automáticamente
4. Crea `.env`, `landing/config.json` y `landing-salon/config.json`
5. Clona el motor de WhatsApp (OpenWA) desde GitHub
6. Buildea las 3 imágenes Docker (openwa, scheduler, admin)

Al terminar te muestra el **Session ID** y la **API Key** — guardalos para vincular WhatsApp después.

---

## Paso 3 — Desplegar

```bash
chmod +x deploy.sh
./deploy.sh
```

Baja imágenes públicas, levanta los 7 servicios y muestra el estado.

---

## Paso 4 — Verificar

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml ps
```

Abrí en el navegador:

| URL | Qué es |
|---|---|
| `http://192.168.18.11` | Landing pública |
| `http://192.168.18.11/admin` | Dashboard admin (usuario: `admin`) |
| `http://192.168.18.11:2785` | OpenWA Dashboard |

---

## Paso 5 — Vincular WhatsApp

1. Entrá a `http://192.168.18.11:2785` → se abre el **Dashboard de OpenWA**
2. Andá a **Sessions** y creá una sesión **nueva**
3. En el campo **Session ID** poné el que te mostró `setup.sh` al terminar
4. Poné **Start session** — va a aparecer un **código QR**
5. Abrí WhatsApp en tu celular → **Dispositivos vinculados** → **Vincular un dispositivo**
6. Escaneá el QR. Cuando conecte, el dashboard muestra "Connected"
7. En el mismo dashboard, andá a **Webhooks** y configurá:
   - **URL**: `http://n8n:5678/webhook/whatsapp-inbound`
   - **Evento**: `message.received`
   - **Header X-API-Key**: la API Key que te mostró `setup.sh`
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

# Si cambiaste código y querés rebuildear:
./setup.sh   # vuelve a preguntar datos y buildear
```
