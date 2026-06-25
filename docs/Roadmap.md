# Roadmap

Ver archivo original: [[roadmap-etapas.md]]

## Orden de ejecución

1. **Visual / Landing** — ✅ Completada
2. **Config (servicios y horarios)** — ✅ Completada
3. **WhatsApp automation** — ✅ Completada (7/7 workflows)
4. **Artefactos de negocio** — ✅ Completada
5. **QA & Testing** — 🔨 En progreso (21 Jun 2026)
6. **Infraestructura productiva** — ⏳ Pendiente (solo si hay cliente que paga)

> **21 Jun 2026 (Bare Metal Migration):** Stack migrado de Docker Compose a bare metal (Ubuntu Server 24.04). n8n reemplazado por inline WhatsApp en scheduler. Docker eliminado para todo excepto OpenWA. Ver [[Sesion-2026-06-21]].
>
> **Decisión arquitectónica:** La confirmación en tiempo real (WF-RT) se movió del webhook n8n al scheduler directamente (inline). Cancelación, reagendado y notificaciones se manejan vía webhooks de OpenWA directo al scheduler. n8n ya no corre.

## Estado por etapa

| Etapa | Estado | Notas |
|---|---|---|---|
| Etapa 1 — Visual/Landing | ✅ Completada | Landing SPA con booking de 3 pasos, mobile-first, colores desde config.json |
| Etapa 2 — Config | ✅ Completada | Dashboard admin: branding (logo, gallery, colores), services CRUD, gestión de turnos |
| Etapa 3 — WhatsApp | ✅ COMPLETADA | Inline en scheduler. Cancelación, reagendado y notificaciones vía webhooks OpenWA |
| Etapa 4 — Negocio | ✅ Completada | Contrato, guías, propuesta comercial redactados |
| Etapa 5 — QA & Testing | ✅ Completada | E2E verificado en bare metal (creación → cancelación vía WhatsApp) |
| Etapa 6 — Infraestructura productiva | ⏳ Pendiente | Solo si hay cliente que paga. |

## Workflows — Estado (post bare metal)

> **Nota:** n8n no corre. La funcionalidad se migró a inline en scheduler + webhooks OpenWA directos.

| Workflow | Tipo | Estado | Nota |
|---|---|---|---|
| WF-RT | Confirmación inline | ✅ | En scheduler (`appointments.js:120-153`) |
| WF-1 | Confirmación 24h (cron) | ⏳ Pendiente | Migrar a cron del sistema |
| WF-2 | Recordatorio 21:00 ART | ⏳ Pendiente | Migrar a cron del sistema |
| WF-3 | Cancelación inbound | ✅ | Webhook OpenWA → scheduler |
| WF-4 | Reagendado inbound | ✅ | Webhook OpenWA → scheduler |
| WF-5 | Notif. cancelación | ✅ | Webhook OpenWA → scheduler |
| WF-6 | Notif. reagendado | ✅ | Webhook OpenWA → scheduler |

## Logros finales (16 Jun 2026)

### WF-3 v3 — Cancelación inbound
- Reconstruido 3 veces. Versión final: Code-based router.
- **Breakthrough**: WhatsApp Web vinculado usa LID (`300815528157@lid`), NO número de teléfono. Buscar cliente por `from` era imposible.
- **Solución v3**: detecta "CANCELAR" → busca TODOS los appointments confirmados → Code node cuenta:
  - 1 turno → cancela directo + notifica
  - 2+ turnos → envía lista numerada para elegir
  - 0 turnos → "No tenés turnos activos"
- Sin hash, sin teléfono. Robusto.

### Scheduler API
- SQLite database (EA+MySQL retirados 15 Jun)
- WhatsApp proxy: `GET/POST /api/v1/whatsapp/send` → OpenWA
- Webhooks: `appointment-created`, `appointment-cancelled`, `appointment-rescheduled`
- `getFullAppointment()`: payload completo con JOIN
- Query params: `hash`, `customer_id`, `status` en GET /appointments
- `address` en provider_settings y webhook payload

### Dashboard
- GD library (PNG + JPEG), logo upload, gallery, branding sync
- Services CRUD, appointments management
- Credentials: `admin` / `admin2024`

### Seguridad
- Auditoría completa (18 Jun): 🟢 HARDENED FOR PRODUCTION — 11 críticos resueltos
- Ver [[SecurityAudit-Report]]

## Próximo

- **`days_off`**: feature para bloquear fechas específicas (feriados, vacaciones)
- **Migrar WF-1 / WF-2**: recordatorios y confirmaciones 24h desde cron del sistema (reemplazar n8n cron)
- Etapa 6 — Infraestructura productiva (solo si hay cliente que paga)

## Relacionado

- [[README|Volver al inicio]]
- [[EstadoProyecto]]
- [[Arquitectura]]
- [[SecurityAudit-Report]]
- [[Sesion-2026-06-21]]
