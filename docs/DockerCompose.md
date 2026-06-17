# Docker Compose — Stack Completo

Archivo: `E:\TUAHORA\easyappointments\docker-compose.yml`

> **⚠️ EasyAppointments y MySQL retirados (15 Jun 2026).** El stack ya no incluye esos servicios. Ver [[TuAhoraScheduler]] para el nuevo motor de reservas.

## Servicios

| Servicio | Puerto | Imagen | Propósito |
|---|---|---|---|
| scheduler | — (interno) | easyappointments-scheduler | Motor de reservas (Node + SQLite) |
| n8n | 5678 | n8nio/n8n:2.26.3 | Orquestación workflows |
| openwa | 2785 | openwa-openwa:latest | Envío WhatsApp |
| redis | 6379 | redis:7-alpine | Cola de mensajes |
| mailpit | — | axllent/mailpit | Captura de emails (dev) |

## Red

Red interna Docker: `stack`

## Archivos relacionados

- `E:\TUAHORA\easyappointments\.env` — Variables de entorno (limpiado, sin vars de EA/MySQL)

## Relacionado

- [[README|Volver al inicio]]
- [[TuAhoraScheduler]] — Motor de reservas actual
