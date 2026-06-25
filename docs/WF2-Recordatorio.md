# WF-2: Recordatorio 24 Horas Antes — HISTÓRICO

> ⚠️ n8n no corre en el stack actual (bare metal). WF-2 (recordatorio diario) está pendiente de migrar a cron del sistema.

**Estado:** ⏳ Pendiente migrar a cron del sistema

**Trigger:** Schedule — cron `0 21 * * *` (18:00 ART)

## Lógica

1. Consultar Easy!Appointments API: turnos con fecha de mañana
2. IF node para filtrar solo turnos con datos (evita ejecuciones vacías)
3. Para cada turno: enviar recordatorio por WhatsApp
4. Deduplicación implementada: no reenvía si ya se notificó

## Fixes aplicados

- Cron corregido: UTC 21:00 = ART 18:00 (antes estaba desfasado)
- Dedup agregado para evitar mensajes duplicados en ejecuciones solapadas
- Flujo optimizado: IF antes del Set para evitar procesamiento innecesario

## Archivo

`E:\TUAHORA\n8n-workflows\WF2-recordatorio.json`

## Dependencias

- [[EasyAppointments]] — API de turnos
- [[OpenWA]] — Envío de WhatsApp
