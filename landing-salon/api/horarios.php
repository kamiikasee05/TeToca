<?php
require_once __DIR__ . '/../env-loader.php';
header('Content-Type: application/json');
require_once __DIR__ . '/cors.php';

$serviceId = (int)($_GET['serviceId'] ?? 0);
$date = $_GET['date'] ?? '';
if (!$serviceId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Faltan serviceId y date (YYYY-MM-DD)']);
    exit;
}

$key = $_ENV['SCHEDULER_API_KEY'] ?? $_ENV['EA_API_PASS'] ?? '';
$url = (schedulerApiUrl()) . "/slots?serviceId={$serviceId}&date={$date}";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key],
    CURLOPT_TIMEOUT => 5,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode === 200 ? 200 : 502);
echo $httpCode === 200 ? $response : json_encode(['slots' => [], 'error' => 'Error al consultar horarios']);
