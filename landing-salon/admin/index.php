<?php
ob_start();
session_start();

$configFile = __DIR__ . '/../config.json';
if (!file_exists($configFile)) {
    $config = [
        'password' => 'admin2024',
        'brand' => ['name' => 'Nails by Laura', 'tagline' => '', 'address' => '', 'whatsapp' => '', 'instagram' => ''],
        'colors' => ['primary' => '#E8A0A0', 'secondary' => '#F5F0F0', 'accent' => '#B56576', 'text' => '#2D2D2D', 'background' => '#FFFFFF'],
        'logo' => 'uploads/logo.png',
        'gallery' => [],
    ];
    file_put_contents($configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}
$config = json_decode(file_get_contents($configFile), true) ?: [];

function saveConfig(array $data): bool {
    global $configFile;
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($json === false) return false;
    if (!is_writable($configFile) && !is_writable(dirname($configFile))) return false;
    file_put_contents($configFile, $json);
    return true;
}

function jsonResponse(array $data, int $code = 200): void {
    if (ob_get_level()) ob_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/';
$galleryDir = $uploadDir . 'gallery/';

if (!is_dir($galleryDir)) { mkdir($galleryDir, 0755, true); }
if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

$authPassword = $config['password'] ?? 'admin2024';
$isLoggedIn = ($_SESSION['tuahora_admin'] ?? false) === true;

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Rate limiting
$attemptsFile = sys_get_temp_dir() . '/admin_login_attempts.json';
$attempts = [];
if (file_exists($attemptsFile)) {
    $attempts = json_decode(file_get_contents($attemptsFile), true) ?: [];
}
$ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
$now = time();
$window = 900;
$maxAttempts = 5;
if (!isset($attempts[$ip])) { $attempts[$ip] = []; }
$attempts[$ip] = array_values(array_filter($attempts[$ip], fn($t) => $now - $t < $window));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    if (count($attempts[$ip]) >= $maxAttempts) {
        $loginError = 'Demasiados intentos. Espera 15 minutos.';
    } else {
        $inputPass = $_POST['password'] ?? '';
        $storedHash = $_ENV['ADMIN_PASSWORD_HASH'] ?? '';
        $passwordValid = false;
        if ($storedHash && $storedHash !== 'CAMBIAR_HASH_BCRYPT') {
            $passwordValid = password_verify($inputPass, $storedHash);
        } else {
            $passwordValid = ($inputPass === $authPassword);
        }
        if ($passwordValid) {
            $attempts[$ip] = [];
            file_put_contents($attemptsFile, json_encode($attempts));
            $_SESSION['tuahora_admin'] = true;
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            header('Location: index.php');
            exit;
        }
        $attempts[$ip][] = $now;
        file_put_contents($attemptsFile, json_encode($attempts));
        $loginError = 'Contraseña incorrecta';
    }
}

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] !== 'login') {
    if (!isset($_POST['csrf_token']) || ($_POST['csrf_token'] ?? '') !== ($_SESSION['csrf_token'] ?? '')) {
        jsonResponse(['error' => 'Token CSRF invalido'], 403);
    }
    $action = $_POST['action'];

    if ($action === 'save_brand') {
        $brand = $config['brand'] ?? [];
        $brand['name'] = trim($_POST['name'] ?? '');
        $brand['tagline'] = trim($_POST['tagline'] ?? '');
        $brand['address'] = trim($_POST['address'] ?? '');
        $brand['whatsapp'] = trim($_POST['whatsapp'] ?? '');
        $brand['instagram'] = trim($_POST['instagram'] ?? '');
        $config['brand'] = $brand;
        if (!saveConfig($config)) {
            jsonResponse(['error' => 'No se puede guardar: permisos de escritura en config.json'], 500);
        }
        jsonResponse(['success' => true, 'brand' => $brand]);
    }

    if ($action === 'save_colors') {
        $colors = $config['colors'] ?? [];
        $colors['primary'] = $_POST['primary'] ?? '';
        $colors['secondary'] = $_POST['secondary'] ?? '';
        $colors['accent'] = $_POST['accent'] ?? '';
        $colors['text'] = $_POST['text'] ?? '';
        $colors['background'] = $_POST['background'] ?? '';
        $config['colors'] = $colors;
        if (!saveConfig($config)) {
            jsonResponse(['error' => 'No se puede guardar: permisos de escritura en config.json'], 500);
        }
        jsonResponse(['success' => true, 'colors' => $colors]);
    }

    if ($action === 'upload_logo') {
        if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'Error al subir el archivo'], 400);
        }
        $file = $_FILES['logo'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'png') { jsonResponse(['error' => 'Solo se permiten archivos PNG'], 400); }
        if ($file['size'] > 2 * 1024 * 1024) { jsonResponse(['error' => 'Máximo 2MB'], 400); }

        $tmpPath = $file['tmp_name'];
        $imgInfo = getimagesize($tmpPath);
        if (!$imgInfo || $imgInfo[2] !== IMAGETYPE_PNG) { jsonResponse(['error' => 'Solo imágenes PNG válidas'], 400); }

        $src = imagecreatefrompng($tmpPath);
        if (!$src) { jsonResponse(['error' => 'No se pudo procesar la imagen'], 400); }

        $origW = imagesx($src);
        $origH = imagesy($src);
        $maxW = 400;
        if ($origW > $maxW) {
            $newW = $maxW;
            $newH = (int)($origH * ($maxW / $origW));
            $dst = imagecreatetruecolor($newW, $newH);
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
            imagedestroy($src);
            $src = $dst;
        }

        $destPath = $uploadDir . 'logo.png';
        imagepng($src, $destPath, 8);
        imagedestroy($src);

        $config['logo'] = 'uploads/logo.png';
        if (!saveConfig($config)) {
            jsonResponse(['error' => 'No se puede guardar: permisos de escritura en config.json'], 500);
        }
        jsonResponse(['success' => true, 'logo' => 'uploads/logo.png?' . time()]);
    }

    if ($action === 'delete_logo') {
        $logoFile = $uploadDir . 'logo.png';
        if (file_exists($logoFile)) { unlink($logoFile); }
        $config['logo'] = '';
        if (!saveConfig($config)) {
            jsonResponse(['error' => 'No se puede guardar: permisos de escritura en config.json'], 500);
        }
        jsonResponse(['success' => true]);
    }

    if ($action === 'upload_gallery') {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'Error al subir la imagen'], 400);
        }
        $file = $_FILES['image'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png'])) { jsonResponse(['error' => 'Solo JPG y PNG'], 400); }
        if ($file['size'] > 5 * 1024 * 1024) { jsonResponse(['error' => 'Máximo 5MB'], 400); }

        $gallery = $config['gallery'] ?? [];
        if (count($gallery) >= 10) { jsonResponse(['error' => 'Máximo 10 imágenes'], 400); }

        $uniqueName = uniqid('img_', true) . '.' . $ext;
        $destPath = $galleryDir . $uniqueName;

        if ($ext === 'png') {
            $src = imagecreatefrompng($file['tmp_name']);
            if (!$src) { jsonResponse(['error' => 'No se pudo procesar la imagen'], 400); }
            imagepng($src, $destPath, 8);
            imagedestroy($src);
        } else {
            $src = imagecreatefromjpeg($file['tmp_name']);
            if (!$src) { jsonResponse(['error' => 'No se pudo procesar la imagen'], 400); }
            imagejpeg($src, $destPath, 85);
            imagedestroy($src);
        }

        $gallery[] = ['filename' => $uniqueName];
        $config['gallery'] = $gallery;
        if (!saveConfig($config)) {
            jsonResponse(['error' => 'No se puede guardar: permisos de escritura en config.json'], 500);
        }
        jsonResponse(['success' => true, 'gallery' => $gallery]);
    }

    if ($action === 'delete_gallery') {
        $filename = $_POST['filename'] ?? '';
        if (!$filename) { jsonResponse(['error' => 'Falta filename'], 400); }
        $gallery = $config['gallery'] ?? [];
        $found = false;
        $newGallery = [];
        foreach ($gallery as $img) {
            if (($img['filename'] ?? '') === $filename) {
                $found = true;
                $filePath = $galleryDir . $filename;
                if (file_exists($filePath)) { unlink($filePath); }
            } else {
                $newGallery[] = $img;
            }
        }
        if (!$found) { jsonResponse(['error' => 'Imagen no encontrada'], 404); }
        $config['gallery'] = $newGallery;
        if (!saveConfig($config)) {
            jsonResponse(['error' => 'No se puede guardar: permisos de escritura en config.json'], 500);
        }
        jsonResponse(['success' => true, 'gallery' => $newGallery]);
    }

    jsonResponse(['error' => 'Acción desconocida'], 400);
}

