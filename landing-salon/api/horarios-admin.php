<?php
require_once __DIR__ . '/../env-loader.php';
session_start();
if (!($_SESSION['tetoca_admin'] ?? false)) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $r = schedulerApiCall('/providers/5');
        $prov = $r['data'] ?? [];
        echo json_encode([
            'workingPlan' => $prov['settings']['workingPlan'] ?? [],
            'timezone' => $prov['timezone'] ?? 'UTC',
        ]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        if (!$data || !isset($data['workingPlan'])) {
            http_response_code(400);
            echo json_encode(['error' => 'workingPlan requerido']);
            exit;
        }
        $current = schedulerApiCall('/providers/5')['data'] ?? [];
        $r = schedulerApiCall('/providers/5', 'PUT', [
            'settings' => [
                'workingPlan' => $data['workingPlan'],
                'username' => $current['settings']['username'] ?? 'laura',
                'notifications' => $current['settings']['notifications'] ?? false,
                'calendarView' => $current['settings']['calendarView'] ?? 'default',
            ],
        ]);
        if ($r['httpCode'] !== 200) {
            http_response_code(500);
            echo json_encode(['error' => 'Error al guardar horarios. Intentá de nuevo.']);
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Horarios guardados']);
        break;

    default:
        http_response_code(405);
        echo json_encode(['error' => 'Método no permitido']);
}
