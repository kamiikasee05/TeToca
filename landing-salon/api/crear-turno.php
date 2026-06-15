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

if (!$serviceId || !$date || !$time || !$firstName || !$phone) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan campos requeridos: serviceId, date, time, firstName, phone']);
    exit;
}

$ea_user = $_ENV['EA_API_USER'] ?? 'kamiikasee';
$ea_pass = $_ENV['EA_API_PASS'] ?? 'admin2024';
$auth = $ea_user . ':' . $ea_pass;
$ea = 'http://localhost/index.php/api/v1';

// 1. Get service duration
$ch = curl_init("$ea/services/$serviceId");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $auth, CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_TIMEOUT => 5]);
$svcBody = curl_exec($ch);
$svcCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$svc = json_decode($svcBody, true);
if (!$svc || (isset($svc['success']) && $svc['success'] === false) || $svcCode !== 200) {
    http_response_code(500);
    echo json_encode(['error' => 'Servicio no encontrado en EasyAppointments', 'detail' => $svc['message'] ?? 'HTTP ' . $svcCode]);
    exit;
}

$duration = (int)$svc['duration'];
$startDt = "$date $time:00";
$endDt = date('Y-m-d H:i:s', strtotime($startDt) + $duration * 60);

// 2. Find or create customer
$customerId = null;
$ch = curl_init("$ea/customers");
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_USERPWD => $auth, CURLOPT_HTTPAUTH => CURLAUTH_BASIC, CURLOPT_TIMEOUT => 5]);
$customersRes = curl_exec($ch);
$customersCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$customers = ($customersCode === 200) ? (json_decode($customersRes, true) ?: []) : [];

foreach ($customers as $c) {
    if (strtolower($c['email'] ?? '') === strtolower($email) || strtolower($c['phone'] ?? '') === strtolower($phone)) {
        $customerId = $c['id'];
        break;
    }
}

if (!$customerId) {
    $payload = json_encode([
        'firstName' => $firstName,
        'lastName' => $lastName ?: $firstName,
        'email' => $email ?: ('no-email-' . substr(md5($phone), 0, 10) . '@tuahora.com.ar'),
        'phone' => $phone,
    ]);
    $ch = curl_init("$ea/customers");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => $auth,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    $res = curl_exec($ch);
    $createCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $body = json_decode($res, true);
    if (!$body || $createCode >= 400 || (isset($body['success']) && $body['success'] === false)) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear cliente en EasyAppointments', 'detail' => $body['message'] ?? 'HTTP ' . $createCode]);
        exit;
    }
    $customerId = $body['id'] ?? null;
    if (!$customerId) {
        http_response_code(500);
        echo json_encode(['error' => 'Error al crear cliente: ID no recibido']);
        exit;
    }
}

// 3. Create appointment
$payload = json_encode([
    'start' => $startDt,
    'end' => $endDt,
    'serviceId' => $serviceId,
    'providerId' => 5,
    'customerId' => $customerId,
    'notes' => "Reserva desde tuahora.com.ar",
]);

$ch = curl_init("$ea/appointments");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => $auth,
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(500);
    echo json_encode(['error' => 'Error de conexión con EasyAppointments: ' . $curlErr]);
    exit;
}

$appt = json_decode($res, true);
if ($code >= 400 || !$appt || (isset($appt['success']) && $appt['success'] === false)) {
    $eaMsg = is_array($appt) ? ($appt['message'] ?? json_encode($appt)) : 'HTTP ' . $code;
    http_response_code(500);
    echo json_encode(['error' => 'Error al crear turno en EasyAppointments', 'detail' => $eaMsg]);
    exit;
}

echo json_encode([
    'success' => true,
    'appointmentId' => $appt['id'] ?? null,
    'start' => $startDt,
    'end' => $endDt,
    'service' => $svc['name'],
    'customer' => $firstName,
    'message' => "Turno confirmado para el $date a las $time",
]);
