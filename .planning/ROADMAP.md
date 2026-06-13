# Roadmap: TuAhora

## Overview

TuAhora is delivered in 5 phases that follow the natural dependency chain of the stack: Foundation first (stack hardening, tunnel access), then the customer-facing booking surface and the backend configuration in parallel, followed by WhatsApp automation (the core differentiator), and finally business artifacts. Phase 1 unlocks everything else — Phase 2 and Phase 3 can run in parallel once the stack is stable. Phase 4 depends on Phase 3 (webhooks must be configured before workflows). Phase 5 is independent and can run anytime.

## Phases

- [ ] **Phase 1: Foundation** - Stack hardening: persistent volumes, timezone, tunnel, bridge consolidation, secrets management
- [ ] **Phase 2: Landing & Booking** - Mobile-first landing page with EA booking iframe, deployed on Vercel
- [ ] **Phase 3: Configuración EA** - Easy!Appointments fully configured for Laura's salon with webhooks
- [ ] **Phase 4: Automatización WhatsApp** - WF-1 confirmation + WF-2 reminder via WhatsApp, warm-up, rate limiting
- [ ] **Phase 5: Artefactos de Negocio** - Contract, owner guide, commercial proposal, health dashboard, contingency plan

## Phase Details

### Phase 1: Foundation
**Goal**: Stack robusto y seguro listo para recibir tráfico real del cliente piloto
**Depends on**: Nothing (first phase)
**Requirements**: FND-01, FND-02, FND-03, FND-04, FND-05, FND-06, FND-07
**Success Criteria** (what must be TRUE):
  1. `docker compose down && docker compose up -d` no pierde datos — todos los volúmenes nombrados persisten correctamente
  2. Todos los contenedores muestran `America/Argentina/La_Rioja` (ART, UTC-3) al ejecutar `date`
  3. El cliente piloto accede al booking page y al panel de administración de EA desde su teléfono vía URL pública (tunnel)
  4. `docker compose ps` no muestra OpenWA; todos los workflows de n8n referencian Baileys (port 3001)
  5. Ningún secreto (API keys, contraseñas, tokens) está hardcodeado en archivos del repositorio
**Plans**: TBD

### Phase 2: Landing & Booking
**Goal**: Clientes del salón pueden descubrir servicios y reservar turnos desde Instagram en mobile
**Depends on**: Phase 1 (tunnel URL para el iframe de EA, testing con cliente real)
**Requirements**: LAND-01, LAND-02, LAND-03, LAND-04, LAND-05, LAND-06, LAND-07
**Success Criteria** (what must be TRUE):
  1. La landing carga en <3 segundos en conexión 3G argentina y pesa <200KB (sin imágenes externas)
  2. Un cliente completa el flujo: link de Instagram → landing → elegir servicio → seleccionar horario → ingresar datos → ver confirmación en EA
  3. La landing se ve correctamente en mobile 375px, desktop 1280px, e Instagram WebView (Android + iOS)
  4. El CTA "Reservar" desde cualquier card de servicio abre el iframe con ese servicio preseleccionado
  5. La landing está publicada y accesible en una URL de Vercel (región gru1 - São Paulo)
**Plans**: TBD
**UI hint**: yes

### Phase 3: Configuración EA
**Goal**: El motor de reservas está completamente configurado para el salón de Laura con webhooks activos
**Depends on**: Phase 1 (stack corriendo para acceder al admin panel de EA)
**Requirements**: CONF-01, CONF-02, CONF-03, CONF-04, CONF-05, CONF-06, CONF-07
**Success Criteria** (what must be TRUE):
  1. El booking page muestra los 6 servicios del salón con nombre, duración y precio en ARS
  2. Al seleccionar horario, solo aparecen slots dentro del horario laboral de Laura (incluyendo breaks diarios)
  3. El sistema rechaza reservas con menos del tiempo mínimo de anticipación y a más de 90 días futuro
  4. Cada vez que se confirma un turno, n8n recibe un webhook `appointment_save` (verificable en n8n execution log)
  5. El formulario de reserva rechaza números de teléfono con formato inválido y tiene CAPTCHA anti-spam activo
**Plans**: TBD
**UI hint**: yes

### Phase 4: Automatización WhatsApp
**Goal**: Los clientes reciben confirmación inmediata y recordatorio 24h antes por WhatsApp, sin riesgo de ban
**Depends on**: Phase 3 (webhooks de EA configurados, servicios y horarios definidos)
**Requirements**: WHAT-01, WHAT-02, WHAT-03, WHAT-04, WHAT-05, WHAT-06, WHAT-07
**Success Criteria** (what must be TRUE):
  1. Al reservar un turno, el cliente recibe un mensaje de WhatsApp en <10 segundos con servicio, fecha, hora y nombre del salón
  2. A las 8:00 AM ART, los clientes con turnos para el día siguiente reciben un recordatorio vía WhatsApp
  3. El sistema envía máximo 10 mensajes/minuto con delay entre envíos; nunca satura el número
  4. El developer recibe una alerta si Baileys se desconecta, la sesión expira, o un mensaje no se entrega
  5. El número de WhatsApp completa 2-4 semanas de warm-up manual sin ser baneado antes de activar automatización
**Plans**: TBD

### Phase 5: Artefactos de Negocio
**Goal**: El desarrollador presenta el servicio profesionalmente y la dueña sabe qué hacer si algo falla
**Depends on**: Nothing (independiente de todas las fases técnicas)
**Requirements**: NEGO-01, NEGO-02, NEGO-03, NEGO-04, NEGO-05
**Success Criteria** (what must be TRUE):
  1. La dueña del salón tiene una guía de 1 página que explica: ver agenda, bloquear días, y qué hacer si WhatsApp no responde (modo manual)
  2. Existe un contrato de servicio profesional (mínimo 3 meses, cláusula de protección de datos) listo para nuevos clientes
  3. La dueña accede a un dashboard simple que muestra estado verde/rojo por componente (EA, n8n, Baileys, MySQL, Redis)
  4. Existe una propuesta comercial en PDF con precios y planes para presentar a prospectos
  5. Hay un plan de contingencia documentado que la dueña puede ejecutar sin ayuda del desarrollador (qué hacer si el bot cae, cómo migrar a nuevo número, mensaje pre-escrito para clientes)
**Plans**: TBD
**UI hint**: yes

## Progress

**Execution Order:**
Phase 1 first (prerequisite). Phase 2 and Phase 3 can run in parallel after Phase 1 completes. Phase 4 must follow Phase 3 (depends on webhooks). Phase 5 is independent — recommended to run in parallel with Phase 3 or Phase 4.

| Phase | Plans Complete | Status | Completed |
|-------|----------------|--------|-----------|
| 1. Foundation | 0/TBD | Not started | - |
| 2. Landing & Booking | 0/TBD | Not started | - |
| 3. Configuración EA | 0/TBD | Not started | - |
| 4. Automatización WhatsApp | 0/TBD | Not started | - |
| 5. Artefactos de Negocio | 0/TBD | Not started | - |
