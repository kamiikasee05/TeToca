<?php
require_once __DIR__ . '/../env-loader.php';
header('Content-Type: application/json');
require_once __DIR__ . '/cors.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Usar POST']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'JSON inválido']);
    exit;
}

$serviceId = (int)($data['serviceId'] ?? 0);
$date = $data['date'] ?? '';
$time = $data['time'] ?? '';
$firstName = trim($data['firstName'] ?? '');
$lastName = trim($data['lastName'] ?? '');
$email = trim($data['email'] ?? '');
$phone = trim($data['phone'] ?? '');
$phone = preg_replace('/\D/', '', $phone);
$phone = preg_replace('/^54/', '', $phone);
$phone = preg_replace('/^0/', '', $phone);
$phone = preg_replace('/^(\d{2,4})15/', '$1', $phone);

if (!$serviceId || !$date || !$time || !$firstName || !$phone) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos requeridos: serviceId, date, time, firstName, phone']);
    exit;
}

$key = $_ENV['SCHEDULER_API_KEY'] ?? $_ENV['EA_API_PASS'] ?? '';
$scheduler = schedulerApiUrl();

// 1. Get service duration
$r = schedulerApiCall("/services/$serviceId");
if ($r['httpCode'] !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Servicio no encontrado', 'detail' => $r['data']['message'] ?? 'HTTP ' . $r['httpCode']]);
    exit;
}
$svc = $r['data'];
$duration = (int)$svc['duration'];
$startDt = "$date $time:00";
$endDt = date('Y-m-d H:i:s', strtotime($startDt) + $duration * 60);

// 2. Find or create customer
$r = schedulerApiCall("/customers?q=" . urlencode($phone));
$customers = ($r['httpCode'] === 200 && is_array($r['data'])) ? $r['data'] : [];
$customerId = null;
foreach ($customers as $c) {
    if (strtolower($c['phone'] ?? '') === strtolower($phone)) {
        $customerId = $c['id'];
        break;
    }
}

if (!$customerId) {
    $r = schedulerApiCall('/customers', 'POST', [
        'firstName' => $firstName,
        'lastName' => $lastName ?: $firstName,
        'email' => $email ?: ('no-email-' . substr(md5($phone), 0, 10) . '@tetoca.com.ar'),
        'phone' => $phone,
    ]);
    if ($r['httpCode'] >= 400) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear cliente', 'detail' => $r['data']['message'] ?? 'HTTP ' . $r['httpCode']]);
        exit;
    }
    $customerId = $r['data']['id'] ?? null;
    if (!$customerId) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear cliente: ID no recibido']);
        exit;
    }
}

// 3. Create appointment
$r = schedulerApiCall('/appointments', 'POST', [
    'start' => $startDt,
    'end' => $endDt,
    'serviceId' => $serviceId,
    'providerId' => 5,
    'customerId' => $customerId,
    'notes' => "Reserva desde tetoca.com.ar",
]);

if ($r['httpCode'] >= 400) {
    http_response_code(500);
    echo json_encode(['error' => 'Error al crear turno', 'detail' => $r['data']['message'] ?? 'HTTP ' . $r['httpCode']]);
    exit;
}

$appt = $r['data'];
echo json_encode([
    'success' => true,
    'appointmentId' => $appt['id'] ?? null,
    'start' => $startDt,
    'end' => $endDt,
    'service' => $svc['name'],
    'customer' => $firstName,
    'message' => "Turno confirmado para el $date a las $time",
]);
