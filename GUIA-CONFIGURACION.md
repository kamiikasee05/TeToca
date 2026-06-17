# Guía de Configuración — TuAhora MVP (Easy!Appointments)

## Stack actual

| Servicio | URL | Puerto | Autenticación |
|----------|-----|--------|---------------|
| Easy!Appointments | http://localhost:8080 | 8080 | Crear cuenta en wizard |
| n8n | http://localhost:5678 | 5678 | Crear cuenta en el login |
| Baileys | http://localhost:3001 | 3001 | Solo API |
| Mailpit | http://localhost:8025 | 8025 | Sin auth (dev) |
| MySQL | localhost:3306 | 3306 | ea_user / ea_pass_2024 |

---

## Paso 1 — Iniciar el stack

```powershell
cd E:\TUAHORA\easyappointments
docker compose up -d
```

Verificar que todos los contenedores estén UP:
```powershell
docker compose ps
```

---

## Paso 2 — Configurar Easy!Appointments (wizard inicial)

1. Abrí http://localhost:8080
2. Completá el wizard de instalación:
   - Completar datos del administrador (usuario: `admin`, contraseña: elegí una)
   - Nombre del negocio: `Nails by Laura`
   - Configurar huso horario: `America/Argentina/La_Rioja` (o `America/Argentina/Buenos_Aires`)
3. Ir a **Backend > Settings** para ajustar:
   - **Booking page**: configurar título, descripción, días de anticipación
   - **Working plan**: Lun-Vie 9:00-18:00, Sáb 9:00-14:00
   - **Notifications**: desactivar emails si se usa WhatsApp (o dejarlos para Mailpit)

---

## Paso 3 — Crear servicios, proveedores y horarios

### Servicios

Ir a **Backend > Services** y crear los 6 servicios:

| Servicio | Duración | Precio | Categoría |
|----------|----------|--------|-----------|
| Manicura simple | 45 min | $8.000 | Manicuría |
| Manicura semipermanente | 60 min | $12.000 | Manicuría |
| Pedicura simple | 60 min | $10.000 | Pedicuría |
| Kapping | 90 min | $18.000 | Esculpidas |
| Nail Art | 30 min | $5.000 | Diseños |
| Combo mani + pedi | 90 min | $16.000 | Combos |

### Proveedores

Ir a **Backend > Providers** y crear:
- Nombre: `Laura García`
- Email: `laura@salondenails.com`
- Teléfono: `+543826405610`
- **Services**: asignar todos los servicios
- **Working plan**: configurar horario Lun-Vie 9-18, Sáb 9-14

---

## Paso 4 — Conectar WhatsApp (escanear QR)

1. Abrí http://localhost:3001/qr-page en el navegador
2. Escaneá el QR con WhatsApp en tu celu (WhatsApp > Dispositivos vinculados > Vincular dispositivo)
3. Verificá que el estado cambie a `connected`:
   ```
   GET http://localhost:3001/health
   → {"status":"ok","whatsapp":"connected"}
   ```

---

## Paso 5 — Configurar API de Easy!Appointments

La API usa **Basic Auth** con usuario/contraseña del admin.

Probar que funciona:
```powershell
curl -u admin:TU_CONTRASEÑA http://localhost:8080/index.php/api/v1/services
```

Guardar credenciales en n8n como **Generic Credentials** para usarlas en los workflows.

---

## Paso 6 — Importar workflows en n8n

1. Abrí http://localhost:5678
2. Crear cuenta (la primera vez)
3. Ir a **Workflows** > **Import from File**
4. Importar los 4 archivos de `E:\TUAHORA\n8n-workflows\`
5. **Ajustar URLs y credenciales** en cada workflow:
   - Reemplazar `http://easyappointments:80` con `http://easyappointments:80` (nombre del contenedor)
   - Configurar Basic Auth con el usuario/contraseña del admin de EA
   - URL de Baileys: `http://tuahora_baileys:3001`
6. **Activar** cada workflow

---

## Paso 7 — Configurar webhook en Baileys (entrante)

Baileys ya reenvía mensajes entrantes a `http://n8n:5678/webhook/whatsapp` automáticamente (configurado via `N8N_WEBHOOK_URL`).

WF-3 (cancelación) y WF-4 (reagendado) escuchan en ese mismo webhook y filtran por keyword.

---

## Paso 8 — Abrir la landing page

```
E:\TUAHORA\landing-salon\index.html
```

O servila:
```powershell
cd E:\TUAHORA\landing-salon
python -m http.server 8080
```

---

## Paso 9 — Probar flujo completo

1. Abrí la landing → click **Reservar**
2. En el booking de Easy!Appointments: seleccioná servicio → proveedor → día/hora → completá datos
3. Verificá que:
   - Aparece en Easy!Appointments como turno creado
   - Dentro de 2 minutos llega el WhatsApp de confirmación (WF-1 polling)
   - El turno aparece en la agenda del backend
4. Respondé `CANCELAR` al WhatsApp → debe cancelar el turno (WF-3)
5. Respondé `CAMBIAR` → debe cancelar el turno y enviar link para reservar de nuevo (WF-4)
6. Al día siguiente a las 18:00, WF-2 envía recordatorio automático

---

## API de Easy!Appointments — Referencia rápida

| Acción | Método | URL |
|--------|--------|-----|
| Listar turnos | GET | `/index.php/api/v1/appointments` |
| Listar turnos (con datos) | GET | `/index.php/api/v1/appointments?with=customer,service,provider` |
| Buscar cliente por teléfono | GET | `/index.php/api/v1/customers?q=5493826405610` |
| Eliminar turno | DELETE | `/index.php/api/v1/appointments/1` |
| Listar servicios | GET | `/index.php/api/v1/services` |
| Listar proveedores | GET | `/index.php/api/v1/providers` |

Todas requieren header `Authorization: Basic <base64(user:pass)>`.

---

## URLs de acceso rápido

| Servicio | URL |
|----------|-----|
| Easy!Appointments (frontend) | http://localhost:8080 |
| Easy!Appointments (backend) | http://localhost:8080/index.php/backend |
| n8n | http://localhost:5678 |
| Baileys health | http://localhost:3001/health |
| Baileys QR | http://localhost:3001/qr-page |
| Mailpit (emails) | http://localhost:8025 |
| Landing page | `E:\TUAHORA\landing-salon\index.html` |

---

## Comandos útiles

```powershell
# Ver logs
docker compose -f easyappointments\docker-compose.yml logs -f easyappointments
docker logs tuahora_baileys
docker logs n8n

# Ver todos los contenedores
docker ps

# Verificar stack completo
E:\TUAHORA\scripts\check-stack.ps1

# Detener todo
docker compose -f easyappointments\docker-compose.yml down

# Iniciar todo
docker compose -f easyappointments\docker-compose.yml up -d
```
