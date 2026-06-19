# TeToca — Documentación del Proyecto

Sistema de turnos online para pequeños negocios en ciudades intermedias de Argentina.

## Stack

| Componente | Tecnología | Nota |
|---|---|---|
| Motor de reservas | [[TuAhoraScheduler]] (Node + SQLite) | ✅ Reemplazó EasyAppointments + MySQL |
| WhatsApp | [[OpenWA]] (Node.js) | Bot, puerto 2785 |
| Orquestación | [[n8n]] | v2.26.3, workflows, puerto 5678 |
| Landing | Nginx + vanilla JS SPA | Puerto :80, reverse proxy → scheduler + admin |
| Admin panel | PHP | Puerto :8081, separado de landing |
| Cola de mensajes | Redis 7 | En Docker |
| Email (dev) | Mailpit | En Docker |

## Workflows n8n

- [[WF1-Confirmacion]] — ✅ Confirmación inmediata (webhook desde scheduler)
- [[WF2-Recordatorio]] — ✅ Recordatorio 24h antes (cron 21:00 ART)
- [[WF3-Cancelacion]] — ✅ Cancelación por WhatsApp (end-to-end)
- [[WF4-Reagendado]] — ✅ Reagendado por WhatsApp (end-to-end)

## Infraestructura

- [[DockerCompose]] — Stack completo
- [[CheckStack]] — Script de verificación
- [[CloudflareTunnel]] — Tunnel productivo
- [[CancelRelay]] — Endpoint PHP para cancelación de turnos

## Negocio

- [[ContextoProyecto]] — Contexto y modelo de negocio
- [[Roadmap]] — Roadmap priorizado
- [[EstadoProyecto]] — Estado actual
- [[ContratoServicio]] — Contrato de servicio
- [[PropuestaComercial]] — Propuesta comercial
- [[GuiaDuena]] — Guía rápida para la dueña
- [[GuiaConfiguracion]] — Guía de configuración

## Seguridad

- [[SecurityAuditor]] — Skill de auditoría de seguridad
- [[SecurityAudit-Report]] — Último reporte generado
- [[SecurityAudit-Plan]] — Plan de auditoría post-cambios
- [[PropuestaSeguridad]] — Propuesta de mejoras priorizada

## Sesiones

- [[Sesion-2026-06-18]] — Deploy fixes: WSL2, CRLF, nginx routing, env vars, admin
- [[Sesion-2026-06-16]] — Landing PHP→Nginx static, WhatsApp proxy, OpenWA session recovery
- [[Sesion-2026-06-15]] — n8n upgrade, EA+MySQL retirados, migración completada
- [[Sesion-2026-06-14]] — WF3/WF4 debugging end-to-end, PHP cancel relay
- [[Sesion-2026-06-13]] — Landing, dashboard unificado, estabilización WFs
- [[Sesion-2026-06-12]] — Setup inicial, Obsidian vault

## Desarrollo

- [[OpenCodeBrief]] — Brief técnico para OpenCode
- [[TuAhoraScheduler]] — ✅ Implementado. Microservicio Node + SQLite que reemplazó EasyAppointments + MySQL
