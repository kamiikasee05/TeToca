# Roadmap

Ver archivo original: [[roadmap-etapas.md]]

## Orden de ejecución

1. **Visual / Landing** — ✅ Completada
2. **Config (servicios y horarios)** — ✅ Completada
3. **WhatsApp automation** — ✅ Completada (7/7 workflows)
4. **Artefactos de negocio** — ✅ Completada
5. **QA & Testing** — 🔨 En progreso (21 Jun 2026)
6. **Infraestructura productiva** — ⏳ Pendiente (solo si hay cliente que paga)

> **21 Jun 2026 (VM deploy + hardening):** Despliegue completo en Ubuntu Server 24.04 (VM). Stack funcional con 4 workflows n8n importados y publicados. Se detectó que el flujo de notificaciones en tiempo real (scheduler → n8n webhook → WhatsApp) es frágil: múltiples puntos de falla (env vars bloqueadas, headers mal configurados, cambios no publicados, formato de body inconsistente). Se agrega Etapa 5.5 QA para robustecer el deploy.
>
> **Decisión arquitectónica:** La confirmación en tiempo real (WF-RT) se moverá del webhook n8n al scheduler directamente para eliminar la cadena de dependencias. Los flujos complejos (cancelación, reagendado, recordatorios) permanecen en n8n.

## Estado por etapa

| Etapa | Estado | Notas |
|---|---|---|
| Etapa 1 — Visual/Landing | ✅ Completada | Landing SPA con booking de 3 pasos, mobile-first, colores desde config.json |
| Etapa 2 — Config | ✅ Completada | Dashboard admin: branding (logo, gallery, colores), services CRUD, gestión de turnos |
| Etapa 3 — WhatsApp | ✅ MVP COMPLETADO | 7/7 workflows diseñados. 4/7 operativos post-deploy VM. |
| Etapa 4 — Negocio | ✅ Completada | Contrato, guías, propuesta comercial redactados |
| Etapa 5 — QA & Testing | 🔨 En progreso | Checklist de validación post-deploy. End-to-end test automatizado. |
| Etapa 6 — Infraestructura productiva | ⏳ Pendiente | Solo si hay cliente que paga. |

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
