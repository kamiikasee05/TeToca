<?php
// Accepts GET with ?chatId=X&text=Y
// Forwards to OpenWA
header('Content-Type: application/json');

$chatId = $_GET['chatId'] ?? '';
$text = $_GET['text'] ?? '';

if (empty($chatId) || empty($text)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing chatId or text']);
    exit;
}

// Normalize Argentine phone numbers to WhatsApp format (549 + area + number)
$parts = explode('@', $chatId, 2);
$phone = preg_replace('/\D/', '', $parts[0]);
// If already in 549 format, keep it
if (preg_match('/^549/', $phone)) {
    $phone = $phone;
} else {
    $phone = preg_replace('/^54/', '', $phone);
    $phone = preg_replace('/^0/', '', $phone);
    $phone = preg_replace('/^(\d{2,4})15/', '$1', $phone);
    $phone = '549' . $phone;
}
$suffix = $parts[1] ?? 'c.us';
$chatId = $phone . '@' . $suffix;

$sessionId = $_ENV['OPENWA_SESSION_ID'];
if (!$sessionId) { http_response_code(500); echo json_encode(['error' => 'OPENWA_SESSION_ID no configurado']); exit; }
$openwa_url = "http://openwa:2785/api/sessions/{$sessionId}/messages/send-text";

$ch = curl_init($openwa_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        'chatId' => $chatId,
        'text' => $text
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-API-Key: ' . ($_ENV['OPENWA_API_KEY'] ?? '')
    ],
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    http_response_code(502);
    echo json_encode(['error' => 'OpenWA relay failed', 'detail' => $error]);
    exit;
}

http_response_code($httpCode);
echo $response;
