# Brief para OpenCode — Proyecto TuAhora MVP

## Contexto del proyecto

TuAhora es un sistema de turnos online orientado a pequeños negocios en ciudades
intermedias de Argentina (Chamical, Chilecito, La Rioja). El modelo de negocio es
de agente local: el desarrollador configura y mantiene el sistema para cada cliente,
cobrando un fee mensual.

**Cliente piloto:** Salón de uñas (1 profesional, múltiples servicios).

---

## Stack ya existente en `E:\TUAHORA\easyappointments\`

- **Easy!Appointments** — motor de reservas open source (PHP/MySQL), API REST
- **MySQL 8.0** — base de datos de Easy!Appointments
- **n8n** — orquestación de workflows (puerto 5678)
- **Baileys** — bot de WhatsApp (Node.js, puerto 3001)
- **Mailpit** — captura de emails en dev (puerto 8025)
- **Redis** — cola de mensajes

### Archivos clave
- `docker-compose.yml` — stack completo (no hay override, todo en un solo compose)
- `.env` — variables de entorno

---

## Lo que hay que construir para el MVP

### TAREA 1 — Baileys WhatsApp Service ✅ YA EXISTE

Servicio Node.js en `E:\TUAHORA\baileys-service\`:
- `GET /health` — estado del servicio y conexión WA
- `GET /qr` — QR code en base64
- `POST /send-text` — envía mensaje de texto
- `POST /send-reminder` — envía recordatorio de turno formateado

---

### TAREA 2 — docker-compose.yml ✅ YA EXISTE

El archivo `E:\TUAHORA\easyappointments\docker-compose.yml` incluye:
- `easyappointments` (puerto 8080)
- `mysql` (base de datos)
- `n8n` (puerto 5678)
- `baileys` (puerto 3001)
- `mailpit` (puerto 8025)
- `redis` (puerto 6379)

---

### TAREA 3 — Workflows n8n (exportar como JSON)

Workflows en `E:\TUAHORA\n8n-workflows\`:

#### WF-1: Confirmación inmediata de turno
- **Trigger:** Schedule cada 2 minutos (polling)
- **Lógica:**
  1. GET `/index.php/api/v1/appointments?sort=-id&length=1` contra Easy!Appointments
  2. Comparar ID con el último procesado (Set/Get workflow data)
  3. Si hay turno nuevo: extraer datos del turno (con `?with=customer,service,provider`)
  4. Formatear mensaje de confirmación en español
  5. POST a `http://tuahora_baileys:3001/send-text`
  6. Actualizar último ID procesado

#### WF-2: Recordatorio 24 horas antes
- **Trigger:** Schedule — todos los días a las 18:00
- **Lógica:**
  1. Consultar Easy!Appointments API: turnos con fecha de mañana
  2. Para cada turno: enviar recordatorio por WhatsApp
  3. (Opcional) Marcar como recordatorio enviado

#### WF-3: Cancelación por WhatsApp
- **Trigger:** Webhook POST `/webhook/whatsapp` (mensajes entrantes de Baileys)
- **Lógica:**
  1. Detectar "CANCELAR" o "cancelar"
  2. Buscar cliente en Easy!Appointments por teléfono: `GET /api/v1/customers?q={phone}`
  3. Obtener appointments del cliente: `GET /api/v1/appointments?with=customer`
  4. Filtrar turnos futuros (start > now)
  5. Si existe: `DELETE /api/v1/appointments/{id}` → confirmar por WA → notificar a la dueña
  6. Si no existe: responder "No encontré ningún turno activo"

#### WF-4: Reagendado por WhatsApp
- **Trigger:** Webhook POST `/webhook/whatsapp`
- **Lógica:**
  1. Detectar "CAMBIAR" o "REAGENDAR"
  2. Buscar turno activo (mismo flujo que WF-3)
  3. Cancelar turno actual (`DELETE /api/v1/appointments/{id}`)
  4. Responder con link del booking público: `http://localhost:8080`

---

### TAREA 4 — Landing page del salón de uñas

`E:\TUAHORA\landing-salon\index.html` — página completa en un solo archivo HTML.

**Secciones:** Hero, Servicios, Cómo funciona, Galería, Sobre nosotras, Reservar (embed/iframe de Easy!Appointments), Footer.

**El embed de Easy!Appointments** va en un iframe apuntando a `http://localhost:8080`.

