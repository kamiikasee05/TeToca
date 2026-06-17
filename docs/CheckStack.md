# Script de Verificación del Stack

## check-stack.ps1

Archivo: `E:\TUAHORA\scripts\check-stack.ps1`

## Qué verifica

1. Docker corriendo
2. Contenedores UP: `easyappointments`, `ea-mysql`, `n8n`, `mailpit`, `redis`, `tuahora_openwa`
3. Endpoints HTTP:
   - `http://localhost:8080`
   - `http://localhost:5678/healthz`
   - `http://localhost:2785/health`
   - `http://localhost:8025/api/v1/info`

## Uso

```powershell
.\scripts\check-stack.ps1
```

Muestra verde para servicios UP, rojo para DOWN. Termina con código 0 si todo OK.
