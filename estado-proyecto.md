# Estado del Proyecto — Sistema de Turnos Online

**Última actualización:** Junio 2026
**Fase actual:** 🟡 Etapa 1 — Visual (Landing + Booking Page)
**Próximo hito:** Deploy de landing atractiva con iframe funcional

---

## Estado general

```
Infraestructura base   ████████████████  100%  (Docker compose funcional, iframe arreglado)
Etapa 1 — Visual       ██░░░░░░░░░░░░░░   10%  (HTML base existe, falta diseño y deploy)
Etapa 2 — Config EA    ░░░░░░░░░░░░░░░░    0%
Etapa 3 — WhatsApp     ░░░░░░░░░░░░░░░░    0%
Etapa 4 — Negocio      ░░░░░░░░░░░░░░░░    0%
Etapa 5 — Infra. prod  ░░░░░░░░░░░░░░░░    0%
Primer cliente         ░░░░░░░░░░░░░░░░    0%
```

---

## Progreso por etapa

### Infraestructura base ✅ COMPLETA
- [x] Docker Compose con Easy!Appointments + MySQL + n8n + Redis + Mailpit + Baileys
- [x] Stack verificado: 6 contenedores UP, todos los health checks pasan
- [x] Instalación de Easy!Appointments completada (wizard)
- [x] `X-Frame-Options` removido del contenedor para permitir iframe embed
- [x] Landing HTML base creada

### Etapa 1 — Visual 🟡 EN PROGRESO
- [ ] Diseñar landing con paleta del salón (rosa-nude + blanco + dorado)
- [ ] Hero con foto + CTA
- [ ] Cards de servicios con precio/duración + botón "Reservar"
- [ ] Sección "Cómo funciona" (3 pasos)
- [ ] Galería de trabajos
- [ ] Sobre nosotras
- [ ] Iframe embed del booking page
- [ ] Footer con WhatsApp + Instagram + dirección
- [ ] Responsive mobile verificado
- [ ] Personalizar colores/logo en Easy!Appointments
- [ ] Deploy en Vercel

### Etapa 2 — Config Easy!Appointments 🔴 PENDIENTE
- [ ] Crear servicios del salón (6 servicios con precio y duración)
- [ ] Configurar proveedor Laura con horarios reales
- [ ] Configurar reglas de reserva (timeout, límite futuro, teléfono obligatorio)
- [ ] Verificar flujo end-to-end

### Etapa 3 — WhatsApp 🔴 PENDIENTE
- [ ] Escanear QR de Baileys
- [ ] Importar y testear WF-1 (confirmación)
- [ ] Importar y testear WF-2 (recordatorio)
- [ ] Importar y testear WF-3 (cancelación)
- [ ] Importar y testear WF-4 (reagendado)

### Etapa 4 — Artefactos de negocio 🔴 PENDIENTE
- [ ] Contrato de servicio (3 meses mínimo)
- [ ] Guía rápida para la dueña
- [ ] Propuesta comercial PDF

### Etapa 5 — Infraestructura productiva 🔴 PENDIENTE (post-cliente)
- [ ] Cloudflare Tunnel + dominio + HTTPS
- [ ] Backups automáticos MySQL
- [ ] Monitoreo básico
- [ ] Migrar a VPS o Miniserver con IP fija

---

## Decisiones tomadas

| Fecha | Decisión | Justificación |
|---|---|---|
| Jun 2026 | Easy!Appointments como motor de reservas | Open source, multi-servicio/profesional, API REST, ideal para belleza y salud |
| Jun 2026 | Baileys para WhatsApp | Stack ya operativo en Chami 3D |
| Jun 2026 | Salón de uñas como piloto | Alto volumen de turnos, problema real de no-shows |
| Jun 2026 | Modelo agente local | Validar mercado antes de invertir en plataforma propia |
| Jun 2026 | Orden de ejecución: Visual → EA → WA → Negocio → Infra | Lo que genera ventas primero, infraestructura solo si hay cliente |

---

## Archivos del proyecto

| Ruta | Descripción |
|---|---|
| `easyappointments/docker-compose.yml` | Stack completo (6 servicios) |
| `easyappointments/Dockerfile` | Custom EA image (sin X-Frame-Options) |
| `easyappointments/.env` | Variables de entorno |
| `landing-salon/index.html` | Landing page base |
| `n8n-workflows/WF1-confirmacion.json` | Workflow confirmación |
| `n8n-workflows/WF2-recordatorio.json` | Workflow recordatorio |
| `n8n-workflows/WF3-cancelacion.json` | Workflow cancelación |
| `n8n-workflows/WF4-reagendado.json` | Workflow reagendado |
| `scripts/check-stack.ps1` | Script de verificación |
| `contexto.md` | Contexto del proyecto |
| `roadmap-etapas.md` | Roadmap priorizado |
| `GUIA-CONFIGURACION.md` | Guía de configuración |
