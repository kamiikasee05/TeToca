<?php
require_once __DIR__ . '/../env-loader.php';
header('Content-Type: application/json');

$token = $_GET['token'] ?? $_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? '';
$expectedToken = $_ENV['N8N_WEBHOOK_TOKEN'] ?? '';
if (!$token || !$expectedToken || $token !== $expectedToken) {
    http_response_code(401);
    echo json_encode(['error' => 'Token inválido o faltante']);
    exit;
}

$id = $_GET['id'] ?? '';
if (empty($id) || !is_numeric($id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing appointment id']);
    exit;
}

$r = schedulerApiCall("/appointments/$id", 'PUT', ['status' => 'cancelled']);
if (!empty($r['error'])) {
    http_response_code(502);
    echo json_encode(['error' => $r['error']]);
    exit;
}
http_response_code($r['httpCode']);
echo json_encode($r['data']);
