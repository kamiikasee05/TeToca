# Requirements: TuAhora

**Defined:** 2026-06-13
**Core Value:** Automatizar la reserva, confirmacion y recordatorio de turnos via WhatsApp, eliminando la gestion manual de agenda para la duena del negocio.

## v1 Requirements

Requirements for initial release. Each maps to roadmap phases.

### Foundation

- [ ] **FND-01**: Docker volumes persistentes configurados para MySQL, EA uploads, n8n data y auth de Baileys
- [ ] **FND-02**: Timezone unificado (America/Argentina/La Rioja) en todos los contenedores
- [ ] **FND-03**: MySQL migrado de 8.0 a 8.4 (LTS hasta 2032)
- [ ] **FND-04**: OpenWA removido del stack -- consolidar en Baileys como unico bridge WhatsApp
- [ ] **FND-05**: n8n workflow URLs actualizadas de OpenWA (port 2785) a Baileys (port 3001)
- [ ] **FND-06**: Tunnel de acceso para demo/testing con cliente piloto (ngrok o Cloudflare Tunnel)
- [ ] **FND-07**: Secretos movidos a variables de entorno (no hardcodeados en workflows)

### Landing & Booking Page

- [ ] **LAND-01**: Landing page mobile-first con Hero, Servicios, Como funciona, Galeria, Sobre nosotras, Footer
- [ ] **LAND-02**: Iframe embed del booking page de Easy!Appointments funcional
- [ ] **LAND-03**: Personalizar colores/logo del salon en Easy!Appointments
- [ ] **LAND-04**: Responsive verificado en mobile (375px), desktop (1280px), Instagram WebView
- [ ] **LAND-05**: Deploy en Vercel (region gru1 - Sao Paulo, mejor latencia para Argentina)
- [ ] **LAND-06**: Pagina pesa <200KB sin imagenes externas (vanilla HTML/CSS, sin frameworks)
- [ ] **LAND-07**: CTA "Reservar" desde cards de servicio -> iframe con servicio preseleccionado

### Configuracion Easy!Appointments

- [ ] **CONF-01**: Servicios creados con nombre, duracion y precio ARS (6 servicios del salon)
- [ ] **CONF-02**: Proveedor "Laura" con horarios de atencion reales y breaks
- [ ] **CONF-03**: Reglas de reserva: timeout anticipado, limite futuro 90 dias, telefono obligatorio
- [ ] **CONF-04**: Webhooks configurados: `appointment_save` apuntando a n8n Webhook trigger
- [ ] **CONF-05**: Validacion de formato de telefono argentino (+54 9 XXX XXX-XXXX)
- [ ] **CONF-06**: CAPTCHA en booking page para prevenir spam/bots
- [ ] **CONF-07**: Flujo end-to-end: landing -> servicio -> horario -> confirmar turno

### Automatizacion WhatsApp

- [ ] **WHAT-01**: WF-1 Confirmacion inmediata via webhook (reemplaza polling 2 min)
- [ ] **WHAT-02**: WF-2 Recordatorio 24h -- schedule diario, consulta turnos de manana -> envia WA
- [ ] **WHAT-03**: Periodo de warm-up del numero WhatsApp (2-4 semanas, mensajes manuales)
- [ ] **WHAT-04**: Rate limiting: maximo 10 mensajes/minuto, delay entre mensajes
- [ ] **WHAT-05**: Telefonos normalizados a formato internacional antes de envio WA
- [ ] **WHAT-06**: Verificacion de entrega de mensajes (ack receipt de Baileys)
- [ ] **WHAT-07**: Testing end-to-end con numero de prueba (todos los workflows)

### Artefactos de Negocio

- [ ] **NEGO-01**: Contrato de servicio (3 meses minimo, clausula de proteccion de datos)
- [ ] **NEGO-02**: Guia rapida para la duena (1 pagina: ver agenda, bloquear dias, que hacer si falla)
- [ ] **NEGO-03**: Propuesta comercial PDF con precios y planes
- [ ] **NEGO-04**: Health dashboard basico para la duena (estado del sistema, ultimos turnos)
- [ ] **NEGO-05**: Plan de contingencia (que hace la duena si WhatsApp no responde)

