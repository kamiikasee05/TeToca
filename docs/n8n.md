# n8n — Orquestación de Workflows (HISTÓRICO)

> **⚠️ n8n no corre en el stack actual.** Desde la migración a bare metal (21 Jun 2026), la confirmación en tiempo real se maneja inline en el scheduler. Cancelación y reagendado se manejan vía webhooks de OpenWA directo al scheduler. Si se necesitan workflows n8n nuevamente, iniciar como contenedor Docker. Ver [[Sesion-2026-06-21]].

Plataforma de automatización self-hosted.

## Versión

**2.26.3** (actualizado 15 Jun 2026, desde 1.92.0). Base de datos migrada exitosamente.

## Login

- **Email:** godoy97@gmail.com
- **Password:** 150588-reg (restablecido durante la migración)

## Puerto

`5678`

## Workflows activos (5)

| ID | Nombre | Trigger | Estado |
|---|---|---|---|
| `wf1-confirmacion` | WF-1: Confirmación inmediata (polling) | Schedule cada 2 min | ✅ Activo |
| `wf2-recordatorio` | WF-2: Recordatorio 24h antes | Schedule diario 21:00 | ✅ Activo |
| `wf3-cancelacion` | WF-3: Cancelación por WhatsApp | Webhook `/webhook/whatsapp-cancelacion` | ✅ Activo |
| `wf4-reagendado` | WF-4: Reagendado por WhatsApp | Webhook `/webhook/whatsapp-reagendado` | ✅ Activo |
| `wf-realtime-confirmacion` | WF-RT: Confirmación en tiempo real | Webhook `/webhook/appointment-created` | ✅ Activo |

**Nota:** WF-RT recibe eventos `appointment-created` del scheduler en tiempo real. WF-1 es backup vía polling cada 2 min.

## Flujo de datos

```
Landing Page (PHP)
        ↓ (HTTP POST crear-turno.php)
    tuahora-scheduler (API REST)
        ↓ (webhook appointment-created a n8n:5678/webhook/appointment-created)
       n8n (WF-RT + WF-1)
        ↓ (HTTP GET landing:8080/api/whatsapp-send.php)
    Landing PHP relay
        ↓ (HTTP POST openwa:2785/api/sessions/.../send-text)
    OpenWA (WhatsApp)
        ↓
    Cliente
```

Los 4 workflows originales fueron migrados de EasyAppointments (retirado) al scheduler:
- URLs cambiadas de `easyappointments:80` → `scheduler:3000` y `landing:8080`
- Auth cambiada de HTTP Basic Auth a `X-API-Key: {{ $env.SCHEDULER_API_KEY }}`
- Nombres de campos actualizados de snake_case (EA) a camelCase (scheduler)

## Variables de entorno en n8n

| Variable | Origen | Uso |
|---|---|---|
| `N8N_WEBHOOK_TOKEN` | `.env` | Auth para cancel-appointment webhook |
| `N8N_OWNER_PHONE` | `.env` | Teléfono de la dueña (sin usar actualmente) |
| `SCHEDULER_API_KEY` | `.env` | Auth para API del scheduler |

## Historial de cambios

| Fecha | Cambio |
|---|---|
| 15 Jun 2026 | Migración completa EA→Scheduler: URLs, auth, field names. +WF-RT webhook en tiempo real. +SCHEDULER_API_KEY en n8n env |
| 15 Jun 2026 | Actualizado de 1.92.0 → 2.26.3. Login restaurado. WF-4 bug corregido |
| Antes | Versión 1.92.0 con workflows conectados a EasyAppointments |

## Relacionado

- [[README|Volver al inicio]]
- [[TuAhoraScheduler]] — Motor de reservas que alimenta los workflows
- [[Sesion-2026-06-15]] — Sesión de trabajo donde se realizó la migración
