<?php
require_once __DIR__ . '/../env-loader.php';
header('Content-Type: application/json');
require_once __DIR__ . '/cors.php';

$url = (schedulerApiUrl()) . '/services';
$key = $_ENV['SCHEDULER_API_KEY'] ?? $_ENV['EA_API_PASS'] ?? '';

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
    http_response_code(500);
    echo json_encode(['error' => 'Error al obtener servicios']);
    exit;
}

echo $response;