## v2 Requirements

Deferred to future release.

### Infraestructura Productiva

- **INF-01**: Cloudflare Tunnel configurado con dominio propio
- **INF-02**: Migracion de PC dev a VPS o Miniserver con IP fija
- **INF-03**: Backups automaticos de MySQL (local + B2/S3)
- **INF-04**: Monitoreo 24/7 con alertas (ntfy.sh o similar)
- **INF-05**: HTTPS configurado con certificados validos

### Funcionalidades

- **FUNC-01**: WF-3 Cancelacion por keyword "CANCELAR" via WhatsApp
- **FUNC-02**: WF-4 Reagendado por keyword "CAMBIAR" via WhatsApp
- **FUNC-03**: Multiples profesionales por negocio
- **FUNC-04**: Migracion a Baileys v7 (cuando release sea estable)
- **FUNC-05**: Migracion landing a Astro para multi-cliente

## Out of Scope

| Feature | Reason |
|---------|--------|
| Pagos online (MercadoPago) | Argentina opera con efectivo/transferencia. Complejidad desproporcionada para MVP. |
| App movil nativa | Web mobile-first cubre el 100% del funnel. No justifica costo. |
| WhatsApp Business API oficial | Baileys es gratuito. Migrar solo si hay riesgo de ban comprobado. |
| Multi-tenant en misma instancia | Cada cliente tendra su propia instancia EA. Mas simple y seguro. |
| Analytics dashboard avanzado | Health dashboard basico es suficiente para el piloto. |
| Notificaciones por email | Argentina tiene ~95% penetracion WhatsApp. Email seria ignorado. |
| Soporte multi-idioma | Espanol solamente para el mercado objetivo. |

## Traceability

Which phases cover which requirements. Updated during roadmap creation.

| Requirement | Phase | Status |
|-------------|-------|--------|
| FND-01 | Phase 1 | Pending |
| FND-02 | Phase 1 | Pending |
| FND-03 | Phase 1 | Pending |
| FND-04 | Phase 1 | Pending |
| FND-05 | Phase 1 | Pending |
| FND-06 | Phase 1 | Pending |
| FND-07 | Phase 1 | Pending |
| LAND-01 | Phase 2 | Pending |
| LAND-02 | Phase 2 | Pending |
| LAND-03 | Phase 2 | Pending |
| LAND-04 | Phase 2 | Pending |
| LAND-05 | Phase 2 | Pending |
| LAND-06 | Phase 2 | Pending |
| LAND-07 | Phase 2 | Pending |
| CONF-01 | Phase 3 | Pending |
| CONF-02 | Phase 3 | Pending |
| CONF-03 | Phase 3 | Pending |
| CONF-04 | Phase 3 | Pending |
| CONF-05 | Phase 3 | Pending |
| CONF-06 | Phase 3 | Pending |
| CONF-07 | Phase 3 | Pending |
| WHAT-01 | Phase 4 | Pending |
| WHAT-02 | Phase 4 | Pending |
| WHAT-03 | Phase 4 | Pending |
| WHAT-04 | Phase 4 | Pending |
| WHAT-05 | Phase 4 | Pending |
| WHAT-06 | Phase 4 | Pending |
| WHAT-07 | Phase 4 | Pending |
| NEGO-01 | Phase 5 | Pending |
| NEGO-02 | Phase 5 | Pending |
| NEGO-03 | Phase 5 | Pending |
| NEGO-04 | Phase 5 | Pending |
| NEGO-05 | Phase 5 | Pending |

**Coverage:**
- v1 requirements: 33 total
- Mapped to phases: 33
- Unmapped: 0

---
*Requirements defined: 2026-06-13*
*Last updated: 2026-06-13 after research synthesis*
