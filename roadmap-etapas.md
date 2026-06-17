# Roadmap del proyecto — Prioridad revisada

**Última actualización:** Junio 2026
**Autor:** Ezequiel Godoy

---

## Prioridad de ejecución (orden de ataque)

| Orden | Etapa | Capa | Por qué acá |
|---|---|---|---|
| **1** | Visual / Landing | 4 | Lo primero que ve el cliente. Sin esto no hay venta. |
| **2** | Config Easy!Appointments | 2 | El motor de reservas debe reflejar los servicios reales del salón. |
| **3** | WhatsApp automation | 3 | Diferenciador clave del producto. |
| **4** | Artefactos de negocio | 5 | Contrato, guía, propuesta comercial. |
| **5** | Infraestructura productiva | 1 | Backups, Tunnel, dominio. Solo si hay cliente que paga. |

---

## ETAPA 1 — Visual: Landing + Booking Page

**Objetivo:** Que la landing del salón sea atractiva, mobile-first, y que el embed del booking page de Easy!Appointments funcione correctamente con la misma identidad visual.

### Landing page
- [ ] Hero: foto del salón + "Reservá tu turno en segundos" + botón CTA
- [ ] Cards de servicios con nombre, duración, precio y botón "Reservar"
- [ ] Sección "Cómo funciona" (3 pasos: Elegí → Reservá → Listo)
- [ ] Galería de trabajos (6–9 fotos de la dueña)
- [ ] Sobre nosotras: foto + texto corto
- [ ] Iframe embed del booking page de Easy!Appointments
- [ ] Footer: WhatsApp directo + Instagram + dirección + mapa
- [ ] Responsive mobile verificado
- [ ] Deploy en Vercel

### Booking page (Easy!Appointments)
- [ ] Personalizar colores y logo del salón en Easy!Appointments
- [ ] Verificar que el iframe tenga consistencia visual con la landing

---

## ETAPA 2 — Configuración Easy!Appointments

**Objetivo:** El motor de reservas reflecta los servicios, horarios y reglas de negocio del salón de uñas.

### Servicios
- [ ] Manicura simple — 45 min — $8.000
- [ ] Manicura semipermanente — 60 min — $12.000
- [ ] Pedicura simple — 60 min — $10.000
- [ ] Kapping — 90 min — $18.000
- [ ] Nail Art (diseño) — 30 min — $5.000
- [ ] Combo mani+pedi — 90 min — $16.000

### Proveedor
- [ ] Crear proveedor (Laura) con nombre, teléfono, email
- [ ] Configurar horarios de atención reales
- [ ] Configurar breaks

### Reglas de reserva
- [ ] Timeout de reserva anticipada
- [ ] Límite futuro (ej: 90 días)
- [ ] Teléfono como campo obligatorio
- [ ] Notificaciones email habilitadas

### Verificación
- [ ] Flujo end-to-end: landing → seleccionar servicio → elegir horario → confirmar turno
- [ ] Que al hacer click en "Reservar" desde una card de servicio en la landing, llegue al booking page con ese servicio preseleccionado

---

## ETAPA 3 — Automatización WhatsApp

**Objetivo:** Confirmación inmediata, recordatorio 24h, cancelación y reagendado por WhatsApp.

### Baileys
- [ ] Escanear QR de WhatsApp
- [ ] Verificar endpoint `/send-text` funcional
- [ ] Verificar webhook de mensajes entrantes

### n8n Workflows
- [ ] WF-1: Confirmación inmediata (polling cada 2 min)
- [ ] WF-2: Recordatorio 24h (schedule cada 1h)
- [ ] WF-3: Cancelación por keyword "CANCELAR"
- [ ] WF-4: Reagendado por keyword "CAMBIAR"
- [ ] Testing end-to-end con número de prueba

---

## ETAPA 4 — Artefactos de negocio

**Objetivo:** Tener todo listo para cobrarle al primer cliente.

- [ ] Contrato de servicio (3 meses mínimo, cláusula de datos)
- [ ] Guía rápida para la dueña (1 página: cómo ver la agenda, cómo bloquear días)
- [ ] Propuesta comercial PDF
- [ ] Definir flujo de cobro (transferencia / MP)

---

## ETAPA 5 — Infraestructura productiva

**Objetivo:** El sistema corre 24/7, con respaldo y sin depender de la PC de desarrollo. Solo se ejecuta cuando hay un cliente que paga.

- [ ] Cloudflare Tunnel configurado
- [ ] Dominio propio
- [ ] HTTPS funcionando
- [ ] Backups automáticos de MySQL (local + B2)
- [ ] Monitoreo básico (health check + alerta)
- [ ] Migrar del PC dev a VPS o Miniserver con IP fija
- [ ] Documentar procedimiento de restore

---

## Stack técnico

```
Servicio        Puerto    Imagen                              Propósito
──────────────  ────────  ─────────────────────────────────  ─────────────────────────
easyappointments  8080    alextselegidis/easyappointments     Motor de reservas
mysql             3306    mysql:8.0                           Base de datos
n8n               5678    n8nio/n8n                           Orquestación workflows
baileys           3001    (custom build)                      Envío WhatsApp
redis             6379    redis:7-alpine                      Cola de mensajes
mailpit         1025/8025 axllent/mailpit                     Captura de emails (dev)
```

## Resumen económico

| Concepto | Monto |
|---|---|
| Setup único (por cliente) | $40.000–$60.000 ARS |
| Plan Base (1 profesional) | $15.000 ARS/mes |
| Plan Pro (hasta 3 profesionales) | $25.000 ARS/mes |
| Plan Clínica (hasta 8 prof.) | $40.000 ARS/mes |
| Costo infraestructura/mes | ~$8.500 ARS |

---

## Criterios de éxito — Cliente piloto

- [ ] Al menos 10 turnos reservados online en las primeras 2 semanas
- [ ] 0 turnos perdidos por error del sistema
- [ ] Tasa de no-show reducida vs. antes
- [ ] La dueña opera el sistema sin asistencia técnica
- [ ] Tiempo de respuesta de la landing < 2 segundos en mobile
