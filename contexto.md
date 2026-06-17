# Contexto del Proyecto — TuAhora

## Identidad

**Nombre tentativo:** TuAhora (a definir)
**Modelo:** SaaS local para pequeños negocios en ciudades intermedias de Argentina
**Operador:** Ezequiel Godoy — Chamical, La Rioja
**Inspiración:** [Ágora](https://agora.red/)

---

## Propósito

Dar a negocios locales (salones, consultorios, odontólogos, etc.) en ciudades intermedias del NOA una herramienta de **turnos online 24/7 con recordatorios por WhatsApp automáticos**, sin que el negocio necesite conocimientos técnicos.

---

## Stack tecnológico

| Componente | Herramienta |
|---|---|
| Motor de reservas | Easy!Appointments (self-hosted, Docker) |
| Base de datos | MySQL 8.0 |
| WhatsApp | Baileys (Node.js) |
| Orquestación | n8n self-hosted |
| Landing | HTML estático en Vercel |
| Cola de mensajes | Redis 7 |
| Email (dev) | Mailpit |

---

## Orden de ejecución

1. **Visual** — Landing atractiva + booking page con marca del salón
2. **Config Easy!Appointments** — Servicios, horarios, reglas
3. **WhatsApp automation** — Workflows n8n, Baileys, testing
4. **Artefactos de negocio** — Contrato, guía, propuesta
5. **Infraestructura productiva** — Tunnel, backups, monitoreo (solo post-cliente)

La infraestructura productiva (backups, dominio, HTTPS) se hace **después** de tener el primer cliente, no antes. Mientras tanto el stack corre en PC de desarrollo.

---

## Modelo de negocio

| Concepto                             | Monto               |
| ------------------------------------ | ------------------- |
| Setup único                          | $40.000–$60.000 ARS |
| Plan Base (1 profesional)            | $15.000 ARS/mes     |
| Plan Pro (hasta 3 profesionales)     | $25.000 ARS/mes     |
| Plan Clínica (hasta 8 profesionales) | $40.000 ARS/mes     |

---

## Cliente piloto

**Salón de uñas** en Chamical/Chilecito — 1 profesional (Laura).
Problema: no-shows, agenda manual por WhatsApp, sin presencia digital.
Flujo esperado: Instagram → link en bio → landing → reserva → confirmación WA → recordatorio 24h.

---

## Riesgos

| Riesgo | Mitigación |
|---|---|
| WhatsApp baneado (Baileys) | Número secundario, limitar volumen, migrar a Twilio si escala |
| Easy!Appointments muy simple para clínicas | Suficiente para MVP, migrar a plataforma propia si hace falta |
| Cliente no renueva | Contrato 3 meses mínimo, setup fee disuade abandono |
| PC dev no es producción | Infraestructura real solo cuando haya cliente pagando |

---

## Autor

**Ezequiel Godoy** — Chami 3D
Chamical, La Rioja, Argentina
Stack: Node.js, Python, Docker, n8n, WhatsApp automations, Notion
