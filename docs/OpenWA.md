# OpenWA — WhatsApp Gateway

Servicio WhatsApp usando OpenWA (open-wa/whatsapp-web.js engine).

## Puerto

`2785`

## Red Docker

Se comunica internamente como `tuahora_openwa:2785`

## Endpoints API principales

| Endpoint | Método | Descripción |
|---|---|---|
| `/health` | GET | Estado del servicio |
| `/api/sendText` | POST | Envía mensaje de texto a un número |
| `/api/qr` | GET | Código QR para emparejar WhatsApp Web |
| `/api/logout` | POST | Cierra sesión de WhatsApp |

## Session Management (reconexión tras reinicio)

Las sesiones de WhatsApp Web se almacenan en el volumen `openwa_data`, montado en `/app/data` dentro del contenedor. La ruta de sesión configurada es `/app/data/sessions`.

### Después de un reinicio del contenedor

1. El contenedor arranca y carga la sesión persistida del volumen
2. Si la sesión es válida, se reconecta automáticamente sin necesidad de re-escanear QR
3. Si la sesión expiró o es inválida, hay que volver a escanear el QR

### Procedimiento de reconexión manual

```powershell
# 1. Verificar estado
curl http://localhost:2785/health

# 2. Si no está conectado, obtener QR nuevo
curl http://localhost:2785/api/qr

# 3. Escanear el QR con WhatsApp mobile (Linked Devices)
```

### API Master Key

Todas las llamadas a la API requieren el header:
```
X-Api-Key: tuahora_openwa_2024
```

## SingletonLock Cleanup (whatsapp-web.js)

Si se produce un error `Protocol error (Target.setDiscoverTargets): Target closed` o hay conflictos de instancia de Puppeteer:

1. Detener el contenedor: `docker compose stop openwa`
2. Eliminar el lock: `docker compose run --rm openwa rm -f /app/data/SingletonLock`
3. Reiniciar: `docker compose up -d openwa`

Puede agregarse un healthcheck en docker-compose que limpie este lock automáticamente:
```yaml
healthcheck:
  test: ["CMD-SHELL", "test ! -f /app/data/SingletonLock || exit 0"]
  interval: 60s
  retries: 1
```

## PHP Relay Configuration

El contenedor `easyappointments` se comunica con OpenWA vía HTTP desde el backend PHP:

- URL base: `http://tuahora_openwa:2785` (red interna `stack`)
- Header: `X-Api-Key: tuahora_openwa_2024`
- Endpoints usados:
  - `POST /api/sendText` — envío de mensajes de texto
  - `GET /health` — verificación de estado

### Ejemplo de llamada desde PHP

```php
$ch = curl_init('http://tuahora_openwa:2785/api/sendText');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'X-Api-Key: tuahora_openwa_2024',
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'phone' => '5491123456789',
    'text' => 'Tu turno ha sido confirmado',
]));
curl_exec($ch);
```

## Webhook Configuration (mensajes entrantes)

OpenWA puede reenviar mensajes entrantes a n8n mediante webhooks. Esto es necesario para WF3 y WF4.

### Configurar webhook en OpenWA

```powershell
# POST al endpoint de configuración de webhooks de OpenWA
curl -X POST http://localhost:2785/api/webhook/set `
  -H "X-Api-Key: tuahora_openwa_2024" `
  -H "Content-Type: application/json" `
  -d '{"url": "http://tuahora_n8n:5678/webhook/whatsapp", "events": ["message"]}'
```

El payload que OpenWA envía al webhook incluye `data.from` (número del remitente), `data.body` (texto del mensaje) y `data.notifyName`.

### Verificar webhook configurado

```powershell
curl http://localhost:2785/api/webhook/get -H "X-Api-Key: tuahora_openwa_2024"
```

### Nota sobre reinicios

Al reiniciar el contenedor OpenWA, la configuración de webhooks puede perderse. Verificar y reconfigurar si es necesario después de cada reinicio.

## Relacionado

- [[README|Volver al inicio]]
- [[DockerCompose]]
- [[WF1-Confirmacion]]
- [[WF2-Recordatorio]]
- [[WF3-Cancelacion]]
- [[WF4-Reagendado]]
- [[Baileys]] — Servicio anterior, deprecado
