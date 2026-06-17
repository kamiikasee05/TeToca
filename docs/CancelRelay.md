# Cancel Relay — PHP Endpoint

**Archivo:** `E:\TUAHORA\tuahora\api\cancel-appointment.php`

**Endpoint:** `GET /tuahora/api/cancel-appointment.php?id=X`

**Propósito:** Permite a los workflows de n8n cancelar turnos en Easy!Appointments mediante una solicitud GET simple, delegando la llamada PUT a la API de EA desde el mismo servidor PHP.

## Cómo funciona

1. n8n hace GET a `http://ea-container/tuahora/api/cancel-appointment.php?id=42`
2. El script PHP recibe el `id` del appointment
3. Hace curl PUT a `http://localhost/api/v1/appointments/42` con body `{ "status": "cancelled" }`
4. Usa Basic Auth con credenciales de EA admin
5. Retorna JSON con el resultado: `{ "success": true }` o `{ "success": false, "error": "..." }`

## Por qué existe

El HTTP Request node de n8n tenía problemas para hacer PUT directamente a EA API (auth entre containers, formato de body). Al delegar la llamada a un script PHP que corre dentro del mismo container que EA, se eliminan las barreras de networking y autenticación.

## Código

```php
<?php
$id = $_GET['id'] ?? null;
if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id parameter']);
    exit;
}

$url = "http://localhost/api/v1/appointments/" . intval($id);
$username = 'admin';
$password = 'TUAHORA_EA_PASSWORD';

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['status' => 'cancelled']));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_USERPWD, "$username:$password");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300) {
    echo json_encode(['success' => true]);
} else {
    http_response_code(502);
    echo json_encode(['success' => false, 'error' => "EA API returned $httpCode"]);
}
```

## Uso en workflows

### WF3 — Cancelación
- Node: HTTP Request (GET)
- URL: `http://{{EA_HOST}}/tuahora/api/cancel-appointment.php?id={{ $json.appointmentId }}`
- Se ejecuta tras el Filter node cuando hay turno activo

### WF4 — Reagendado
- Node: HTTP Request (GET)
- URL: `http://{{EA_HOST}}/tuahora/api/cancel-appointment.php?id={{ $json.appointmentId }}`
- Se ejecuta antes de enviar el link de reagendado

## Dependencias

- [[EasyAppointments]] — El script corre dentro del container de EA
- PHP con extensión curl habilitada
- Credenciales de EA admin configuradas en el script

## Relacionado

- [[WF3-Cancelacion]]
- [[WF4-Reagendado]]
- [[Sesion-2026-06-14]]
- [[README|Volver al inicio]]
