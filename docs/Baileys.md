# Baileys — Servicio WhatsApp (ELIMINADO)

> ⚠️ **Eliminado — 14 Junio 2026**
> 
> El directorio `baileys-service/` fue removido del filesystem. Este documento queda como registro histórico.

Bot de WhatsApp usando la librería Baileys (Node.js). Operó desde el inicio del proyecto como gateway WhatsApp en `tuahora_baileys:3001`.

## Razón del reemplazo

- Inestabilidad de sesión (desconexiones frecuentes, re-escanear QR)
- Problemas de compatibilidad de dependencias Node.js
- OpenWA ofrece gestión de sesión más robusta vía whatsapp-web.js engine

## Migración completada

- `baileys-service/` eliminado del repositorio
- `docker-compose.yml` no incluye servicio Baileys
- Workflows n8n migrados a [[OpenWA]] en `tuahora_openwa:2785`
- Variables `BAILEYS_API_KEY` eliminadas de `.env` y `.env.example`

## Relacionado

- [[README|Volver al inicio]]
- [[OpenWA]] — Servicio actual
