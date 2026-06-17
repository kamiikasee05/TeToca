<?php
require_once __DIR__ . '/../env-loader.php';
header('Content-Type: application/json');
require_once __DIR__ . '/cors.php';

$serviceId = (int)($_GET['serviceId'] ?? 0);
$date = $_GET['date'] ?? '';

if (!$serviceId || !$date) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan serviceId o date']);
    exit;
}

$key = $_ENV['SCHEDULER_API_KEY'] ?? $_ENV['EA_API_PASS'] ?? '';
$url = (schedulerApiUrl()) . "/availabilities?providerId=5&serviceId={$serviceId}&date={$date}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key],
    CURLOPT_TIMEOUT => 5,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode !== 200) {
    http_response_code(502);
    echo json_encode(['error' => 'Error al consultar disponibilidad']);
    exit;
}

http_response_code(200);
echo $response;
