# Checklist de configuración pendiente — TuAhora

## 1. 📱 WhatsApp — Escanear QR

**Objetivo:** Vincular WhatsApp de Laura para que el sistema pueda enviar mensajes.

**Pasos:**
1. Abrí `http://localhost:8080/tuahora/admin/dashboard.php`
2. Iniciá sesión con user: `admin`, password: `admin2024`
3. Hacé clic en la pestaña **WhatsApp**
4. Si ves un código QR, **abrí WhatsApp en tu celular**
5. Andá a los **3 puntos (⋮) → Dispositivos vinculados → Vincular un dispositivo**
6. Escaneá el QR que aparece en la pantalla
7. Debería cambiar automáticamente a "✅ WhatsApp conectado"

**Si no aparece QR:** esperá unos segundos, la página lo refresca sola cada 5 segundos.

---

## 2. ⚙️ Easy!Appointments — Configurar reglas de turnos

**Objetivo:** Hacer que el booking funcione bien para clientes.

**Pasos:**
1. Abrí `http://localhost:8080/index.php/backend`
2. Iniciá sesión con user: `kamiikasee`, password: `admin2024`
3. Andá a **Configuración** (Settings)
4. Configurá lo siguiente:

**Pestaña General:**
- [ ] **Teléfono obligatorio** → marcar (Phone number required)

**Pestaña Clientes:**
- [ ] **Desactivar registro** → marcar (deshabilita que se registren solos, solo reservan)

**Pestaña Turnos (Appointments):**
- [ ] **Intervalo de tiempo mínimo entre turnos** → dejar en `0` (lo controlamos desde PHP)
- [ ] **Ventana de reserva máxima (días)** → `30` (o lo que quieras)
- [ ] **Tiempo mínimo antes de la reserva (minutos)** → `0` (lo controlamos desde PHP)

**Pestaña Notificaciones:**
- [ ] **Desactivar notificaciones de EA** (para que no mande mails duplicados, ya que n8n va a mandar WhatsApp)

---

## 3. 🔗 n8n — Configurar credenciales de los workflows

**Objetivo:** Los 4 workflows importados necesitan credenciales para conectarse a EA y a Baileys.

**Pasos:**
1. Abrí `http://localhost:5678/` en el navegador
2. Creá una cuenta (es local, cualquier mail/contraseña sirve)
3. Una vez dentro, andá a **Workflows**

### Workflow 1: WF1 - Confirmación de turno

- [ ] Buscar el nodo **"HTTP Request - EA API"** (o similar)
- [ ] Configurar la URL: `http://easyappointments/index.php/api/v1/appointments`
- [ ] Configurar **Credential**: Basic Auth
  - User: `kamiikasee`
  - Password: `admin2024`

- [ ] Buscar el nodo **"HTTP Request - Baileys"**
- [ ] Configurar la URL: `http://tuahora_baileys:3001/send-message`

### Workflow 2: WF2 - Recordatorio (igual que WF1)

- [ ] Misma configuración de credenciales que WF1

### Workflow 3: WF3 - Cancelación

- [ ] Misma configuración de credenciales que WF1

### Workflow 4: WF4 - Reagendado

- [ ] Misma configuración de credenciales que WF1

### Activar workflows

Una vez configuradas las credenciales en cada workflow:
- [ ] Hacé clic en **"Active"** (toggle) en cada workflow para activarlos

---

## 4. 🔁 Prueba integral — Reserva real

**Objetivo:** Verificar que todo el flujo funciona de punta a punta.

**Pasos:**
1. Abrí `http://localhost:8080/tuahora/`
2. Seleccioná un servicio (ej: "Manicura simple")
3. Elegí una fecha y horario disponible
4. Completá nombre, teléfono (ej: `3825123456`), email opcional
5. Confirmá la reserva
6. Aparece el mensaje "Turno reservado con éxito"

**Verificaciones post-reserva:**
- [ ] El turno aparece en el Admin → pestaña **Turnos**
- [ ] El turno aparece en el Admin → pestaña **Calendario**
- [ ] Laura recibe el mensaje de WhatsApp (si ya escaneó el QR)
- [ ] Aparece una notificación en n8n (se puede ver en el historial de ejecuciones)

---

## 5. ❌ Prueba de cancelación

- [ ] En Admin → Turnos, hacer clic en **Cancelar** de algún turno
- [ ] Verificar que desaparece del calendario
- [ ] Verificar que cambia a estado "Cancelado"

---

## 6. 📅 Prueba de reagendado

- [ ] En Admin → Turnos, hacer clic en **Ver** de algún turno
- [ ] Clic en **Reagendar**
- [ ] Seleccionar nueva fecha y horario
- [ ] Confirmar
- [ ] Verificar que el turno se actualizó en el calendario

---

## 7. 🧪 Probar horarios de Laura

**Objetivo:** Verificar que los cambios en los horarios se reflejan en la landing.

**Pasos:**
1. En Admin → pestaña **Horarios**
2. Desactivar un día (ej: domingo)
3. Guardar
4. Ir a la landing y verificar que ese día ya no muestra horarios disponibles
5. Cambiar el horario de atención (ej: lunes 10:00-17:00)
6. Guardar
7. Verificar en la landing que los slots se ajustaron

---

## Resumen de URLs

| Qué | URL |
|---|---|
| Landing page | `http://localhost:8080/tuahora/` |
| Admin panel | `http://localhost:8080/tuahora/admin/` |
| EA backend | `http://localhost:8080/index.php/backend` |
| EA login (Laura) | `http://localhost:8080/index.php/login` |
| n8n | `http://localhost:5678/` |

## Credenciales

| Sistema | User | Password |
|---|---|---|
| Admin panel | `admin` | `admin2024` |
| EA backend | `kamiikasee` | `admin2024` |
| EA provider (Laura) | `laura` | `laura2024` |
| MySQL | `root` | `admin2024` |
