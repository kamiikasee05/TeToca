# Monitoreo — TuAhora en Produccion

## Arquitectura de monitoreo

```
Servidor (Windows/VPS)
  ├── health-check.ps1  (cada 5 min via tarea programada)
  ├── backup-mysql.ps1  (diario via tarea programada)
  └── Docker logs       (via Uptime Robot / ntfy.sh)
        │
        ▼
  ntfy.sh (alertas) ───> Telegram / WhatsApp
  Uptime Robot (uptime) ───> Email / Telegram
```

---

## 1. Health check interno (script local)

El script `scripts\health-check.ps1` verifica:

| Que verifica | Como |
|---|---|
| Docker corriendo | `docker info` |
| Cada contenedor UP | `docker ps` por nombre |
| Cada endpoint responde | `Invoke-WebRequest` |
| Alerta en fallo | POST a ntfy.sh |

### Programar en Windows Task Scheduler

```powershell
# Crear tarea que corre cada 5 minutos
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File E:\TUAHORA\scripts\health-check.ps1 -Quiet -NtfyTopic tuahora-alertas"

$trigger = New-ScheduledTaskTrigger -Once -At (Get-Date) -RepetitionInterval (New-TimeSpan -Minutes 5) -RepetitionDuration ([TimeSpan]::MaxValue)

Register-ScheduledTask -TaskName "TuAhora-HealthCheck" -Action $action -Trigger $trigger -RunLevel Highest
```

### Programar backup diario

```powershell
$backupAction = New-ScheduledTaskAction -Execute "powershell.exe" `
    -Argument "-NoProfile -ExecutionPolicy Bypass -File E:\TUAHORA\scripts\backup-mysql.ps1"

$backupTrigger = New-ScheduledTaskTrigger -Daily -At "03:00"

Register-ScheduledTask -TaskName "TuAhora-BackupMySQL" -Action $backupAction -Trigger $backupTrigger -RunLevel Highest
```

---

## 2. Uptime Robot (o similar)

### Plan gratuito
- **Uptime Robot** (https://uptimerobot.com): 50 monitores gratis, checks cada 5 min
- **Better Uptime** (https://betteruptime.com): alternativa con plan gratis limitado
- **Pulsetic** (https://pulsetic.com): similar, con alertas a WhatsApp

### Monitores a crear (plan gratis)

| Monitor | URL | Tipo | Intervalo |
|---|---|---|---|
| Booking publico | `https://booking.tudominio.com.ar` | HTTPS | 5 min |
| Admin n8n | `https://admin.tudominio.com.ar/healthz` | HTTPS | 5 min |
| API (opcional) | `https://booking.tudominio.com.ar/index.php/api/v1/appointments` | HTTPS | 5 min |

### Configurar metodo de alerta en Uptime Robot
1. Ir a "My Settings" -> "Alert Contacts"
2. Agregar:
   - Email (gratis)
   - Telegram (gratis, requiere bot token)
   - SMS (pago, no recomendado)

---

## 3. Alertas por Telegram

Usando el bot de Uptime Robot integrado:

1. En Telegram, buscar `@UptimeRobotBot`
2. Iniciar conversacion y obtener el chat ID
3. En Uptime Robot -> My Settings -> Alert Contacts -> Add Telegram
4. Ingresar el token/chat ID recibido

Nota: Uptime Robot no tiene integracion nativa con WhatsApp.
Usar Telegram como alternativa (o ntfy.sh con bridge a WhatsApp).

---

## 4. Alertas por WhatsApp via ntfy.sh

ntfy.sh (https://ntfy.sh) es gratuito, sin registro, y permite publicar/consumir mensajes via HTTP.

### Enviar alerta manual de prueba

```powershell
$body = @{
    topic = "tuahora-alertas"
    message = "Prueba de alerta - TuAhora monitoreo"
    title = "TuAhora"
    priority = 3
    tags = @("test")
} | ConvertTo-Json

Invoke-RestMethod -Uri "https://ntfy.sh/tuahora-alertas" -Method Post -Body $body -ContentType "application/json"
```

### Recibir alertas en WhatsApp

Opcion A - Usar el webhook de n8n como bridge:
```
ntfy.sh/tuahora-alertas --> Webhook --> n8n --> Baileys (WhatsApp)
```

Opcion B - Usar un bot de Telegram como intermediario:
```
ntfy.sh/tuahora-alertas --> Telegram Bot --> usuario
```

### Suscribirse desde el celular (recomendado)

1. Instalar app ntfy (Android/iOS)
2. Suscribirse al topic `tuahora-alertas`
3. Configurar notificaciones push

---

## 5. Alertas por WhatsApp via n8n (recomendado)

Flujo completo usando la infraestructura existente:

```
Health check falla
       │
       ▼
ntfy.sh (topic: tuahora-alertas)
       │
       ▼
n8n Webhook (recibe el POST de ntfy.sh)
       │
       ▼
n8n Workflow (formatea mensaje)
       │
       ▼
Baileys API (POST /send-text)
       │
       ▼
WhatsApp al operador
```

### Configurar ntfy.sh como webhook en n8n

1. En n8n, crear un nuevo workflow con trigger "Webhook"
2. URL: `https://n8n.tudominio.com.ar/webhook/tuahora-alertas` (expuesto via Cloudflare)
3. Configurar ntfy.sh para que haga POST a esa URL cuando haya una alerta:

```bash
# Desde cualquier script, enviar alerta a ntfy con callback webhook
curl -H "X-Callback: https://admin.tudominio.com.ar/webhook/tuahora-alertas" \
     -d "TuAhora - Fallo detectado en health check" \
     https://ntfy.sh/tuahora-alertas
```

### Workflow n8n de alerta

```json
{
  "name": "Alerta Monitoreo",
  "trigger": "Webhook",
  "steps": [
    { "type": "Webhook", "path": "tuahora-alertas" },
    { "type": "Set", "values": {
        "message": "ALERTA TuAhora: {{ $json.body }}",
        "phone": "549XXXXXXXXXX"
    }},
    { "type": "HTTP Request", "url": "http://tuahora_baileys:3001/send-text", "method": "POST",
      "body": { "phone": "{{ $json.phone }}", "message": "{{ $json.message }}" } }
  ]
}
```

---

## 6. Dashboard opcional

Para un dashboard visual sin costo:

- **Grafana** (local en Docker) + Prometheus: overkill para este proyecto
- **Uptime Robot public status page**: plan gratis incluye 1 pagina publica

### Activar Status Page en Uptime Robot

1. My Settings -> Public Status Page
2. Activar y personalizar
3. Compartir URL: `https://stats.uptimerobot.com/XXXXXXXXX`

---

## 7. Checklist de puesta en marcha

- [ ] health-check.ps1 corriendo cada 5 min (Task Scheduler)
- [ ] backup-mysql.ps1 corriendo cada 24 h (Task Scheduler, 3 AM)
- [ ] Verificar que el backup se genera correctamente
- [ ] Verificar que ntfy.sh recibe alertas
- [ ] Uptime Robot monitoreando booking.tudominio.com.ar
- [ ] Uptime Robot monitoreando admin.tudominio.com.ar
- [ ] Alerta configurada en Telegram
- [ ] (Opcional) Bridge ntfy.sh -> WhatsApp via n8n
- [ ] Status page publica compartida con el cliente
- [ ] Probar corte manual: detener un contenedor y verificar que llega alerta
