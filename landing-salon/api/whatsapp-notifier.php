<?php
// Temporary replacement for n8n WF1 while n8n has internal database issues
// Run via cron: * * * * * php /var/www/html/tetoca/api/whatsapp-notifier.php

require_once __DIR__ . '/../env-loader.php';
header('Content-Type: application/json');

$stateFile = __DIR__ . '/../notifier-state.json';
$lastId = 0;
if (file_exists($stateFile)) {
    $state = json_decode(file_get_contents($stateFile), true);
    $lastId = $state['lastProcessedId'] ?? 0;
}

$key = $_ENV['SCHEDULER_API_KEY'] ?? $_ENV['EA_API_PASS'] ?? '';
$scheduler = schedulerApiUrl();

$ch = curl_init($scheduler . '/appointments?sort=-id&length=1&with=customer,service');
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['X-API-Key: ' . $key], CURLOPT_TIMEOUT => 5]);
$res = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($code !== 200) exit;

$appts = json_decode($res, true);
if (!$appts || !isset($appts[0])) exit;

$appt = $appts[0];
if ($appt['id'] <= $lastId) exit;

// New appointment - send WhatsApp
$customer = $appt['customer'] ?? [];
$service = $appt['service'] ?? [];
$phone = $customer['phone_number'] ?? '';

if (!$phone) exit;

$provider = $appt['provider'] ?? [];
$providerName = ($provider['first_name'] ?? '') . ' ' . ($provider['last_name'] ?? '');
$clientName = ($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '');
$svcName = $service['name'] ?? 'Servicio';
$date = explode(' ', $appt['start'] ?? '')[0] ?? '';
$time = substr(explode(' ', $appt['start'] ?? '')[1] ?? '', 0, 5);

$message = "✅ ¡Tu turno está confirmado, " . trim($clientName) . "!\n\n📆 $date a las $time\n💇 Servicio: $svcName\n👩 Profesional: " . trim($providerName) . "\n📍 Mitre 456, Chamical\n\nPara cancelar, respondé CANCELAR a este mensaje.";

$sendUrl = 'http://localhost/tetoca/api/whatsapp-send.php?chatId=' . urlencode($phone . '@c.us') . '&text=' . urlencode($message);
$ch = curl_init($sendUrl);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$sendRes = curl_exec($ch);
curl_close($ch);

// Save state
file_put_contents($stateFile, json_encode(['lastProcessedId' => $appt['id'], 'lastProcessedAt' => date('Y-m-d H:i:s')]));
echo "Sent: " . $appt['id'] . "\n";