---

### TAREA 5 — Script de verificación del stack

`E:\TUAHORA\scripts\check-stack.ps1` — script PowerShell que verifica:
1. Docker corriendo
2. Contenedores UP: `easyappointments`, `ea-mysql`, `n8n`, `mailpit`, `redis`, `tuahora_baileys`
3. Endpoints: `http://localhost:8080`, `http://localhost:5678/healthz`, `http://localhost:3001/health`, `http://localhost:8025/api/v1/info`

---

## Estructura de archivos esperada

```
E:\TUAHORA\
├── easyappointments\           ← NUEVO (reemplaza cal.diy)
│   ├── .env
│   └── docker-compose.yml      ← Stack completo (EA + MySQL + n8n + Redis + Mailpit + Baileys)
├── baileys-service\            ← YA EXISTE
│   ├── index.js
│   ├── package.json
│   └── Dockerfile
├── n8n-workflows\              ← YA EXISTE (actualizar para Easy!Appointments API)
│   ├── WF1-confirmacion.json
│   ├── WF2-recordatorio.json
│   ├── WF3-cancelacion.json
│   └── WF4-reagendado.json
├── landing-salon\              ← YA EXISTE
│   └── index.html
└── scripts\                   ← YA EXISTE
    └── check-stack.ps1
```

---

## API de Easy!Appointments (referencia)

Autenticación: Basic Auth con credenciales de admin.

| Endpoint | Método | Descripción |
|---|---|---|
| `/index.php/api/v1/appointments` | GET | Listar turnos |
| `/index.php/api/v1/appointments/:id` | GET | Turno por ID |
| `/index.php/api/v1/appointments` | POST | Crear turno |
| `/index.php/api/v1/appointments/:id` | PUT | Actualizar turno |
| `/index.php/api/v1/appointments/:id` | DELETE | Eliminar turno |
| `/index.php/api/v1/customers` | GET | Listar clientes |
| `/index.php/api/v1/customers?q=phone` | GET | Buscar cliente por teléfono |
| `/index.php/api/v1/services` | GET | Listar servicios |
| `/index.php/api/v1/providers` | GET | Listar proveedores |

Parámetros útiles:
- `?sort=-id,+book` — ordenar descendente por ID
- `?page=1&length=10` — paginación
- `?with=customer,service,provider` — incluir datos relacionados
- `?fields=id,start,end,hash` — solo campos específicos

---

## API de Easy!Appointments — Payload de Appointment

```json
{
  "id": 1,
  "book": "2026-06-11 12:57:00",
  "start": "2026-06-11 15:00:00",
  "end": "2026-06-11 16:00:00",
  "hash": "apTWVbSvBJXR",
  "location": "Mitre 456, Chamical",
  "notes": "",
  "customerId": 3,
  "serviceId": 1,
  "providerId": 2
}
```

Con `?with=customer,service,provider` se incluyen los objetos anidados.

---

## Notas importantes

- El proyecto vive en `E:\TUAHORA\`
- Easy!Appointments corre en `http://localhost:8080`
- La red Docker interna se llama `stack`
- La API usa Basic Auth (usuario/contraseña del admin)
- Como Easy!Appointments NO tiene webhooks nativos, WF-1 usa polling cada 2 min
- Para producción, considerar agregar webhook modificando el callback PHP de Easy!Appointments

---

## Criterios de aceptación por tarea

### TAREA 3 — Workflows n8n ✅ cuando:
- Los 4 archivos JSON se importan en n8n sin errores
- WF-1: al crearse un nuevo turno en Easy!Appointments, dentro de 2 min llega WhatsApp
- WF-2: al ejecutar manualmente, consulta Easy!Appointments y envía recordatorios
- WF-3: al enviar "CANCELAR" por WhatsApp, el turno se cancela en Easy!Appointments
- WF-4: al enviar "CAMBIAR", se cancela el turno actual y se envía link del booking page

### TAREA 4 — Landing ✅ cuando:
- Se ve correctamente en mobile (375px) y desktop (1280px)
- El embed/iframe de Easy!Appointments carga correctamente
- La página pesa menos de 200KB sin imágenes externas

### TAREA 5 — check-stack.ps1 ✅ cuando:
- Corre sin errores con `.\check-stack.ps1`
- Muestra verde para servicios UP y rojo para DOWN
- Termina con código 0 si todo OK