$brand = $config['brand'] ?? [];
$colors = $config['colors'] ?? [];
$gallery = $config['gallery'] ?? [];
$logo = $config['logo'] ?? '';
$logoUrl = ($logo && file_exists(__DIR__ . '/../' . $logo)) ? '../' . $logo . '?' . filemtime(__DIR__ . '/../' . $logo) : '';
$waNumber = $brand['whatsapp'] ?? '5493826403110';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>TuAhora — Panel</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz@14..32&display=swap" rel="stylesheet">
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body { font-family:'Inter',sans-serif; background:#fdf6f0; color:#333; }
a { color:#b76e79; }

/* Login */
.login-wrapper { display:flex; align-items:center; justify-content:center; min-height:100vh; padding:24px; }
.login-card { background:#fff; border-radius:20px; padding:40px; width:100%; max-width:380px; box-shadow:0 8px 32px rgba(0,0,0,.08); text-align:center; }
.login-card h1 { color:#b76e79; font-size:24px; margin-bottom:4px; }
.login-card .subtitle { color:#999; font-size:14px; margin-bottom:24px; }
.login-card label { display:block; text-align:left; font-size:13px; color:#666; margin-bottom:6px; }
.login-card input { width:100%; padding:12px 16px; border:1.5px solid #e8ddd6; border-radius:12px; font-size:15px; outline:none; transition:.2s; font-family:inherit; }
.login-card input:focus { border-color:#b76e79; box-shadow:0 0 0 3px rgba(183,110,121,.15); }
.login-card button { width:100%; padding:12px; background:#b76e79; color:#fff; border:none; border-radius:12px; font-size:15px; cursor:pointer; transition:.2s; margin-top:8px; font-family:inherit; }
.login-card button:hover { filter:brightness(.9); }
.login-card .error { color:#e74c3c; font-size:13px; margin-top:12px; }

/* Dashboard header */
header { background:#fff; border-bottom:1px solid #e8ddd6; padding:16px 24px; display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; z-index:100; }
header h1 { font-size:20px; color:#b76e79; }
header a { color:#999; text-decoration:none; font-size:14px; transition:.2s; }
header a:hover { color:#b76e79; }

/* Tabs */
.tabs { display:flex; gap:2px; background:#fff; border-bottom:1px solid #e8ddd6; padding:0 24px; overflow-x:auto; position:sticky; top:57px; z-index:99; }
.tab-btn { padding:12px 20px; font-size:14px; cursor:pointer; border:none; background:transparent; color:#888; font-family:inherit; white-space:nowrap; transition:.2s; border-bottom:2px solid transparent; }
.tab-btn:hover { color:#b76e79; background:#fdf6f0; }
.tab-btn.active { color:#b76e79; border-bottom-color:#b76e79; font-weight:600; }
.tab-content { display:none; }
.tab-content.active { display:block; }
.container { max-width:1000px; margin:0 auto; padding:24px; }

/* Stats cards */
.stats-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:16px; margin-bottom:24px; }
.stat-card { background:#fff; border-radius:16px; padding:20px; box-shadow:0 2px 8px rgba(0,0,0,.04); text-align:center; }
.stat-card .num { font-size:32px; font-weight:700; color:#b76e79; }
.stat-card .label { font-size:13px; color:#999; margin-top:4px; }

/* Cards */
.card { background:#fff; border-radius:16px; padding:24px; margin-bottom:24px; box-shadow:0 4px 16px rgba(0,0,0,.06); }
.card h2 { font-size:18px; margin-bottom:16px; color:#555; }
.card .desc { font-size:13px; color:#999; margin-bottom:16px; }

/* Table */
table { width:100%; border-collapse:collapse; }
th,td { text-align:left; padding:12px 8px; border-bottom:1px solid #f0ebe7; font-size:14px; }
th { color:#999; font-weight:500; text-transform:uppercase; font-size:12px; letter-spacing:.5px; }

/* Buttons */
.btn { display:inline-flex; align-items:center; gap:6px; padding:8px 16px; border-radius:10px; font-size:13px; cursor:pointer; border:none; text-decoration:none; transition:.2s; font-family:inherit; }
.btn-primary { background:#b76e79; color:#fff; }
.btn-primary:hover { background:#a05f69; }
.btn-ghost { background:transparent; color:#666; border:1.5px solid #e8ddd6; }
.btn-ghost:hover { border-color:#b76e79; color:#b76e79; }
.btn-danger { background:#fef2f2; color:#e74c3c; }
.btn-danger:hover { background:#fee2e2; }
.btn-sm { padding:5px 10px; font-size:12px; }
.btn-xs { padding:3px 8px; font-size:11px; border-radius:6px; }
.actions { display:flex; gap:6px; flex-wrap:wrap; }

/* Forms */
.form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:16px; }
.form-row.three { grid-template-columns:1fr 1fr 1fr; }
label { display:block; font-size:13px; color:#666; margin-bottom:4px; }
input,textarea,select { width:100%; padding:10px 12px; border:1.5px solid #e8ddd6; border-radius:10px; font-size:14px; outline:none; font-family:inherit; transition:.2s; }
input:focus,textarea:focus { border-color:#b76e79; box-shadow:0 0 0 3px rgba(183,110,121,.15); }
textarea { resize:vertical; min-height:60px; }
.form-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:8px; }
.hidden { display:none !important; }

/* Toast */
.toast { position:fixed; bottom:24px; right:24px; background:#333; color:#fff; padding:12px 20px; border-radius:12px; font-size:14px; opacity:0; transition:.3s; z-index:9999; pointer-events:none; }
.toast.show { opacity:1; }

/* Servicios styles */
.precio { font-weight:600; color:#b76e79; }
.duracion { color:#888; }

/* Horarios styles */
.break-row { display:flex; gap:4px; align-items:center; margin-bottom:4px; }
.break-row input { width:80px; padding:6px 8px; font-size:12px; }
.breaks-container input:disabled { opacity:.4; }
#form-horarios td { vertical-align:top; }
#form-horarios input[type="checkbox"] { width:20px; height:20px; cursor:pointer; }
#form-horarios input[type="time"] { width:110px; padding:6px 8px; font-size:13px; }
.wa-loading { color:#999; font-size:14px; padding:32px; }
.wa-error { color:#e74c3c; font-size:14px; padding:32px; }
.wa-retry { color:#999; font-size:12px; margin-top:8px; }

/* Calendar */
.cal-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; }
.cal-header h3 { font-size:18px; color:#555; }
.cal-nav { display:flex; gap:8px; }
.cal-nav button { background:#fff; border:1.5px solid #e8ddd6; border-radius:10px; padding:8px 14px; cursor:pointer; font-size:14px; color:#555; transition:.2s; font-family:inherit; }
.cal-nav button:hover { border-color:#b76e79; color:#b76e79; }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:3px; background:#f0ebe7; border-radius:12px; overflow:hidden; }
.cal-cell { background:#fff; min-height:100px; padding:4px; font-size:12px; cursor:pointer; transition:.2s; overflow:hidden; }
.cal-cell:hover { background:#fdf6f0; }
.cal-cell.other-month { background:#faf8f6; color:#ccc; }
.cal-cell.today { background:#f5e1e4; }
.cal-cell .day-num { font-weight:600; font-size:13px; padding:2px 4px; color:#666; }
.cal-cell.today .day-num { color:#b76e79; }
.cal-cell .cal-appt { padding:2px 4px; margin:1px 0; border-radius:4px; font-size:11px; cursor:pointer; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:#fff; transition:.15s; }
.cal-cell .cal-appt:hover { opacity:.8; transform:scale(1.02); }
.cal-cell .cal-appt.-confirmed { background:#b76e79; }
.cal-cell .cal-appt.cancelled { background:#e8ddd6; color:#999; text-decoration:line-through; }
.cal-cell .cal-appt .cal-time { font-weight:500; }
.cal-weekday { background:#fff; padding:8px 4px; text-align:center; font-size:12px; font-weight:600; color:#999; text-transform:uppercase; letter-spacing:.5px; border-bottom:1px solid #f0ebe7; }

/* Modal */
.modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,.35); display:none; align-items:center; justify-content:center; z-index:9998; padding:24px; backdrop-filter:blur(2px); }
.modal-overlay.show { display:flex; }
.modal { background:#fff; border-radius:20px; padding:32px; width:100%; max-width:460px; max-height:90vh; overflow-y:auto; box-shadow:0 16px 48px rgba(0,0,0,.15); position:relative; animation:modalIn .25s ease; }
@keyframes modalIn { from{opacity:0;transform:scale(.95) translateY(10px)} to{opacity:1;transform:scale(1) translateY(0)} }
.modal-close { position:absolute; top:16px; right:16px; background:transparent; border:none; font-size:22px; cursor:pointer; color:#999; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:.2s; }
.modal-close:hover { background:#f0ebe7; color:#333; }
.modal h2 { font-size:20px; color:#333; margin-bottom:20px; }
.modal h3 { font-size:15px; color:#555; margin-bottom:8px; }
.modal .detail-row { display:flex; gap:8px; margin-bottom:10px; font-size:14px; }
.modal .detail-row .icon { width:20px; color:#b76e79; flex-shrink:0; }
.modal .detail-row .val { color:#333; }
.modal .status-badge { display:inline-block; padding:3px 10px; border-radius:20px; font-size:12px; font-weight:500; }
.status-badge.confirmed { background:#f5e1e4; color:#b76e79; }
.status-badge.cancelled { background:#f0ebe7; color:#999; }
.modal-actions { display:flex; gap:8px; margin-top:20px; flex-wrap:wrap; }

/* Reschedule */
.reschedule-section { margin-top:16px; padding-top:16px; border-top:1px solid #f0ebe7; }
.reschedule-section h3 { margin-bottom:12px; }
.slot-options { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.slot-btn { padding:8px 16px; border:1.5px solid #e8ddd6; border-radius:10px; background:#fff; cursor:pointer; font-size:13px; font-family:inherit; transition:.2s; color:#555; }
.slot-btn:hover { border-color:#b76e79; color:#b76e79; }
.slot-btn.selected { background:#b76e79; color:#fff; border-color:#b76e79; }

/* Turnos list */
.search-bar { display:flex; gap:8px; margin-bottom:16px; flex-wrap:wrap; }
.search-bar input { flex:1; min-width:180px; }
.search-bar select { width:auto; min-width:140px; }
.turno-row td { vertical-align:middle; }
.turno-row .cliente { font-weight:500; }
.turno-row .servicio-info { font-size:13px; color:#666; }
.empty-state { text-align:center; padding:48px 24px; color:#bbb; }
.empty-state .icon { font-size:48px; margin-bottom:8px; }

/* ===== Personalization styles ===== */

.pf-row { margin-bottom:16px; }
.pf-row label { display:block; font-size:13px; color:#666; margin-bottom:4px; }
.pf-row input[type="text"],
.pf-row input[type="url"] { width:100%; padding:10px 12px; border:1.5px solid #e8ddd6; border-radius:10px; font-size:14px; outline:none; font-family:inherit; transition:.2s; }
.pf-row input:focus { border-color:#b76e79; box-shadow:0 0 0 3px rgba(183,110,121,.15); }

.color-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:16px; }
.color-item { display:flex; flex-direction:column; align-items:center; gap:6px; }
.color-item label { font-size:12px; color:#888; }
.color-item input[type="color"] { width:64px; height:44px; border:2px solid #e8ddd6; border-radius:8px; padding:2px; cursor:pointer; }

.preview-card { border-radius:16px; overflow:hidden; border:1px solid #eee; margin-top:16px; transition:.2s; }
.preview-header { padding:14px 20px; display:flex; align-items:center; justify-content:space-between; color:#fff; font-weight:600; font-size:15px; }
.preview-hero { padding:40px 20px; text-align:center; }
.preview-hero h3 { font-size:22px; margin-bottom:6px; }
.preview-hero p { font-size:14px; opacity:.8; }
.preview-btn { display:inline-block; margin-top:12px; padding:10px 24px; border-radius:24px; color:#fff; font-weight:500; font-size:14px; }

.logo-preview { max-width:200px; max-height:80px; margin-bottom:12px; border-radius:8px; }
.logo-preview-placeholder { width:200px; height:60px; background:#f0ebe7; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#bbb; font-size:13px; margin-bottom:12px; }

.gallery-grid-pf { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
.gallery-thumb { position:relative; aspect-ratio:1; border-radius:12px; overflow:hidden; background:#f0ebe7; }
.gallery-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
.gallery-thumb .delete-btn { position:absolute; top:6px; right:6px; width:26px; height:26px; border-radius:50%; background:rgba(231,76,60,.85); color:#fff; border:none; cursor:pointer; font-size:14px; display:flex; align-items:center; justify-content:center; transition:.15s; }
.gallery-thumb .delete-btn:hover { background:#e74c3c; }
.gallery-empty { grid-column:1/-1; text-align:center; padding:40px; color:#bbb; font-size:14px; }

.upload-zone { border:2px dashed #e8ddd6; border-radius:12px; padding:32px; text-align:center; cursor:pointer; transition:.2s; }
.upload-zone:hover { border-color:#b76e79; background:#fdf6f0; }
.upload-zone input[type="file"] { display:none; }

.qr-card { text-align:center; padding:24px; }
.qr-card img { width:300px; height:300px; border-radius:16px; box-shadow:0 4px 24px rgba(0,0,0,.08); margin-bottom:20px; }
.qr-card .qr-info { font-size:14px; color:#888; max-width:400px; margin:0 auto 20px; line-height:1.6; }

/* Responsive */
@media(max-width:700px) {
  .stats-grid { grid-template-columns:1fr 1fr; }
  .form-row,.form-row.three { grid-template-columns:1fr; }
  .tabs { padding:0 8px; }
  .tab-btn { padding:10px 14px; font-size:13px; }
  .container { padding:16px; }
  .modal { padding:24px; }
  .cal-grid { gap:2px; }
  .cal-cell { min-height:70px; font-size:11px; }
  .cal-cell .cal-appt { font-size:10px; padding:1px 2px; }
  .color-grid { grid-template-columns:repeat(2,1fr); }
  .qr-card img { width:200px; height:200px; }
}
</style>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<div class="login-wrapper">
    <div class="login-card">
        <h1>TuAhora</h1>
        <p class="subtitle">Panel de administración</p>
        <form method="post">
            <input type="hidden" name="action" value="login">
            <label for="pass">Contraseña</label>
            <input type="password" id="pass" name="password" placeholder="••••••••" autofocus>
            <button type="submit">Ingresar</button>
            <?php if (isset($loginError)): ?><div class="error"><?=htmlspecialchars($loginError)?></div><?php endif; ?>
        </form>
    </div>
</div>

<?php else: ?>
<header>
    <h1 id="header-title">TuAhora · Dashboard</h1>
    <a href="logout.php">Cerrar sesión</a>
</header>

<div class="tabs" id="tabNav">
    <button class="tab-btn active" data-tab="dashboard">Dashboard</button>
    <button class="tab-btn" data-tab="servicios">Servicios</button>
    <button class="tab-btn" data-tab="horarios">Horarios</button>
    <button class="tab-btn" data-tab="calendario">Calendario</button>
    <button class="tab-btn" data-tab="turnos">Turnos</button>
    <button class="tab-btn" data-tab="whatsapp">WhatsApp</button>
    <button class="tab-btn" data-tab="marca">Marca</button>
    <button class="tab-btn" data-tab="logo">Logo</button>
    <button class="tab-btn" data-tab="colores">Colores</button>
    <button class="tab-btn" data-tab="galeria">Galería</button>
</div>

<div class="container">

<!-- TAB: Dashboard -->
<div id="tab-dashboard" class="tab-content active">
    <div class="stats-grid" id="statsGrid">
        <div class="stat-card"><div class="num" id="statHoy">-</div><div class="label">Turnos hoy</div></div>
        <div class="stat-card"><div class="num" id="statSemana">-</div><div class="label">Turnos esta semana</div></div>
        <div class="stat-card"><div class="num" id="statMes">-</div><div class="label">Turnos este mes</div></div>
        <div class="stat-card"><div class="num" id="statIngresos">-</div><div class="label">Ingresos del mes</div></div>
    </div>
    <div class="card">
        <h2>Próximos turnos (7 días)</h2>
        <div id="proximos-container"><div class="empty-state">Cargando...</div></div>
    </div>
</div>

<!-- TAB: Servicios -->
<div id="tab-servicios" class="tab-content">
    <div class="card">
        <h2>Agregar servicio</h2>
        <form id="form-servicio">
            <div class="form-row three">
                <div><label>Nombre</label><input name="name" required></div>
                <div><label>Precio (ARS)</label><input name="price" type="number" min="0" step="1" required></div>
                <div><label>Duración (min)</label><input name="duration" type="number" min="5" step="5" required></div>
            </div>
            <div class="form-row"><div><label>Descripción</label><textarea name="description"></textarea></div></div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost hidden" id="btn-cancelar" onclick="cancelarEdicion()">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar servicio</button>
            </div>
        </form>
    </div>
    <div class="card">
        <h2>Servicios actuales</h2>
        <div id="loading">Cargando...</div>
        <table id="tabla-servicios" class="hidden">
            <thead><tr><th>Nombre</th><th>Descripción</th><th>Duración</th><th>Precio</th><th></th></tr></thead>
            <tbody id="tbody-servicios"></tbody>
        </table>
        <div id="empty" class="hidden" style="text-align:center;padding:32px;color:#999;">No hay servicios todavía</div>
    </div>
</div>

<!-- TAB: Horarios -->
<div id="tab-horarios" class="tab-content">
    <div class="card">
        <h2>Mis horarios</h2>
        <p style="color:#999;font-size:13px;margin-bottom:16px;">Configurá tu disponibilidad semanal. Los días desactivados no mostrarán horarios.</p>
        <div id="wp-loading" style="text-align:center;padding:24px;color:#999;">Cargando horarios...</div>
        <form id="form-horarios" class="hidden">
            <table><thead><tr><th>Día</th><th>Activo</th><th>Desde</th><th>Hasta</th><th>Descanso</th></tr></thead>
                <tbody id="wp-tbody"></tbody>
            </table>
            <div class="form-actions" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary" id="btn-guardar-horarios">Guardar horarios</button>
            </div>
        </form>
    </div>
</div>

<!-- TAB: Calendario -->
<div id="tab-calendario" class="tab-content">
    <div class="card">
        <div class="cal-header">
            <h3 id="calTitle">Mes</h3>
            <div class="cal-nav">
                <button onclick="navegarCal(-1)">←</button>
                <button onclick="irHoy()">Hoy</button>
                <button onclick="navegarCal(1)">→</button>
            </div>
        </div>
        <div id="calContainer" style="text-align:center;padding:32px;color:#999;">Cargando calendario...</div>
    </div>
    <div style="display:flex;gap:16px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
        <span style="font-size:13px;color:#999;">Referencia:</span>
        <span style="display:flex;align-items:center;gap:4px;font-size:12px;"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#b76e79;"></span> Confirmado</span>
        <span style="display:flex;align-items:center;gap:4px;font-size:12px;"><span style="display:inline-block;width:12px;height:12px;border-radius:3px;background:#e8ddd6;"></span> Cancelado</span>
    </div>
</div>

<!-- TAB: Turnos -->
<div id="tab-turnos" class="tab-content">
    <div class="card">
        <h2>Gestión de turnos</h2>
        <div class="search-bar">
            <input type="text" id="searchTurno" placeholder="Buscar por cliente..." oninput="filtrarTurnos()">
            <select id="filtroEstado" onchange="filtrarTurnos()">
                <option value="">Todos los estados</option>
                <option value="confirmed">Confirmados</option>
                <option value="cancelled">Cancelados</option>
            </select>
        </div>
        <div id="turnosContainer"><div class="empty-state">Cargando turnos...</div></div>
    </div>
</div>

<!-- TAB: WhatsApp -->
<div id="tab-whatsapp" class="tab-content">
    <div class="card" style="text-align:center;max-width:480px;margin:0 auto;">
        <h2 style="margin-bottom:4px;">📱 Conexión WhatsApp</h2>
        <p style="color:#999;font-size:13px;margin-bottom:24px;">Escaneá el código QR con WhatsApp Business para recibir y responder mensajes automáticamente.</p>
        <div id="whatsapp-status">
            <div class="wa-loading">⏳ Conectando...</div>
        </div>
        <div id="whatsapp-qr-container" style="display:none;">
            <img id="whatsapp-qr-img" src="" alt="QR WhatsApp" style="max-width:300px;width:100%;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.08);">
            <p style="color:#888;font-size:13px;margin-top:12px;">📱 Abrí WhatsApp en tu celu → Dispositivos vinculados → Vincular</p>
        </div>
        <div id="whatsapp-connected" style="display:none;">
            <div style="font-size:64px;margin-bottom:12px;">✅</div>
            <h3 style="color:#4caf50;">WhatsApp conectado</h3>
            <p style="color:#888;font-size:14px;">Los mensajes de confirmación y recordatorios se enviarán automáticamente.</p>
        </div>
    </div>
</div>

<!-- TAB: Marca -->
<div id="tab-marca" class="tab-content">
    <div class="card">
        <h2>Información de la marca</h2>
        <p class="desc">Estos datos se muestran en la landing page.</p>
        <form id="formMarca">
            <div class="pf-row">
                <label>Nombre del salón</label>
                <input type="text" name="name" value="<?=htmlspecialchars($brand['name'] ?? '')?>" required>
            </div>
            <div class="pf-row">
                <label>Eslogan / Tagline</label>
                <input type="text" name="tagline" value="<?=htmlspecialchars($brand['tagline'] ?? '')?>">
            </div>
            <div class="pf-row">
                <label>Dirección</label>
                <input type="text" name="address" value="<?=htmlspecialchars($brand['address'] ?? '')?>">
            </div>
            <div class="pf-row">
                <label>Número de WhatsApp (código país + número, sin +)</label>
                <input type="text" name="whatsapp" value="<?=htmlspecialchars($brand['whatsapp'] ?? '')?>">
            </div>
            <div class="pf-row">
                <label>Usuario de Instagram (con @)</label>
                <input type="text" name="instagram" value="<?=htmlspecialchars($brand['instagram'] ?? '')?>">
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-primary" onclick="guardarMarca()">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>

<!-- TAB: Logo -->
<div id="tab-logo" class="tab-content">
    <div class="card">
        <h2>Logo del salón</h2>
        <p class="desc">Subí el logo en formato PNG. Tamaño máximo: 2MB. Se redimensiona automáticamente a 400px de ancho.</p>
        <div style="margin-bottom:16px;">
            <?php if ($logoUrl): ?>
                <img src="<?=$logoUrl?>" alt="Logo actual" class="logo-preview" id="logoPreview">
            <?php else: ?>
                <div class="logo-preview-placeholder" id="logoPlaceholder">Sin logo</div>
                <img src="" alt="Logo" class="logo-preview" id="logoPreview" style="display:none;">
            <?php endif; ?>
        </div>
        <div class="form-actions">
            <label class="btn btn-primary" style="cursor:pointer;">
                <?=$logoUrl ? 'Cambiar logo' : 'Subir logo'?>
                <input type="file" accept="image/png" id="logoInput" hidden>
            </label>
            <?php if ($logoUrl): ?>
                <button class="btn btn-danger" id="btnDeleteLogo">Eliminar logo</button>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TAB: Colores -->
<div id="tab-colores" class="tab-content">
    <div class="card">
        <h2>Paleta de colores</h2>
        <p class="desc">Personalizá los colores de la landing page.</p>
        <form id="formColores">
            <div class="color-grid">
                <?php
                $colorFields = [
                    'primary' => 'Principal (botones, links)',
                    'secondary' => 'Secundario (fondos suaves)',
                    'accent' => 'Acento (degradados, detalles)',
                    'text' => 'Texto principal',
                    'background' => 'Fondo de página',
                ];
                foreach ($colorFields as $key => $label):
                    $val = htmlspecialchars($colors[$key] ?? '#000000');
                ?>
                <div class="color-item">
                    <label><?=$label?></label>
                    <input type="color" name="<?=$key?>" value="<?=$val?>">
                </div>
                <?php endforeach; ?>
            </div>
            <div class="form-actions" style="margin-top:16px;">
                <button type="submit" class="btn btn-primary">Guardar colores</button>
            </div>
        </form>

        <div class="preview-card" id="colorPreview" style="margin-top:20px;">
            <div class="preview-header" style="background:<?=htmlspecialchars($colors['primary']??'#E8A0A0')?>;">
                <?=htmlspecialchars($brand['name'] ?? 'Salón')?>
            </div>
            <div class="preview-hero" style="background:<?=htmlspecialchars($colors['secondary']??'#F5F0F0')?>;color:<?=htmlspecialchars($colors['text']??'#2D2D2D')?>;">
                <h3><?=htmlspecialchars($brand['tagline'] ?: 'Tu eslogan acá')?></h3>
                <p>Vista previa del hero con los colores seleccionados</p>
                <span class="preview-btn" style="background:<?=htmlspecialchars($colors['primary']??'#E8A0A0')?>;">Reservar ahora</span>
            </div>
        </div>
    </div>
</div>

<!-- TAB: Galería -->
<div id="tab-galeria" class="tab-content">
    <div class="card">
        <h2>Galería de trabajos</h2>
        <p class="desc">Subí fotos de tus trabajos. JPG o PNG, máximo 5MB cada una. Hasta 10 imágenes.</p>
        <div class="gallery-grid-pf" id="galleryGrid">
            <?php if (empty($gallery)): ?>
                <div class="gallery-empty" id="galleryEmpty">No hay imágenes todavía</div>
            <?php else: ?>
                <?php foreach ($gallery as $img): ?>
                    <?php $fname = htmlspecialchars($img['filename'] ?? ''); ?>
                    <?php if ($fname): ?>
                    <div class="gallery-thumb" data-filename="<?=$fname?>">
                        <img src="../uploads/gallery/<?=$fname?>" alt="" loading="lazy">
                        <button class="delete-btn" title="Eliminar" onclick="eliminarGaleria('<?=$fname?>')">&times;</button>
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div style="margin-top:16px;">
            <label class="upload-zone" id="galleryUploadZone">
                <div>📁 Hacé clic o arrastrá una imagen</div>
                <div style="font-size:12px;color:#999;margin-top:4px;">JPG o PNG · Máx 5MB · <?=count($gallery)?>/10</div>
                <input type="file" accept="image/jpeg,image/png" id="galleryInput" hidden>
            </label>
        </div>
    </div>
</div>

</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay">
    <div class="modal">
        <button class="modal-close" onclick="cerrarModal()">✕</button>
        <div id="modalBody"></div>
    </div>
</div>

<div id="toast" class="toast"></div>

<script>
const CSRF_TOKEN = '<?= $_SESSION['csrf_token'] ?? '' ?>';

// ===== ORIGINAL DASHBOARD APIs =====
const API = '../api/admin-servicios.php';
const WP_API = '../api/horarios-admin.php';
const TURNOS_API = '../api/turnos-admin.php';

const DAYS = [
    { key:'monday', label:'Lunes' }, { key:'tuesday', label:'Martes' },
    { key:'wednesday', label:'Miércoles' }, { key:'thursday', label:'Jueves' },
    { key:'friday', label:'Viernes' }, { key:'saturday', label:'Sábado' },
    { key:'sunday', label:'Domingo' },
];
const MONTHS = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
const DAY_LABELS = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];

let allAppointments = [];
let allServices = [];
let calYear, calMonth;
let selectedAppt = null;
let editingService = null;

// ===== TABS =====
document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        this.classList.add('active');
        const tab = this.dataset.tab;
        var panel = document.getElementById('tab-' + tab);
        if (panel) panel.classList.add('active');
        document.getElementById('header-title').textContent = 'TuAhora · ' + this.textContent;
        if (tab === 'dashboard') cargarDashboard();
        if (tab === 'calendario') renderCalendario();
        if (tab === 'turnos') renderTurnos();
        if (tab === 'whatsapp') { cargarWhatsApp(); if (!waPollTimer) waPollTimer = setInterval(cargarWhatsApp, 5000); }
        else if (waPollTimer) { clearInterval(waPollTimer); waPollTimer = null; }
    });
});

// ===== TOAST =====
function mostrarToast(msg) { var t = document.getElementById('toast'); t.textContent = msg; t.classList.add('show'); setTimeout(function() { t.classList.remove('show'); }, 2500); }

// ===== MODAL =====
function abrirModal(html) { document.getElementById('modalBody').innerHTML = html; document.getElementById('modalOverlay').classList.add('show'); }
function cerrarModal() { document.getElementById('modalOverlay').classList.remove('show'); }
document.getElementById('modalOverlay').addEventListener('click', function(e) { if (e.target === e.currentTarget) cerrarModal(); });

// ===== DASHBOARD =====
async function cargarDashboard() {
    try {
        const res = await fetch(TURNOS_API + '?month=' + new Date().getFullYear() + '-' + String(new Date().getMonth()+1).padStart(2,'0'));
        const appts = await res.json();
        const now = new Date();
        const today = now.toISOString().slice(0,10);
        const todayAppts = appts.filter(function(a) { return a.start.slice(0,10) === today; });
        document.getElementById('statHoy').textContent = todayAppts.length;

        const weekStart = new Date(now); weekStart.setDate(now.getDate() - now.getDay() + 1);
        const weekEnd = new Date(weekStart); weekEnd.setDate(weekStart.getDate() + 6);
        const weekAppts = appts.filter(function(a) { var d = a.start.slice(0,10); return d >= weekStart.toISOString().slice(0,10) && d <= weekEnd.toISOString().slice(0,10); });
        document.getElementById('statSemana').textContent = weekAppts.length;

        const monthAppts = appts.filter(function(a) { return a.start.slice(0,7) === today.slice(0,7); });
        document.getElementById('statMes').textContent = monthAppts.length;
        const ingresos = monthAppts.reduce(function(sum, a) { return sum + (a.service ? a.service.price : 0); }, 0);
        document.getElementById('statIngresos').textContent = '$' + ingresos.toLocaleString('es-AR');

        const proximos = appts.filter(function(a) { var d = a.start.slice(0,10); return d >= today && d <= new Date(now.getTime()+7*86400000).toISOString().slice(0,10) && a.status !== 'cancelled'; });
        proximos.sort(function(a,b) { return a.start.localeCompare(b.start); });
        var container = document.getElementById('proximos-container');
        if (!proximos.length) { container.innerHTML = '<div class="empty-state">No hay turnos próximos</div>'; return; }
        var html = '<table><thead><tr><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Servicio</th><th>Precio</th></tr></thead><tbody>';
        proximos.slice(0,10).forEach(function(a) {
            var d = a.start.slice(0,10).split('-').reverse().join('/');
            var h = a.start.slice(11,16);
            var c = a.customer ? a.customer.firstName + ' ' + a.customer.lastName : '—';
            var s = a.service ? a.service.name : '—';
            var p = a.service ? '$' + Number(a.service.price).toLocaleString('es-AR') : '—';
            html += '<tr><td>' + d + '</td><td><strong>' + h + '</strong></td><td>' + c + '</td><td style="color:#888;font-size:13px;">' + s + '</td><td class="precio">' + p + '</td></tr>';
        });
        html += '</tbody></table>';
        container.innerHTML = html;
    } catch(e) { document.getElementById('proximos-container').innerHTML = '<div class="empty-state">Error al cargar datos</div>'; }
}

// ===== SERVICIOS CRUD =====
async function cargarServicios() {
    try {
        const res = await fetch(API); const data = await res.json();
        document.getElementById('loading').classList.add('hidden');
        var tbody = document.getElementById('tbody-servicios'); tbody.innerHTML = '';
        if (!Array.isArray(data) || data.length === 0) {
            document.getElementById('empty').classList.remove('hidden'); document.getElementById('tabla-servicios').classList.add('hidden'); return;
        }
        document.getElementById('empty').classList.add('hidden'); document.getElementById('tabla-servicios').classList.remove('hidden');
        allServices = data;
        data.forEach(function(s) {
            var tr = document.createElement('tr');
            tr.innerHTML = '<td><strong>' + s.name + '</strong></td><td style="color:#888;font-size:13px;">' + (s.description||'-') + '</td><td class="duracion">' + s.duration + ' min</td><td class="precio">$' + Number(s.price).toLocaleString('es-AR') + '</td><td class="actions"><button class="btn btn-ghost btn-sm" onclick="editarServicio(' + s.id + ')">Editar</button> <button class="btn btn-danger btn-sm" onclick="eliminarServicio(' + s.id + ')">Eliminar</button></td>';
            tbody.appendChild(tr);
        });
    } catch(e) {}
}

document.getElementById('form-servicio').addEventListener('submit', async function(e) {
    e.preventDefault();
    var fd = new FormData(e.target); var data = Object.fromEntries(fd);
    data.price = parseFloat(data.price); data.duration = parseInt(data.duration);
    var url = API; var method = 'POST';
    if (editingService) { url += '?id=' + editingService; method = 'PUT'; }
    data.csrf_token = CSRF_TOKEN;
    var res = await fetch(url, { method: method, headers:{'Content-Type':'application/json'}, body:JSON.stringify(data) });
    if (!res.ok) { var err = await res.json(); mostrarToast('Error: ' + (err.error||'desconocido')); return; }
    mostrarToast(editingService ? 'Servicio actualizado' : 'Servicio creado');
    cancelarEdicion(); cargarServicios();
});

async function editarServicio(id) {
    var res = await fetch(API); var servicios = await res.json(); var s = servicios.find(function(x) { return x.id === id; });
    if (!s) return;
    editingService = id;
    var form = document.getElementById('form-servicio');
    form.querySelector('[name="name"]').value = s.name; form.querySelector('[name="price"]').value = s.price;
    form.querySelector('[name="duration"]').value = s.duration; form.querySelector('[name="description"]').value = s.description||'';
    form.querySelector('[type="submit"]').textContent = 'Actualizar servicio';
    document.getElementById('btn-cancelar').classList.remove('hidden');
    form.scrollIntoView({behavior:'smooth'});
}

function cancelarEdicion() {
    editingService = null; document.getElementById('form-servicio').reset();
    document.getElementById('form-servicio').querySelector('[type="submit"]').textContent = 'Guardar servicio';
    document.getElementById('btn-cancelar').classList.add('hidden');
}

async function eliminarServicio(id) {
    if (!confirm('¿Eliminar este servicio?')) return;
    var res = await fetch(API+'?id='+id, {method:'DELETE'});
    if (!res.ok) { var err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Servicio eliminado'); cargarServicios();
}

// ===== HORARIOS =====
async function cargarHorarios() {
    var res = await fetch(WP_API); var data = await res.json();
    document.getElementById('wp-loading').classList.add('hidden'); document.getElementById('form-horarios').classList.remove('hidden');
    var wp = data.workingPlan || {}; var tbody = document.getElementById('wp-tbody'); tbody.innerHTML = '';
    DAYS.forEach(function(d) {
        var day = wp[d.key]; var active = day && day.start && day.end;
        var tr = document.createElement('tr');
        tr.innerHTML = '<td><strong>' + d.label + '</strong></td>' +
            '<td><input type="checkbox" class="day-active" data-day="' + d.key + '" ' + (active?'checked':'') + '></td>' +
            '<td><input type="time" class="day-start" data-day="' + d.key + '" value="' + (active?day.start:'09:00') + '" ' + (active?'':'disabled') + '></td>' +
            '<td><input type="time" class="day-end" data-day="' + d.key + '" value="' + (active?day.end:'18:00') + '" ' + (active?'':'disabled') + '></td>' +
            '<td><div class="breaks-container" data-day="' + d.key + '">' +
                (active&&day.breaks?day.breaks.map(function(b){return '<div class="break-row"><input type="time" class="break-start" value="'+b.start+'" style="width:80px"><input type="time" class="break-end" value="'+b.end+'" style="width:80px"><button type="button" class="btn btn-ghost btn-xs" onclick="this.closest(\'.break-row\').remove()">✕</button></div>';}).join(''):'') +
                '<button type="button" class="btn btn-ghost btn-xs" style="margin-top:4px;" onclick="agregarDescanso(\'' + d.key + '\')">+ Descanso</button>' +
            '</div></td>';
        tbody.appendChild(tr);
    });
    document.querySelectorAll('.day-active').forEach(function(cb) {
        cb.addEventListener('change', function() {
            var day = this.dataset.day; var row = this.closest('tr');
            row.querySelector('.day-start').disabled = !this.checked;
            row.querySelector('.day-end').disabled = !this.checked;
            row.querySelectorAll('.breaks-container input').forEach(function(i) { i.disabled = !cb.checked; });
        });
    });
}

function agregarDescanso(day) {
    var container = document.querySelector('.breaks-container[data-day="' + day + '"]');
    var div = document.createElement('div'); div.className = 'break-row';
    div.innerHTML = '<input type="time" class="break-start" value="13:00" style="width:80px"><input type="time" class="break-end" value="14:00" style="width:80px"><button type="button" class="btn btn-ghost btn-xs" onclick="this.closest(\'.break-row\').remove()">✕</button>';
    container.insertBefore(div, container.lastElementChild);
}

document.getElementById('form-horarios').addEventListener('submit', async function(e) {
    e.preventDefault();
    var btn = document.getElementById('btn-guardar-horarios'); btn.textContent = 'Guardando...'; btn.disabled = true;
    var workingPlan = {};
    DAYS.forEach(function(d) {
        var active = document.querySelector('.day-active[data-day="' + d.key + '"]').checked;
        if (!active) { workingPlan[d.key] = null; return; }
        var start = document.querySelector('.day-start[data-day="' + d.key + '"]').value;
        var end = document.querySelector('.day-end[data-day="' + d.key + '"]').value;
        var breaks = [];
        document.querySelector('.breaks-container[data-day="' + d.key + '"]').querySelectorAll('.break-row').forEach(function(row) {
            var bs = row.querySelector('.break-start').value; var be = row.querySelector('.break-end').value;
            if (bs && be) breaks.push({start:bs, end:be});
        });
        workingPlan[d.key] = {start: start, end: end, breaks: breaks};
    });
    try {
        var res = await fetch(WP_API, {method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({workingPlan:workingPlan, csrf_token:CSRF_TOKEN})});
        var data = await res.json();
        if (!data.success) { mostrarToast('Error: '+(data.error||'')); return; }
        mostrarToast('Horarios guardados');
    } catch(e) { mostrarToast('Error de conexión'); }
    finally { btn.textContent = 'Guardar horarios'; btn.disabled = false; }
});

// ===== CALENDARIO =====
function irHoy() { var n = new Date(); calYear = n.getFullYear(); calMonth = n.getMonth(); renderCalendario(); }
function navegarCal(dir) { calMonth += dir; if (calMonth > 11) { calMonth = 0; calYear++; } if (calMonth < 0) { calMonth = 11; calYear--; } renderCalendario(); }

async function renderCalendario() {
    if (calYear === undefined) { var n = new Date(); calYear = n.getFullYear(); calMonth = n.getMonth(); }
    var monthStr = calYear + '-' + String(calMonth+1).padStart(2,'0');
    document.getElementById('calTitle').textContent = MONTHS[calMonth] + ' ' + calYear;
    document.getElementById('calContainer').innerHTML = 'Cargando...';
    var res = await fetch(TURNOS_API + '?month=' + monthStr);
    var appts = await res.json();
    allAppointments = appts;

    var firstDay = new Date(calYear, calMonth, 1);
    var lastDay = new Date(calYear, calMonth + 1, 0);
    var startDow = firstDay.getDay();
    var startOffset = startDow === 0 ? 6 : startDow - 1;

    var dayMap = {};
    appts.forEach(function(a) {
        var d = a.start.slice(0,10);
        if (!dayMap[d]) dayMap[d] = [];
        dayMap[d].push(a);
    });

    var todayStr = new Date().toISOString().slice(0,10);
    var html = '<div class="cal-grid">';
    DAY_LABELS.forEach(function(l) { html += '<div class="cal-weekday">' + l + '</div>'; });

    var prevLastDay = new Date(calYear, calMonth, 0).getDate();
    for (var i = startOffset - 1; i >= 0; i--) {
        var d = prevLastDay - i;
        html += '<div class="cal-cell other-month"><div class="day-num">' + d + '</div></div>';
    }

    for (var d = 1; d <= lastDay.getDate(); d++) {
        var dateStr = calYear + '-' + String(calMonth+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        var isToday = dateStr === todayStr;
        var cellClass = isToday ? 'cal-cell today' : 'cal-cell';
        html += '<div class="' + cellClass + '"><div class="day-num">' + d + '</div>';
        if (dayMap[dateStr]) {
            dayMap[dateStr].forEach(function(a) {
                var time = a.start.slice(11,16);
                var name = a.customer ? a.customer.firstName : '?';
                var status = a.status || 'confirmed';
                html += '<div class="cal-appt ' + status + '" onclick="event.stopPropagation();abrirModalTurno(' + a.id + ')"><span class="cal-time">' + time + '</span> ' + name + '</div>';
            });
        }
        html += '</div>';
    }

    var totalCells = startOffset + lastDay.getDate();
    var remaining = (7 - (totalCells % 7)) % 7;
    for (var d = 1; d <= remaining; d++) {
        html += '<div class="cal-cell other-month"><div class="day-num">' + d + '</div></div>';
    }

    html += '</div>';
    document.getElementById('calContainer').innerHTML = html;
}

// ===== MODAL TURNO =====
async function abrirModalTurno(id) {
    var appt = allAppointments.find(function(a) { return a.id === id; });
    if (!appt) return;
    selectedAppt = appt;
    var c = appt.customer || {};
    var s = appt.service || {};
    var fecha = new Date(appt.start);
    var fechaStr = fecha.toLocaleDateString('es-AR', { weekday:'long', day:'numeric', month:'long', year:'numeric' });
    var horaStr = appt.start.slice(11,16) + ' - ' + appt.end.slice(11,16);
    var status = appt.status || 'confirmed';
    var statusLabel = status === 'confirmed' ? 'Confirmado' : 'Cancelado';
    var precio = s.price ? '$' + Number(s.price).toLocaleString('es-AR') : '—';

    var html = '<h2>Detalle del turno</h2>' +
        '<div class="detail-row"><span class="icon">👤</span><span class="val"><strong>' + (c.firstName||'?') + ' ' + (c.lastName||'') + '</strong></span></div>' +
        '<div class="detail-row"><span class="icon">📞</span><span class="val">' + (c.phone||'—') + '</span></div>' +
        '<div class="detail-row"><span class="icon">✉️</span><span class="val">' + (c.email||'—') + '</span></div>' +
        '<div style="height:1px;background:#f0ebe7;margin:12px 0;"></div>' +
        '<div class="detail-row"><span class="icon">💅</span><span class="val">' + (s.name||'—') + '</span></div>' +
        '<div class="detail-row"><span class="icon">⏱️</span><span class="val">' + horaStr + ' (' + (s.duration||'?') + ' min)</span></div>' +
        '<div class="detail-row"><span class="icon">💰</span><span class="val">' + precio + '</span></div>' +
        '<div class="detail-row"><span class="icon">📅</span><span class="val" style="text-transform:capitalize;">' + fechaStr + '</span></div>' +
        '<div class="detail-row"><span class="icon">🏷️</span><span class="val"><span class="status-badge ' + status + '">' + statusLabel + '</span></span></div>';

    if (status !== 'cancelled') {
        html += '<div class="modal-actions"><button class="btn btn-primary" onclick="mostrarReagendar()">Reagendar</button><button class="btn btn-danger" onclick="cancelarTurno(' + id + ')">Cancelar turno</button></div>';
    } else {
        html += '<div class="modal-actions"><button class="btn btn-ghost" onclick="cerrarModal()">Cerrar</button></div>';
    }

    html += '<div id="rescheduleSection" class="reschedule-section hidden">' +
        '<h3>Reagendar turno</h3>' +
        '<label>Nueva fecha</label>' +
        '<input type="date" id="reschedDate" min="' + new Date().toISOString().slice(0,10) + '" onchange="cargarSlotsReagendar(' + id + ')">' +
        '<div id="reschedSlots" style="margin-top:8px;"></div>' +
        '<div class="form-actions" style="margin-top:12px;">' +
        '<button class="btn btn-ghost" onclick="cerrarReagendar()">Cancelar</button>' +
        '<button class="btn btn-primary" id="btnConfirmarResched" onclick="confirmarReagendar(' + id + ')" disabled>Confirmar reagendamiento</button>' +
        '</div></div>';

    abrirModal(html);
}

function cerrarReagendar() {
    var sec = document.getElementById('rescheduleSection');
    if (sec) { sec.classList.add('hidden'); }
    var slots = document.getElementById('reschedSlots');
    if (slots) { slots.innerHTML = ''; }
}

async function cargarSlotsReagendar(id) {
    var date = document.getElementById('reschedDate').value;
    if (!date) return;
    var appt = selectedAppt;
    if (!appt || !appt.service) { document.getElementById('reschedSlots').innerHTML = '<div style="color:#999;">Servicio no disponible</div>'; return; }
    var serviceId = appt.service.id;
    var res = await fetch('../api/horarios.php?serviceId=' + serviceId + '&date=' + date);
    var data = await res.json();
    var container = document.getElementById('reschedSlots');
    var btnConfirmar = document.getElementById('btnConfirmarResched');
    btnConfirmar.disabled = true;

    if (data.dayOff || !data.slots || data.slots.length === 0) {
        container.innerHTML = '<div style="color:#999;">No hay horarios disponibles para esta fecha</div>';
        return;
    }

    var html = '<label style="margin-top:8px;">Horario disponible</label><div class="slot-options">';
    data.slots.forEach(function(s) {
        html += '<button type="button" class="slot-btn" data-slot="' + s + '" onclick="seleccionarSlot(this,\'' + s + '\')">' + s + '</button>';
    });
    html += '</div>';
    container.innerHTML = html;
}

var selectedSlot = null;

function seleccionarSlot(el, slot) {
    document.querySelectorAll('.slot-btn').forEach(function(b) { b.classList.remove('selected'); });
    el.classList.add('selected');
    selectedSlot = slot;
    document.getElementById('btnConfirmarResched').disabled = false;
}

async function confirmarReagendar(id) {
    var date = document.getElementById('reschedDate').value;
    if (!date || !selectedSlot) return;
    var start = date + ' ' + selectedSlot + ':00';
    var appt = selectedAppt;
    if (!appt || !appt.service) return;
    var dur = appt.service.duration;
    var startDt = new Date(date + 'T' + selectedSlot + ':00');
    var endDt = new Date(startDt.getTime() + dur * 60000);
    var end = endDt.toISOString().slice(0,19).replace('T',' ');

    var res = await fetch(TURNOS_API + '?id=' + id, { method:'PUT', headers:{'Content-Type':'application/json'}, body:JSON.stringify({start:start, end:end, csrf_token:CSRF_TOKEN}) });
    if (!res.ok) { var err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Turno reagendado con éxito');
    cerrarModal();
    renderCalendario();
    var activeTab = document.querySelector('.tab-btn.active');
    if (activeTab) {
        if (activeTab.dataset.tab === 'turnos') renderTurnos();
        if (activeTab.dataset.tab === 'dashboard') cargarDashboard();
    }
}

function mostrarReagendar() {
    var sec = document.getElementById('rescheduleSection');
    if (sec) { sec.classList.remove('hidden'); }
    var tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    var inp = document.getElementById('reschedDate');
    if (inp) inp.min = tomorrow.toISOString().slice(0,10);
}

async function cancelarTurno(id) {
    if (!confirm('¿Estás segura de cancelar este turno?')) return;
    var res = await fetch(TURNOS_API + '?id=' + id, {method:'DELETE'});
    if (!res.ok) { var err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Turno cancelado');
    cerrarModal();
    renderCalendario();
    var activeTab = document.querySelector('.tab-btn.active');
    if (activeTab) {
        if (activeTab.dataset.tab === 'turnos') renderTurnos();
        if (activeTab.dataset.tab === 'dashboard') cargarDashboard();
    }
}

// ===== TURNOS LISTA =====
var filteredTurnos = [];

async function renderTurnos() {
    var container = document.getElementById('turnosContainer');
    container.innerHTML = '<div class="empty-state">Cargando turnos...</div>';
    var res = await fetch(TURNOS_API);
    var appts = await res.json();
    allAppointments = appts;
    filteredTurnos = appts;
    filtrarTurnos();
}

function filtrarTurnos() {
    var q = document.getElementById('searchTurno').value.toLowerCase();
    var estado = document.getElementById('filtroEstado').value;
    var list = allAppointments;
    if (q) list = list.filter(function(a) {
        var c = a.customer || {};
        return (c.firstName+' '+c.lastName).toLowerCase().indexOf(q) >= 0 || (c.phone||'').indexOf(q) >= 0;
    });
    if (estado) list = list.filter(function(a) { return a.status === estado; });
    list.sort(function(a,b) { return a.start.localeCompare(b.start); });
    filteredTurnos = list;
    var container = document.getElementById('turnosContainer');

    if (!list.length) {
        container.innerHTML = '<div class="empty-state"><div class="icon">📋</div>No se encontraron turnos</div>';
        return;
    }

    var html = '<table><thead><tr><th>Fecha</th><th>Hora</th><th>Cliente</th><th>Servicio</th><th>Precio</th><th>Estado</th><th></th></tr></thead><tbody>';
    list.forEach(function(a) {
        var d = a.start.slice(0,10).split('-').reverse().join('/');
        var h = a.start.slice(11,16) + ' - ' + a.end.slice(11,16);
        var c = a.customer ? a.customer.firstName + ' ' + (a.customer.lastName||'') : '—';
        var tel = a.customer ? a.customer.phone||'' : '';
        var s = a.service ? a.service.name : '—';
        var p = a.service ? '$' + Number(a.service.price).toLocaleString('es-AR') : '—';
        var est = a.status || 'confirmed';
        var estLabel = est === 'confirmed' ? 'Confirmado' : 'Cancelado';
        html += '<tr class="turno-row"><td>' + d + '</td><td style="font-weight:500;">' + h + '</td>' +
            '<td><span class="cliente">' + c + '</span>' + (tel ? '<br><span style="font-size:12px;color:#999;">'+tel+'</span>' : '') + '</td>' +
            '<td class="servicio-info">' + s + '</td><td class="precio">' + p + '</td>' +
            '<td><span class="status-badge ' + est + '">' + estLabel + '</span></td>' +
            '<td class="actions"><button class="btn btn-ghost btn-sm" onclick="abrirModalTurno(' + a.id + ')">Ver</button>' +
            (est !== 'cancelled' ? '<button class="btn btn-danger btn-sm" onclick="cancelarTurnoLista(' + a.id + ')">Cancelar</button>' : '') + '</td></tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

async function cancelarTurnoLista(id) {
    if (!confirm('¿Cancelar este turno?')) return;
    var res = await fetch(TURNOS_API + '?id=' + id, {method:'DELETE'});
    if (!res.ok) { var err = await res.json(); mostrarToast('Error: '+(err.error||'desconocido')); return; }
    mostrarToast('Turno cancelado');
    renderTurnos();
    renderCalendario();
}

// ===== WHATSAPP QR =====
var waPollTimer = null;

async function cargarWhatsApp() {
    var statusDiv = document.getElementById('whatsapp-status');
    var qrDiv = document.getElementById('whatsapp-qr-container');
    var connectedDiv = document.getElementById('whatsapp-connected');
    try {
        var res = await fetch('../api/whatsapp-qr.php');
        var data = await res.json();
        if (data.status === 'connected') {
            statusDiv.innerHTML = '';
            qrDiv.style.display = 'none';
            connectedDiv.style.display = 'block';
            if (waPollTimer) { clearInterval(waPollTimer); waPollTimer = null; }
            return;
        }
        if (data.status === 'awaiting_qr' && data.qr) {
            statusDiv.innerHTML = '';
            document.getElementById('whatsapp-qr-img').src = data.qr;
            qrDiv.style.display = 'block';
            connectedDiv.style.display = 'none';
            return;
        }
        statusDiv.innerHTML = '<div class="wa-loading">⏳ Esperando código QR...</div><div class="wa-retry">La página se actualiza automáticamente</div>';
        qrDiv.style.display = 'none';
        connectedDiv.style.display = 'none';
    } catch(e) {
        statusDiv.innerHTML = '<div class="wa-error">❌ No se pudo conectar con el servicio de WhatsApp</div><div class="wa-retry">Asegurate que el contenedor de Baileys esté funcionando</div>';
        qrDiv.style.display = 'none';
        connectedDiv.style.display = 'none';
    }
}

// ===== PERSONALIZATION: BRAND FORM =====
function guardarMarca() {
    var form = document.getElementById('formMarca');
    var btn = form.querySelector('[type="button"]');
    btn.disabled = true;
    btn.textContent = 'Guardando...';
    var fd = new FormData(form);
    fd.append('action', 'save_brand');
    fd.append('csrf_token', CSRF_TOKEN);
    fetch('index.php', { method: 'POST', body: fd })
        .then(function(r) { return r.text(); })
        .then(function(text) {
            var d;
            try { d = JSON.parse(text); }
            catch (e) { mostrarToast('Error: respuesta inválida del servidor'); console.error('Respuesta del servidor:', text); btn.disabled = false; btn.textContent = 'Guardar cambios'; return; }
            if (d.success) {
                mostrarToast('Marca guardada');
                var b = d.brand || {};
                if (b.name !== undefined) form.querySelector('[name="name"]').value = b.name;
                if (b.tagline !== undefined) form.querySelector('[name="tagline"]').value = b.tagline;
                if (b.address !== undefined) form.querySelector('[name="address"]').value = b.address;
                if (b.whatsapp !== undefined) form.querySelector('[name="whatsapp"]').value = b.whatsapp;
                if (b.instagram !== undefined) form.querySelector('[name="instagram"]').value = b.instagram;
            } else {
                mostrarToast('Error: ' + (d.error || 'desconocido'));
            }
        })
        .catch(function(err) { mostrarToast('Error de red: ' + err.message); })
        .finally(function() { btn.disabled = false; btn.textContent = 'Guardar cambios'; });
}

// ===== PERSONALIZATION: COLORS FORM =====
var formColores = document.getElementById('formColores');
if (formColores) {
    var colorInputs = formColores.querySelectorAll('input[type="color"]');
    var previewHeader = document.querySelector('.preview-header');
    var previewHero = document.querySelector('.preview-hero');
    var previewBtn = document.querySelector('.preview-btn');

    colorInputs.forEach(function(input) {
        input.addEventListener('input', function() {
            var name = this.name;
            var val = this.value;
            if (name === 'primary') {
                if (previewHeader) previewHeader.style.background = val;
                if (previewBtn) previewBtn.style.background = val;
            }
            if (name === 'secondary' && previewHero) previewHero.style.background = val;
            if (name === 'text' && previewHero) previewHero.style.color = val;
        });
    });

    formColores.addEventListener('submit', function(e) {
        e.preventDefault();
        var fd = new FormData(this);
        fd.append('action', 'save_colors');
        fd.append('csrf_token', CSRF_TOKEN);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) { mostrarToast(d.success ? 'Colores guardados' : ('Error: ' + (d.error || 'desconocido'))); })
            .catch(function() { mostrarToast('Error de conexión'); });
    });
}

// ===== PERSONALIZATION: LOGO UPLOAD =====
(function() {
    var logoInput = document.getElementById('logoInput');
    if (logoInput) {
        logoInput.addEventListener('change', function() {
            if (!this.files || !this.files[0]) return;
            var file = this.files[0];
            if (file.type !== 'image/png') { mostrarToast('Solo se permiten archivos PNG'); return; }
            if (file.size > 2 * 1024 * 1024) { mostrarToast('Máximo 2MB'); return; }

            var fd = new FormData();
            fd.append('action', 'upload_logo');
            fd.append('csrf_token', CSRF_TOKEN);
            fd.append('logo', file);
            fetch('index.php', { method: 'POST', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.error) { mostrarToast('Error: ' + d.error); return; }
                    mostrarToast('Logo actualizado');
                    var preview = document.getElementById('logoPreview');
                    var placeholder = document.getElementById('logoPlaceholder');
                    preview.src = '../' + d.logo;
                    preview.style.display = 'block';
                    if (placeholder) placeholder.style.display = 'none';
                    var delBtn = document.getElementById('btnDeleteLogo');
                    if (delBtn) { delBtn.style.display = 'inline-flex'; delBtn.textContent = 'Eliminar logo'; }
                    else {
                        var div = document.querySelector('#tab-logo .form-actions');
                        if (div) {
                            var b = document.createElement('button');
                            b.className = 'btn btn-danger'; b.id = 'btnDeleteLogo'; b.textContent = 'Eliminar logo';
                            b.addEventListener('click', eliminarLogo);
                            div.appendChild(b);
                        }
                    }
                })
                .catch(function() { mostrarToast('Error de conexión'); });
        });
    }

    function eliminarLogo() {
        if (!confirm('¿Eliminar el logo?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_logo');
        fd.append('csrf_token', CSRF_TOKEN);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) { mostrarToast('Error: ' + d.error); return; }
                mostrarToast('Logo eliminado');
                var preview = document.getElementById('logoPreview');
                preview.src = ''; preview.style.display = 'none';
                var placeholder = document.getElementById('logoPlaceholder');
                if (placeholder) placeholder.style.display = 'flex';
                var delBtn = document.getElementById('btnDeleteLogo');
                if (delBtn) delBtn.style.display = 'none';
            })
            .catch(function() { mostrarToast('Error de conexión'); });
    }

    var btnDeleteLogo = document.getElementById('btnDeleteLogo');
    if (btnDeleteLogo) btnDeleteLogo.addEventListener('click', eliminarLogo);
})();

// ===== PERSONALIZATION: GALLERY =====
(function() {
    function renderizarGaleria(gallery) {
        var grid = document.getElementById('galleryGrid');
        if (!gallery || gallery.length === 0) {
            grid.innerHTML = '<div class="gallery-empty" id="galleryEmpty">No hay imágenes todavía</div>';
        } else {
            var html = '';
            gallery.forEach(function(img) {
                html += '<div class="gallery-thumb" data-filename="' + img.filename + '">';
                html += '<img src="../uploads/gallery/' + img.filename + '" alt="" loading="lazy">';
                html += '<button class="delete-btn" title="Eliminar" onclick="eliminarGaleria(\'' + img.filename + '\')">&times;</button>';
                html += '</div>';
            });
            grid.innerHTML = html;
        }
        var zone = document.getElementById('galleryUploadZone');
        if (zone) {
            var countDiv = zone.querySelector('div:last-child');
            if (countDiv) countDiv.textContent = 'JPG o PNG · Máx 5MB · ' + (gallery ? gallery.length : 0) + '/10';
        }
    }

    window.eliminarGaleria = function(filename) {
        if (!confirm('¿Eliminar esta imagen?')) return;
        var fd = new FormData();
        fd.append('action', 'delete_gallery');
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('filename', filename);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) { mostrarToast('Error: ' + d.error); return; }
                mostrarToast('Imagen eliminada');
                renderizarGaleria(d.gallery);
            })
            .catch(function() { mostrarToast('Error de conexión'); });
    };

    function subirGaleria(file) {
        if (['image/jpeg', 'image/png'].indexOf(file.type) === -1) { mostrarToast('Solo JPG y PNG'); return; }
        if (file.size > 5 * 1024 * 1024) { mostrarToast('Máximo 5MB'); return; }
        var fd = new FormData();
        fd.append('action', 'upload_gallery');
        fd.append('csrf_token', CSRF_TOKEN);
        fd.append('image', file);
        fetch('index.php', { method: 'POST', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) { mostrarToast('Error: ' + d.error); return; }
                mostrarToast('Imagen agregada');
                renderizarGaleria(d.gallery);
            })
            .catch(function() { mostrarToast('Error de conexión'); });
    }

    var galleryInput = document.getElementById('galleryInput');
    var galleryZone = document.getElementById('galleryUploadZone');
    if (galleryInput && galleryZone) {
        galleryZone.addEventListener('click', function() { galleryInput.click(); });
        galleryZone.addEventListener('dragover', function(e) { e.preventDefault(); this.style.borderColor = '#b76e79'; });
        galleryZone.addEventListener('dragleave', function() { this.style.borderColor = ''; });
        galleryZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '';
            if (e.dataTransfer.files && e.dataTransfer.files[0]) subirGaleria(e.dataTransfer.files[0]);
        });
        galleryInput.addEventListener('change', function() {
            if (this.files && this.files[0]) subirGaleria(this.files[0]);
        });
    }
})();

// ===== INIT =====
cargarServicios();
cargarHorarios();
cargarDashboard();
</script>
<?php endif; ?>
</body>
</html>
