<?php
require_once __DIR__ . '/../env-loader.php';
session_start();
if (!($_SESSION['tetoca_admin'] ?? false)) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

$customers = schedulerApiCall('/customers')['data'] ?? [];
if (!is_array($customers) || isset($customers['error'])) {
    echo json_encode([]);
    exit;
}

// Count appointments per customer
$appts = schedulerApiCall('/appointments')['data'] ?? [];
$counts = [];
if (is_array($appts)) {
    foreach ($appts as $a) {
        $cid = $a['customerId'] ?? null;
        if ($cid) {
            $counts[$cid] = ($counts[$cid] ?? 0) + 1;
        }
    }
}

// Merge counts into customers
$result = array_map(function($c) use ($counts) {
    $c['appointmentCount'] = $counts[$c['id'] ?? 0] ?? 0;
    return $c;
}, $customers);

echo json_encode($result);
