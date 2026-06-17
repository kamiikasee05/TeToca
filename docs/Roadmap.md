# Roadmap

Ver archivo original: [[roadmap-etapas.md]]

## Orden de ejecución

1. **Visual / Landing** — ✅ Completada
2. **Config (servicios y horarios)** — ✅ Completada
3. **WhatsApp automation** — ✅ Completada (7/7 workflows)
4. **Artefactos de negocio** — ✅ Completada
5. **Infraestructura productiva** — ⏳ Pendiente (solo si hay cliente que paga)

> **16 Jun 2026 (sesión de cierre):** MVP 100% completado. Los 7 workflows n8n son funcionales end-to-end. Dashboard admin operativo con branding, services CRUD, y gestión de turnos. Stack final: Nginx :8080, PHP+GD :8081, Scheduler :3000, OpenWA :2785, n8n :5678. Auditoría de seguridad completada (🟡 MEDIUM RISK, 3 críticos pendientes para Etapa 5). WF-3 reconstruido como v3 con Code-based router tras el breakthrough del LID de WhatsApp Web.

## Estado por etapa

| Etapa | Estado | Notas |
|---|---|---|
| Etapa 1 — Visual/Landing | ✅ Completada | Landing SPA con booking de 3 pasos, mobile-first, colores desde config.json |
| Etapa 2 — Config | ✅ Completada | Dashboard admin: branding (logo, gallery, colores), services CRUD, gestión de turnos |
| Etapa 3 — WhatsApp | ✅ MVP COMPLETADO | 7/7 workflows: WF-RT, WF-1, WF-2, WF-3 v3, WF-4, WF-5, WF-6 todos funcionales end-to-end |
| Etapa 4 — Negocio | ✅ Completada | Contrato, guías, propuesta comercial redactados |
| Etapa 5 — Infraestructura productiva | ⏳ Pendiente | Solo si hay cliente que paga. Requiere resolver 3 críticos de auditoría. |

## 7 Workflows n8n — Estado final

| Workflow | Tipo | Descripción | Estado |
|---|---|---|---|
| WF-RT | Outbound | Confirmación en tiempo real vía webhook `appointment-created` | ✅ |
| WF-1 | Outbound | Confirmación 24h antes (cron) | ✅ |
| WF-2 | Outbound | Recordatorio diario 21:00 ART (cron) | ✅ Verificado |
| WF-3 | Inbound | "CANCELAR" vía WhatsApp → cancela turno | ✅ v3 |
| WF-4 | Inbound | "CAMBIAR/REAGENDAR" vía WhatsApp → cancela + link reagendado | ✅ |
| WF-5 | Outbound | Notificación cancelación vía webhook `appointment-cancelled` | ✅ |
| WF-6 | Outbound | Notificación reagendado vía webhook `appointment-rescheduled` | ✅ |

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
- Auditoría completa: 🟡 MEDIUM RISK — 3 críticos, 4 sospechosos, 4 observaciones
- Ver [[SecurityAudit-Report]]

## Próximo

- Etapa 5 — Infraestructura productiva (solo si hay cliente que paga)
- Resolver 3 críticos de auditoría antes del deploy

## Relacionado

- [[README|Volver al inicio]]
- [[EstadoProyecto]]
- [[Arquitectura]]
- [[SecurityAudit-Report]]
