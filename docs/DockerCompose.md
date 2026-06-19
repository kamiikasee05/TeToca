# Docker Compose — Stack Completo

Archivo principal: `E:\TUAHORA\docker-compose.yml`
Override productivo: `E:\TUAHORA\docker-compose.prod.yml`

> **⚠️ EasyAppointments y MySQL retirados (15 Jun 2026).** El stack ya no incluye esos servicios. Ver [[TuAhoraScheduler]] para el nuevo motor de reservas.
> 🛠️ **18 Jun 2026 — Deploy fixes:** agregados env vars de OpenWA al scheduler; landing en `:80` con nginx reverse proxy.

## Servicios

| Servicio | Puerto | Imagen | Propósito |
|---|---|---|---|
| scheduler | 3000 | build | Motor de reservas (Node + SQLite). WhatsApp proxy. |
| landing | 80 | nginx:alpine | Reverse proxy: SPA + `/api/v1`→scheduler + `/admin`→admin PHP |
| landing-admin | 8081 | build | Panel admin (PHP + GD). Branding, services CRUD, turnos. |
| n8n | 5678 | n8nio/n8n:2.26.3 | Orquestación workflows |
| openwa | 2785 | openwa-openwa:latest | Envío/recepción WhatsApp (Puppeteer) |
| redis | internal | redis:7-alpine | Cola de mensajes |
| mailpit | internal | axllent/mailpit | Captura de emails (dev) |

## Red

Red interna Docker: `stack`

## Archivos relacionados

- `E:\TUAHORA\.env` — Variables de entorno
- `E:\TUAHORA\landing\nginx.conf` — Config del reverse proxy
- `E:\TUAHORA\setup.sh` — Setup interactivo (genera `.env` + configs)
- `E:\TUAHORA\deploy.sh` — Deploy productivo

## Relacionado

- [[README|Volver al inicio]]
- [[TuAhoraScheduler]] — Motor de reservas actual
- [[Arquitectura]]
- [[Sesion-2026-06-18]] — Deploy fixes
