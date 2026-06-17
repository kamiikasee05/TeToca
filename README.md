# TeToca — Sistema de Turnos Online

Sistema de turnos online para pequeños negocios. Documentación completa en `docs/`.

## Stack

- **Scheduler API** — Motor de reservas (Node.js + SQLite)
- **n8n** — Workflows de automatización (confirmación, recordatorios, cancelación)
- **OpenWA** — Bot de WhatsApp (Puppeteer/WhatsApp Web)
- **Landing SPA** — Página pública de reservas (Nginx + vanilla JS)
- **Admin Panel** — Dashboard de administración (PHP)
- **Redis / Mailpit** — Soporte

## Despliegue rápido

```bash
cp .env.example .env   # editar con valores reales
docker compose up -d
```

## Documentación

Documentación completa en `docs/` (formato Obsidian).
