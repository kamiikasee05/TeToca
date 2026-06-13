# TuAhora

## What This Is

Sistema de turnos online para pequenos negocios en ciudades intermedias de Argentina (Chamical, Chilecito, La Rioja). Modelo de negocio agente local: el desarrollador configura y mantiene el sistema para cada cliente, cobrando un fee mensual. Cliente piloto: salon de unas (1 profesional, multiples servicios).

## Core Value

Automatizar la reserva, confirmacion y recordatorio de turnos via WhatsApp, eliminando la gestion manual de agenda para la duena del negocio.

## Requirements

### Validated

- V-01: Stack Docker con EasyAppointments + MySQL + n8n + Baileys + Redis funcionando -- fase 0
- V-02: Baileys WhatsApp Service con endpoints /health, /qr, /send-text, /send-reminder -- fase 0
- V-03: docker-compose.yml con todos los servicios interconectados -- fase 0
- V-04: Script check-stack.ps1 verificando salud del stack -- fase 0
- V-05: Documentacion del proyecto en Obsidian vault (docs/) -- fase 0
- V-06: Codebase map generado (.planning/codebase/) -- fase 0

### Active

#### Etapa 1 -- Visual: Landing + Booking Page
- [ ] LAND-01: Landing page mobile-first con Hero, Servicios, Como funciona, Galeria, Sobre nosotras, Footer
- [ ] LAND-02: Iframe embed del booking page de Easy!Appointments funcional
- [ ] LAND-03: Personalizar colores/logo del salon en Easy!Appointments
- [ ] LAND-04: Responsive verificado (375px mobile, 1280px desktop)
- [ ] LAND-05: Deploy en Vercel
- [ ] LAND-06: Pagina pesa <200KB sin imagenes externas

#### Etapa 2 -- Configuracion Easy!Appointments
- [ ] CONF-01: Crear servicios del salon (manicura, pedicura, kapping, nail art, combos) con duracion y precio
- [ ] CONF-02: Crear proveedor (Laura) con horarios de atencion y breaks
- [ ] CONF-03: Configurar reglas de reserva (timeout, limite futuro, telefono obligatorio)
- [ ] CONF-04: Flujo end-to-end: landing -> servicio -> horario -> confirmar

#### Etapa 3 -- Automatizacion WhatsApp
- [ ] WHAT-01: WF-1 Confirmacion inmediata -- n8n polling cada 2 min detecta turno nuevo -> envia WA
- [ ] WHAT-02: WF-2 Recordatorio 24h -- schedule diario consulta turnos de manana -> envia WA
- [ ] WHAT-03: WF-3 Cancelacion -- keyword "CANCELAR" por WA -> cancela en EA -> confirma
- [ ] WHAT-04: WF-4 Reagendado -- keyword "CAMBIAR" por WA -> cancela turno actual -> envia link booking
- [ ] WHAT-05: Webhook de mensajes entrantes de Baileys -> n8n funcional
- [ ] WHAT-06: Testing end-to-end con numero de prueba

#### Etapa 4 -- Artefactos de Negocio (paralelo)
- [ ] NEGO-01: Contrato de servicio (3 meses minimo, clausula de datos)
- [ ] NEGO-02: Guia rapida para la duena (1 pagina)
- [ ] NEGO-03: Propuesta comercial PDF
- [ ] NEGO-04: Definir flujo de cobro (transferencia / MP)

### Out of Scope

- Infraestructura productiva (Cloudflare Tunnel, VPS, backups automaticos) -- v2, solo si hay cliente que paga
- Dominio propio -- v2
- Multiples profesionales -- v2
- App movil nativa -- no planeado

## Context

**Modelo de negocio:** Agente local. El desarrollador configura el sistema para cada cliente (salon, consultorio, barberia) y cobra fee mensual. El cliente no toca configuracion tecnica.

**Cliente piloto:** Salon de unas en Chamical, La Rioja. 1 profesional (Laura), multiples servicios.

**Stack existente:** Easy!Appointments (PHP/MySQL, puerto 8080), n8n (puerto 5678), Baileys WhatsApp bot (Node.js, puerto 3001), Redis 7 (puerto 6379), Mailpit (dev email, puerto 8025). Todo en Docker Compose.

**Limitaciones conocidas:** Easy!Appointments no tiene webhooks nativos -> WF-1 usa polling cada 2 min. Solucion productiva: agregar callback PHP que dispare webhook.

**Documentacion:** Obsidian vault en docs/ con notas interconectadas. Roadmap original en roadmap-etapas.md. Brief tecnico en OPENCODE-BRIEF.md.

## Constraints

- **Stack:** Easy!Appointments (PHP/MySQL) como motor de reservas -- open source, API REST, ya funcionando
- **Plataforma:** Docker Compose para desarrollo local, Vercel para landing
- **Idioma:** Todo en espanol (interfaz, mensajes WA, documentacion)
- **Moneda:** Pesos argentinos (ARS)
- **Seguridad:** Cobertura integral en todas las etapas -- HTTPS, API auth, variables de entorno, no hardcodear credenciales
- **Budget:** Infraestructura ~$8.500 ARS/mes cuando pase a produccion
- **Mobile-first:** Landing debe funcionar perfecto en mobile (Instagram -> landing es el funnel principal)

## Key Decisions

| Decision | Rationale | Outcome |
|----------|-----------|---------|
| Easy!Appointments sobre Cal.com | Ya funcionando, mas liviano, API REST suficiente | -- Pending |
| n8n sobre custom scripts | Workflows visuales, mas facil de mantener y modificar | -- Pending |
| Baileys sobre WhatsApp Business API | Open source, sin costo, ideal para MVP | -- Pending |
| Landing HTML estatico (Vercel) | Maximo rendimiento, deploy gratis, sin backend | -- Pending |
| Infraestructura postergada a v2 | Solo tiene sentido si hay cliente que paga | -- Pending |
| Seguridad como preocupacion transversal | Datos de clientes (telefonos, agenda) requieren proteccion desde el inicio | -- Pending |

## Evolution

This document evolves at phase transitions and milestone boundaries.

**After each phase transition:**
1. Requirements invalidated? -> Move to Out of Scope with reason
2. Requirements validated? -> Move to Validated with phase reference
3. New requirements emerged? -> Add to Active
4. Decisions to log? -> Add to Key Decisions
5. "What This Is" still accurate? -> Update if drifted

**After each milestone:**
1. Full review of all sections
2. Core Value check -- still the right priority?
3. Audit Out of Scope -- reasons still valid?
4. Update Context with current state

---
*Last updated: 2026-06-13 after initialization*
