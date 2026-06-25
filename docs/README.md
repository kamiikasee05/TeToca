# TeToca — Documentación del Proyecto

Sistema de turnos online para pequeños negocios en ciudades intermedias de Argentina.

## Stack actual (Bare Metal)

| Componente | Tecnología | Gestión | Nota |
|---|---|---|---|
| Landing | Nginx + vanilla JS SPA | systemd | Puerto :80, reverse proxy → scheduler + admin |
| Admin panel | PHP-FPM 8.5 | systemd | Vía nginx reverse proxy, GD library |
| Motor de reservas | [[TuAhoraScheduler]] (Node + SQLite) | pm2 (fork) | Puerto :3000, inline WhatsApp |
| WhatsApp | [[OpenWA]] (Node.js + Puppeteer) | Docker (único) | Puerto :2785, único servicio en Docker |
| Cola de mensajes | Redis 7 | systemd | Interno |
| n8n | — | No corre | Reemplazado por inline WhatsApp en scheduler |

## Features

- **Reserva online 3 pasos** desde landing pública (servicio → fecha/hora → datos)
- **Confirmación automática por WhatsApp** inline en scheduler
- **Cancelación por WhatsApp** — usuario escribe "CANCELAR"
- **Reagendado por WhatsApp** — usuario escribe "CAMBIAR"
- **Recordatorios** — pendiente migrar a cron del sistema
- **Dashboard admin** — gestión de servicios, horarios, turnos, marca, logo, galería
- **`days_off`** — ⏳ Planificado (bloquear fechas específicas)

## Infraestructura

- [[DockerCompose]] — Stack Docker histórico (solo OpenWA usa Docker actualmente)
- [[CloudflareTunnel]] — Tunnel productivo
- [[CancelRelay]] — Endpoint PHP para cancelación de turnos

## Negocio

- [[ContextoProyecto]] — Contexto y modelo de negocio
- [[Roadmap]] — Roadmap priorizado
- [[EstadoProyecto]] — Estado actual
- [[ContratoServicio]] — Contrato de servicio
- [[PropuestaComercial]] — Propuesta comercial
- [[GuiaDuena]] — Guía rápida para la dueña
- [[Guia-de-Uso]] — Guía de uso del panel de administración
- [[GuiaConfiguracion]] — Guía de configuración técnica

## Seguridad

- [[SecurityAuditor]] — Skill de auditoría de seguridad
- [[SecurityAudit-Report]] — Último reporte generado
- [[SecurityAudit-Plan]] — Plan de auditoría post-cambios
- [[PropuestaSeguridad]] — Propuesta de mejoras priorizada
- [[SecurityAudit-n8n-OpenWA]] — Auditoría específica n8n/OpenWA

## Sesiones

- [[Sesion-2026-06-21]] — Migración a bare metal: Ubuntu Server, pm2, Docker solo OpenWA
- [[Sesion-2026-06-18]] — Deploy fixes (WSL2, CRLF, nginx routing, admin) + Security hardening (11 críticos resueltos, commits `bdcae27`, `4afd018`, `edb1660`)
- [[Sesion-2026-06-15]] — n8n upgrade, EA+MySQL retirados, migración completada
- [[Sesion-2026-06-14]] — WF3/WF4 debugging end-to-end, PHP cancel relay
- [[Sesion-2026-06-13]] — Landing, dashboard unificado, estabilización WFs
- [[Sesion-2026-06-12]] — Setup inicial, Obsidian vault

## Desarrollo

- [[OpenCodeBrief]] — Brief técnico para OpenCode
- [[TuAhoraScheduler]] — ✅ Implementado. Microservicio Node + SQLite
- [[CheckStack]] — Script de verificación
- [[Monitoreo]] — Monitoreo y logging

## Relacionado

- [[Arquitectura]] — Diagrama de arquitectura actual
- [[LandingSalon]] — Documentación del landing
