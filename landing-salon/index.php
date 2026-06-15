<?php
require_once __DIR__ . '/env-loader.php';
$tomorrow = (new DateTime('tomorrow'))->format('Y-m-d');
$configFile = __DIR__ . '/config.json';
$config = json_decode(file_get_contents($configFile), true) ?: [];
$brand = $config['brand'] ?? [];
$colors = $config['colors'] ?? [];
$gallery = $config['gallery'] ?? [];
$logo = $config['logo'] ?? '';

$name = htmlspecialchars($brand['name'] ?? 'TuAhora');
$tagline = htmlspecialchars($brand['tagline'] ?? '');
$address = htmlspecialchars($brand['address'] ?? '');
$whatsapp = htmlspecialchars($brand['whatsapp'] ?? '');
$instagram = htmlspecialchars($brand['instagram'] ?? '');
$instaUrl = 'https://instagram.com/' . ltrim($instagram, '@');
$waUrl = 'https://wa.me/' . $whatsapp;
$mapsQuery = urlencode($address);

$pri = $colors['primary'] ?? '#E8A0A0';
$sec = $colors['secondary'] ?? '#F5F0F0';
$acc = $colors['accent'] ?? '#B56576';
$txt = $colors['text'] ?? '#2D2D2D';
$bg = $colors['background'] ?? '#FFFFFF';

$logoPath = ($logo && file_exists(__DIR__ . '/' . $logo)) ? $logo : '';

$ch = curl_init('http://localhost/index.php/api/v1/services');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_USERPWD => ($_ENV['EA_API_USER'] ?? 'kamiikasee') . ':' . ($_ENV['EA_API_PASS'] ?? 'admin2024'),
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
    CURLOPT_TIMEOUT => 3,
]);
$serviciosJson = curl_exec($ch);
curl_close($ch);
$servicios = json_decode($serviciosJson, true) ?: [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?=$name?> · Manicuría en Chamical</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>
:root {
    --pri: <?=$pri?>;
    --sec: <?=$sec?>;
    --acc: <?=$acc?>;
    --txt: <?=$txt?>;
    --bg: <?=$bg?>;
    --pri-light: color-mix(in srgb, var(--pri) 25%, #fff);
    --txt-sub: #888;
}
* { margin:0; padding:0; box-sizing:border-box; }
html { scroll-behavior:smooth; }
body { font-family:'Inter',sans-serif; color:var(--txt); background:var(--bg); overflow-x:hidden; line-height:1.6; }

nav {
    position:fixed; top:0; width:100%; z-index:100;
    background:color-mix(in srgb, var(--bg) 88%, transparent);
    backdrop-filter:blur(12px);
    border-bottom:1px solid rgba(0,0,0,.06);
    padding:12px 20px;
    display:flex; align-items:center; justify-content:space-between;
}
.nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
.nav-brand img { height:36px; width:auto; border-radius:6px; }
.nav-brand .brand-name { font-family:'Playfair Display',serif; font-size:20px; color:var(--pri); font-weight:600; }
.nav-links { display:flex; gap:20px; align-items:center; }
.nav-links a { text-decoration:none; font-size:14px; color:var(--txt); transition:color .2s; font-weight:500; }
.nav-links a:hover { color:var(--pri); }
.nav-cta { background:var(--pri); color:#fff!important; padding:8px 18px; border-radius:20px; font-size:13px!important; white-space:nowrap; }
.hamburger { display:none; flex-direction:column; gap:5px; cursor:pointer; background:none; border:none; padding:4px; z-index:101; }
.hamburger span { display:block; width:24px; height:2px; background:var(--txt); border-radius:2px; transition:.3s; }
.hamburger.open span:nth-child(1) { transform:rotate(45deg) translate(5px,5px); }
.hamburger.open span:nth-child(2) { opacity:0; }
.hamburger.open span:nth-child(3) { transform:rotate(-45deg) translate(5px,-5px); }

.hero {
    min-height:90vh; display:flex; align-items:center; justify-content:center;
    text-align:center; padding:120px 24px 60px;
    background: linear-gradient(135deg, var(--sec) 0%, var(--bg) 100%);
    position:relative;
}
.hero::before {
    content:''; position:absolute; top:0; left:0; right:0; height:4px;
    background: linear-gradient(90deg, var(--pri), var(--acc), var(--pri));
}
.hero-content { max-width:640px; }
.hero-content .logo-hero { max-width:120px; margin-bottom:20px; }
.badge {
    display:inline-block; background:var(--pri-light); color:var(--pri);
    padding:6px 16px; border-radius:20px; font-size:12px; font-weight:500;
    margin-bottom:20px; letter-spacing:.3px;
}
.hero h1 { font-family:'Playfair Display',serif; font-size:48px; color:var(--txt); line-height:1.15; margin-bottom:16px; }
.hero h1 span { color:var(--pri); }
.hero p { font-size:18px; line-height:1.7; margin-bottom:32px; color:var(--txt-sub); }
.hero-btns { display:flex; gap:12px; justify-content:center; flex-wrap:wrap; }
.btn-primary {
    display:inline-flex; align-items:center; gap:8px;
    background:var(--pri); color:#fff; padding:14px 28px;
    border-radius:30px; text-decoration:none; font-weight:500;
    transition:.2s; border:none; cursor:pointer; font-size:15px; font-family:inherit;
}
.btn-primary:hover { filter:brightness(.9); transform:translateY(-1px); }
.btn-primary:disabled { opacity:.5; cursor:not-allowed; transform:none; filter:none; }
.btn-secondary {
    display:inline-flex; align-items:center; gap:8px;
    background:transparent; color:var(--txt); padding:14px 28px;
    border-radius:30px; text-decoration:none; font-weight:500;
    border:1.5px solid rgba(128,128,128,.25); transition:.2s; font-size:15px; font-family:inherit;
}
.btn-secondary:hover { border-color:var(--pri); color:var(--pri); }

.section { padding:80px 24px; }
.section-title { text-align:center; margin-bottom:48px; }
.section-title h2 { font-family:'Playfair Display',serif; font-size:34px; color:var(--txt); margin-bottom:8px; }
.section-title p { color:var(--txt-sub); font-size:15px; }
.container { max-width:1100px; margin:0 auto; }

.services-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:24px; }
.service-card {
    background:#fff; border-radius:20px; padding:28px; transition:.3s;
    border:1px solid rgba(0,0,0,.04); box-shadow:0 4px 20px rgba(0,0,0,.04);
    display:flex; flex-direction:column;
}
.service-card:hover { transform:translateY(-4px); box-shadow:0 12px 40px color-mix(in srgb, var(--pri) 15%, transparent); }
.service-card .icon { font-size:28px; margin-bottom:12px; }
.service-card h3 { font-size:18px; color:var(--txt); margin-bottom:6px; }
.service-card .desc { font-size:13px; color:var(--txt-sub); line-height:1.5; margin-bottom:16px; flex:1; }
.service-card .meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
.service-card .precio { font-size:22px; font-weight:700; color:var(--pri); }
.service-card .duracion { font-size:13px; color:var(--txt-sub); display:flex; align-items:center; gap:4px; }
.service-card .btn-reservar {
    width:100%; padding:12px; border-radius:14px; border:none;
    background:var(--sec); color:var(--pri); font-weight:600; font-size:14px;
    cursor:pointer; transition:.2s; text-decoration:none; text-align:center; display:block; font-family:inherit;
}
.service-card .btn-reservar:hover { background:var(--pri); color:#fff; }

.steps { display:grid; grid-template-columns:repeat(3,1fr); gap:32px; max-width:800px; margin:0 auto; }
.step { text-align:center; }
.step .num { width:56px; height:56px; border-radius:50%; background:var(--pri-light); color:var(--pri); display:flex; align-items:center; justify-content:center; margin:0 auto 16px; font-size:22px; font-weight:700; font-family:'Playfair Display',serif; }
.step h4 { font-size:16px; color:var(--txt); margin-bottom:6px; }
.step p { font-size:13px; color:var(--txt-sub); line-height:1.5; }

.gallery-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:12px; }
.gallery-item {
    aspect-ratio:1; border-radius:16px; overflow:hidden; cursor:pointer;
    background:var(--sec); transition:.2s; position:relative;
}
.gallery-item:hover { transform:scale(1.02); }
.gallery-item img { width:100%; height:100%; object-fit:cover; display:block; }
.gallery-item--empty {
    display:flex; align-items:center; justify-content:center;
    border:2px dashed color-mix(in srgb, var(--pri) 30%, transparent);
    cursor:default; min-height:160px;
}
.gallery-item--empty:hover { transform:none; }
.gallery-placeholder-inner { text-align:center; }
.gallery-placeholder-icon { font-size:28px; display:block; margin-bottom:4px; }
.gallery-placeholder-text { font-size:12px; color:var(--txt-sub); }

.lightbox {
    display:none; position:fixed; top:0; left:0; width:100%; height:100%;
    background:rgba(0,0,0,.9); z-index:9999; align-items:center; justify-content:center;
    cursor:pointer; flex-direction:column;
}
.lightbox.show { display:flex; }
.lightbox img { max-width:90vw; max-height:80vh; object-fit:contain; border-radius:8px; }
.lightbox .lb-close { position:absolute; top:20px; right:24px; color:#fff; font-size:32px; cursor:pointer; width:40px; height:40px; display:flex; align-items:center; justify-content:center; }
.lightbox .lb-nav { position:absolute; top:50%; transform:translateY(-50%); color:#fff; font-size:40px; cursor:pointer; width:50px; height:50px; display:flex; align-items:center; justify-content:center; user-select:none; }
.lightbox .lb-prev { left:16px; }
.lightbox .lb-next { right:16px; }

.ubicacion-card { background:#fff; border-radius:20px; padding:32px; box-shadow:0 4px 24px rgba(0,0,0,.06); max-width:500px; margin:0 auto; }
.ubicacion-card h3 { font-size:20px; color:var(--txt); margin-bottom:12px; }
.ubicacion-card p { color:var(--txt-sub); font-size:15px; margin-bottom:8px; }
.ubicacion-card .maps-link { display:inline-flex; align-items:center; gap:6px; color:var(--pri); text-decoration:none; font-weight:500; font-size:14px; margin-top:8px; }
.ubicacion-card .maps-link:hover { text-decoration:underline; }

footer { background:var(--txt); color:rgba(255,255,255,.65); padding:40px 24px; text-align:center; }
footer .social { display:flex; justify-content:center; gap:24px; margin-bottom:16px; }
footer .social a { color:rgba(255,255,255,.65); text-decoration:none; font-size:14px; transition:.2s; display:flex; align-items:center; gap:6px; }
footer .social a:hover { color:var(--pri); }
footer p { font-size:12px; }
footer .brand { font-family:'Playfair Display',serif; font-size:18px; color:var(--pri); margin-bottom:8px; }

.fade-in { opacity:0; transform:translateY(20px); transition:.6s ease; }
.fade-in.show { opacity:1; transform:translateY(0); }

/* Booking form */
.booking-section { background:var(--sec); }
.booking-form-wrapper { max-width:520px; margin:0 auto; background:#fff; border-radius:24px; padding:32px; box-shadow:0 8px 32px rgba(0,0,0,.08); }
.booking-step { display:none; }
.booking-step.active { display:block; }
.step-bar { display:flex; justify-content:center; gap:8px; margin-bottom:28px; }
.step-bar .step-dot { width:36px; height:36px; border-radius:50%; background:rgba(128,128,128,.08); color:var(--txt-sub); display:flex; align-items:center; justify-content:center; font-size:14px; font-weight:600; transition:.2s; }
.step-bar .step-dot.done { background:var(--pri); color:#fff; }
.step-bar .step-dot.current { background:var(--pri); color:#fff; box-shadow:0 0 0 4px color-mix(in srgb, var(--pri) 20%, transparent); }
.step-bar .step-line { width:32px; height:2px; background:rgba(128,128,128,.12); align-self:center; border-radius:1px; }
.step-bar .step-line.done { background:var(--pri); }

.bf-service-card { border:2px solid rgba(128,128,128,.1); border-radius:14px; padding:16px; margin-bottom:10px; cursor:pointer; transition:.2s; display:flex; justify-content:space-between; align-items:center; }
.bf-service-card:hover { border-color:var(--pri); }
.bf-service-card.selected { border-color:var(--pri); background:var(--pri-light); }
.bf-service-card .bf-svc-name { font-weight:600; font-size:15px; color:var(--txt); }
.bf-service-card .bf-svc-meta { font-size:13px; color:var(--txt-sub); margin-top:3px; }
.bf-service-card .bf-svc-price { font-size:18px; font-weight:700; color:var(--pri); }

.bf-date-input { width:100%; padding:14px 16px; border:2px solid rgba(128,128,128,.15); border-radius:14px; font-size:15px; outline:none; font-family:inherit; cursor:pointer; }
.bf-date-input:focus { border-color:var(--pri); box-shadow:0 0 0 3px color-mix(in srgb, var(--pri) 15%, transparent); }
.slots-hint { text-align:center; color:var(--txt-sub); font-size:14px; padding:16px; }
.slots-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(85px,1fr)); gap:8px; margin:16px 0; }
.slot-btn { padding:10px 8px; border-radius:10px; border:1.5px solid rgba(128,128,128,.2); background:#fff; font-size:14px; cursor:pointer; transition:.2s; text-align:center; color:var(--txt); font-family:inherit; }
.slot-btn:hover { border-color:var(--pri); color:var(--pri); }
.slot-btn.selected { border-color:var(--pri); background:var(--pri); color:#fff; }
.slots-loading { text-align:center; padding:20px; }
.slots-loading .spinner { width:28px; height:28px; border:3px solid rgba(128,128,128,.08); border-top-color:var(--pri); border-radius:50%; animation:spin .6s linear infinite; margin:0 auto 8px; }
@keyframes spin { to { transform:rotate(360deg); } }

.bf-label { display:block; font-size:13px; color:var(--txt-sub); margin-bottom:4px; }
.bf-input { width:100%; padding:14px 16px; border:2px solid rgba(128,128,128,.15); border-radius:14px; font-size:15px; outline:none; font-family:inherit; transition:.2s; }
.bf-input:focus { border-color:var(--pri); box-shadow:0 0 0 3px color-mix(in srgb, var(--pri) 15%, transparent); }
.bf-row { display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:16px; }
.bf-group { margin-bottom:16px; }

.confirmation-box { text-align:center; padding:24px 8px; }
.confirmation-box .conf-icon { font-size:52px; margin-bottom:12px; }
.confirmation-box h3 { font-family:'Playfair Display',serif; font-size:22px; color:var(--txt); margin-bottom:8px; }
.confirmation-box p { color:var(--txt-sub); font-size:15px; line-height:1.5; margin-bottom:6px; }
.confirmation-box .resumen { background:var(--pri-light); border-radius:14px; padding:14px 18px; margin:16px 0; text-align:left; font-size:14px; line-height:1.7; }
.confirmation-box .resumen strong { color:var(--txt); }

.bf-actions { display:flex; gap:8px; justify-content:flex-end; margin-top:16px; }
.bf-actions .btn { padding:14px 28px; border-radius:30px; font-size:15px; cursor:pointer; font-family:inherit; transition:.2s; }
.bf-actions .btn-back { background:transparent; color:var(--txt-sub); border:1.5px solid rgba(128,128,128,.2); }
.bf-actions .btn-back:hover { border-color:var(--pri); color:var(--pri); }
.bf-actions .btn-next { background:var(--pri); color:#fff; border:none; }
.bf-actions .btn-next:hover { filter:brightness(.9); }
.bf-error { color:#e74c3c; font-size:13px; text-align:center; margin-top:8px; }

.bf-summary { background:var(--pri-light); border-radius:14px; padding:14px 18px; margin-bottom:20px; font-size:14px; }
.bf-summary .bf-summary-line { margin-bottom:6px; }
.bf-summary .bf-summary-line:last-child { margin-bottom:0; }
.bf-summary .bf-summary-label { color:var(--txt-sub); }
.bf-summary .bf-summary-val { color:var(--txt); font-weight:500; }

@media(max-width:768px) {
    .nav-links { position:fixed; top:0; right:-280px; width:280px; height:100vh; background:var(--bg); flex-direction:column; padding:80px 24px 24px; gap:16px; transition:right .3s; box-shadow:-4px 0 20px rgba(0,0,0,.08); }
    .nav-links.open { right:0; }
    .nav-links a:not(.nav-cta) { font-size:16px; }
    .hamburger { display:flex; }
    .hero h1 { font-size:32px; }
    .hero p { font-size:16px; }
    .steps { grid-template-columns:1fr; max-width:320px; }
    .gallery-grid { grid-template-columns:repeat(2,1fr); }
    .services-grid { grid-template-columns:1fr; }
    .bf-row { grid-template-columns:1fr; }
    .booking-form-wrapper { padding:24px; }
}
@media(max-width:400px) {
    .gallery-grid { grid-template-columns:1fr 1fr; }
}
</style>
</head>
<body>

<nav>
    <a href="#" class="nav-brand">
        <?php if ($logoPath): ?><img src="<?=$logoPath?>" alt="<?=$name?>"><?php endif; ?>
        <span class="brand-name"><?=$name?></span>
    </a>
    <div class="nav-links" id="navLinks">
        <a href="#servicios">Servicios</a>
        <a href="#como-funciona">Cómo funciona</a>
        <a href="#galeria">Galería</a>
        <a href="#reserva">Reservar</a>
        <a href="#ubicacion">Ubicación</a>
        <a href="<?=$waUrl?>" target="_blank" class="nav-cta">WhatsApp</a>
    </div>
    <button class="hamburger" id="hamburger" aria-label="Menú">
        <span></span><span></span><span></span>
    </button>
</nav>

<section class="hero">
    <div class="hero-content fade-in">
        <div class="badge">✨ En el centro de Chamical</div>
        <h1><?=$tagline?></h1>
        <p>Manicuría profesional con productos de primera calidad. Turnos online, sin vueltas.</p>
        <div class="hero-btns">
            <a href="#reserva" class="btn-primary">✨ Reservar turno</a>
            <a href="#servicios" class="btn-secondary">Ver servicios</a>
        </div>
    </div>
</section>

<section id="servicios" class="section">
    <div class="container">
        <div class="section-title fade-in">
            <h2>Servicios</h2>
            <p>Todo lo que necesitás para lucir unas manos perfectas</p>
        </div>
        <div class="services-grid">
            <?php if (empty($servicios)): ?>
                <p style="color:var(--txt-sub);grid-column:1/-1;text-align:center;">Cargando servicios...</p>
            <?php else: ?>
                <?php $icons = ['💅','✨','🦶','💎','🎨','💫']; $i = 0; ?>
                <?php foreach ($servicios as $s): ?>
                    <?php if ($s['id'] == 1 && $s['name'] == 'Service') continue; ?>
                    <div class="service-card fade-in">
                        <div class="icon"><?=$icons[$i++ % count($icons)]?></div>
                        <h3><?=htmlspecialchars($s['name'])?></h3>
                        <p class="desc"><?=htmlspecialchars($s['description'] ?: 'Servicio profesional de manicuría')?></p>
                        <div class="meta">
                            <span class="precio">$<?=number_format((float)$s['price'], 0, ',', '.')?></span>
                            <span class="duracion">🕐 <?=(int)$s['duration']?> min</span>
                        </div>
                        <a href="#reserva" class="btn-reservar" onclick="irAReserva(<?=$s['id']?>)">Reservar turno</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="como-funciona" class="section" style="background:var(--sec);">
    <div class="container">
        <div class="section-title fade-in">
            <h2>¿Cómo funciona?</h2>
            <p>Reservá en 3 pasos</p>
        </div>
        <div class="steps">
            <div class="step fade-in"><div class="num">1</div><h4>Elegí tu servicio</h4><p>Mirá nuestras opciones y elegí lo que más te guste.</p></div>
            <div class="step fade-in"><div class="num">2</div><h4>Elegí fecha y horario</h4><p>Seleccioná el día y la hora que más te convenga.</p></div>
            <div class="step fade-in"><div class="num">3</div><h4>Confirmá y listo</h4><p>Te llega la confirmación por WhatsApp automáticamente.</p></div>
        </div>
    </div>
</section>

<section id="galeria" class="section gallery-section">
    <div class="container">
        <div class="section-title fade-in">
            <h2>Galería</h2>
            <p>Algunos de nuestros trabajos</p>
        </div>
        <div class="gallery-grid" id="galleryGrid">
            <?php if (empty($gallery)): ?>
                <?php for ($j = 0; $j < 6; $j++): ?>
                <div class="gallery-item gallery-item--empty fade-in">
                    <div class="gallery-placeholder-inner">
                        <span class="gallery-placeholder-icon">📸</span>
                        <span class="gallery-placeholder-text">Agregá fotos desde el panel</span>
                    </div>
                </div>
                <?php endfor; ?>
            <?php else: ?>
                <?php foreach ($gallery as $i => $img): ?>
                    <?php $fname = htmlspecialchars($img['filename'] ?? ''); ?>
                    <?php if ($fname): ?>
                    <div class="gallery-item fade-in" data-index="<?=$i?>">
                        <img src="uploads/gallery/<?=$fname?>" alt="Trabajo <?=$i+1?>" loading="lazy">
                    </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<section id="reserva" class="section booking-section">
    <div class="container">
        <div class="section-title fade-in">
            <h2>Reservá tu turno</h2>
            <p>Elegí tu servicio, fecha y horario</p>
        </div>
        <p style="text-align:center;color:var(--acc);font-size:14px;margin-bottom:24px;background:var(--pri-light);padding:10px 20px;border-radius:12px;max-width:520px;margin-left:auto;margin-right:auto;">📅 Las reservas requieren <strong>24 horas de anticipación</strong></p>
        <div class="booking-form-wrapper">
            <!-- Step Bar -->
            <div class="step-bar">
                <span class="step-dot current" id="stepDot1">1</span>
                <span class="step-line" id="stepLine1"></span>
                <span class="step-dot" id="stepDot2">2</span>
                <span class="step-line" id="stepLine2"></span>
                <span class="step-dot" id="stepDot3">3</span>
            </div>

            <!-- Step 1: Select Service -->
            <div class="booking-step active" id="step1">
                <h3 style="text-align:center;margin-bottom:20px;color:var(--txt);">Elegí tu servicio</h3>
                <div id="serviceList">
                    <?php foreach ($servicios as $s): ?>
                        <?php if ($s['id'] == 1 && $s['name'] == 'Service') continue; ?>
                        <div class="bf-service-card" data-id="<?=$s['id']?>" data-name="<?=htmlspecialchars($s['name'])?>" data-price="<?=(float)$s['price']?>" data-duration="<?=(int)$s['duration']?>">
                            <div>
                                <div class="bf-svc-name"><?=htmlspecialchars($s['name'])?></div>
                                <div class="bf-svc-meta"><?=(int)$s['duration']?> min</div>
                            </div>
                            <div class="bf-svc-price">$<?=number_format((float)$s['price'], 0, ',', '.')?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="bf-actions">
                    <button class="btn btn-next" id="btnStep1Next" disabled onclick="goToStep2()">Siguiente →</button>
                </div>
            </div>

            <!-- Step 2: Select Date & Time -->
            <div class="booking-step" id="step2">
                <h3 style="text-align:center;margin-bottom:20px;color:var(--txt);">Elegí fecha y horario</h3>
                <div class="bf-group">
                    <label class="bf-label">Fecha</label>
                    <input type="date" class="bf-date-input" id="bfDate" min="<?=$tomorrow?>" onchange="cargarHorarios()">
                </div>
                <div id="slotsContainer" class="slots-hint">Seleccioná una fecha para ver los horarios disponibles</div>
                <div class="bf-error hidden" id="step2Error"></div>
                <div class="bf-actions">
                    <button class="btn btn-back" onclick="goToStep1()">← Volver</button>
                    <button class="btn btn-next" id="btnStep2Next" disabled onclick="goToStep3()">Siguiente →</button>
                </div>
            </div>

            <!-- Step 3: Your Info -->
            <div class="booking-step" id="step3">
                <h3 style="text-align:center;margin-bottom:20px;color:var(--txt);">Tus datos</h3>
                <div class="bf-summary" id="step3Summary"></div>
                <div class="bf-row">
                    <div class="bf-group"><label class="bf-label">Nombre *</label><input type="text" class="bf-input" id="bfFirstName" placeholder="Tu nombre" required></div>
                    <div class="bf-group"><label class="bf-label">Apellido</label><input type="text" class="bf-input" id="bfLastName" placeholder="Tu apellido"></div>
                </div>
                <div class="bf-group"><label class="bf-label">WhatsApp *</label><input type="tel" class="bf-input" id="bfPhone" placeholder="Sin 0, sin 15. Ej: 3826403110" required></div>
                <div class="bf-group"><label class="bf-label">Email</label><input type="email" class="bf-input" id="bfEmail" placeholder="tu@email.com"></div>
                <div class="bf-error hidden" id="step3Error"></div>
                <div class="bf-actions">
                    <button class="btn btn-back" onclick="goToStep2()">← Volver</button>
                    <button class="btn btn-next" id="btnConfirmar" onclick="confirmarTurno()">Confirmar turno</button>
                </div>
            </div>

            <!-- Confirmation -->
            <div class="booking-step" id="step4">
                <div class="confirmation-box">
                    <div class="conf-icon">✅</div>
                    <h3>¡Turno reservado!</h3>
                    <p>Te enviamos la confirmación por WhatsApp.</p>
                    <div class="resumen" id="confResumen"></div>
                    <a href="#reserva" class="btn-primary" style="display:inline-flex;margin-top:8px;" onclick="resetBooking();return false;">Reservar otro turno</a>
                </div>
            </div>
        </div>
    </div>
</section>

<section id="ubicacion" class="section">
    <div class="container">
        <div class="section-title fade-in">
            <h2>Ubicación</h2>
            <p>Encontrá nuestro salón</p>
        </div>
        <div class="ubicacion-card fade-in">
            <h3><?=$name?></h3>
            <p><?=$address?></p>
            <a href="https://www.google.com/maps/search/?api=1&query=<?=$mapsQuery?>" target="_blank" class="maps-link">🗺️ Abrir en Google Maps</a>
        </div>
    </div>
</section>

<footer>
    <div class="brand"><?=$name?></div>
    <div class="social">
        <a href="https://wa.me/<?=$whatsapp?>" target="_blank">💬 WhatsApp</a>
        <?php if ($instagram): ?>
        <a href="<?=$instaUrl?>" target="_blank">📷 Instagram</a>
        <?php endif; ?>
    </div>
    <p><?=$address?></p>
    <p style="margin-top:8px;">© <?=date('Y')?> <?=$name?> · Turnos online con TuAhora</p>
</footer>

<div class="lightbox" id="lightbox">
    <span class="lb-close" id="lbClose">&times;</span>
    <span class="lb-nav lb-prev" id="lbPrev">&lsaquo;</span>
    <img src="" alt="" id="lbImg">
    <span class="lb-nav lb-next" id="lbNext">&rsaquo;</span>
</div>

<script>
(function() {
    // ===== Gallery lightbox =====
    var galleryItems = document.querySelectorAll('.gallery-item img');
    var lb = document.getElementById('lightbox');
    var lbImg = document.getElementById('lbImg');
    var lbClose = document.getElementById('lbClose');
    var lbPrev = document.getElementById('lbPrev');
    var lbNext = document.getElementById('lbNext');
    var currentIdx = 0;
    var total = galleryItems.length;

    if (total > 0) {
        galleryItems.forEach(function(img, idx) {
            img.parentElement.addEventListener('click', function() {
                currentIdx = idx;
                showImage(idx);
                lb.classList.add('show');
            });
        });
        lbClose.addEventListener('click', function(e) { e.stopPropagation(); lb.classList.remove('show'); });
        lb.addEventListener('click', function(e) { if (e.target === lb) lb.classList.remove('show'); });
        lbPrev.addEventListener('click', function(e) { e.stopPropagation(); currentIdx = (currentIdx - 1 + total) % total; showImage(currentIdx); });
        lbNext.addEventListener('click', function(e) { e.stopPropagation(); currentIdx = (currentIdx + 1) % total; showImage(currentIdx); });
        document.addEventListener('keydown', function(e) {
            if (!lb.classList.contains('show')) return;
            if (e.key === 'Escape') lb.classList.remove('show');
            if (e.key === 'ArrowLeft') { currentIdx = (currentIdx - 1 + total) % total; showImage(currentIdx); }
            if (e.key === 'ArrowRight') { currentIdx = (currentIdx + 1) % total; showImage(currentIdx); }
        });
    }
    function showImage(idx) {
        var img = galleryItems[idx];
        lbImg.src = img.src;
        lbImg.alt = img.alt;
    }

    // ===== Hamburger menu =====
    var hamburger = document.getElementById('hamburger');
    var navLinks = document.getElementById('navLinks');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('open');
            navLinks.classList.toggle('open');
        });
        navLinks.querySelectorAll('a').forEach(function(a) {
            a.addEventListener('click', function() {
                hamburger.classList.remove('open');
                navLinks.classList.remove('open');
            });
        });
    }

    // ===== Fade-in scroll animation =====
    var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(e) { if (e.isIntersecting) e.target.classList.add('show'); });
    }, { threshold: .1 });
    document.querySelectorAll('.fade-in').forEach(function(el) { observer.observe(el); });

    // ===== BOOKING FORM =====
    var selectedService = null;
    var selectedDate = null;
    var selectedSlot = null;
    var selectedSlotEnd = null;

    // Service selection cards
    document.querySelectorAll('.bf-service-card').forEach(function(card) {
        card.addEventListener('click', function() {
            document.querySelectorAll('.bf-service-card').forEach(function(c) { c.classList.remove('selected'); });
            card.classList.add('selected');
            selectedService = {
                id: card.dataset.id,
                name: card.dataset.name,
                price: parseFloat(card.dataset.price),
                duration: parseInt(card.dataset.duration)
            };
            document.getElementById('btnStep1Next').disabled = false;
        });
    });

    // Navigate between steps
    function showStep(n) {
        document.querySelectorAll('.booking-step').forEach(function(s) { s.classList.remove('active'); });
        document.getElementById('step' + n).classList.add('active');

        var dots = ['stepDot1', 'stepDot2', 'stepDot3'];
        var lines = ['stepLine1', 'stepLine2'];
        dots.forEach(function(id, i) {
            var dot = document.getElementById(id);
            dot.classList.remove('current', 'done');
            if (i + 1 < n) dot.classList.add('done');
            if (i + 1 === n) dot.classList.add('current');
        });
        lines.forEach(function(id, i) {
            var line = document.getElementById(id);
            line.classList.remove('done');
            if (i + 1 < n) line.classList.add('done');
        });

        if (n === 3) actualizarResumen();
    }

    window.goToStep1 = function() { showStep(1); };
    window.goToStep2 = function() {
        if (!selectedService) return;
        showStep(2);
        if (selectedDate) cargarHorarios();
    };
    window.goToStep3 = function() {
        if (!selectedDate || !selectedSlot) return;
        showStep(3);
    };

    window.irAReserva = function(serviceId) {
        var card = document.querySelector('.bf-service-card[data-id="' + serviceId + '"]');
        if (card) card.click();
        showStep(1);
        document.getElementById('reserva').scrollIntoView({behavior:'smooth'});
    };

    // Step 2: Load available slots
    window.cargarHorarios = function() {
        var date = document.getElementById('bfDate').value;
        selectedDate = date;
        var container = document.getElementById('slotsContainer');
        var errorDiv = document.getElementById('step2Error');
        var btnNext = document.getElementById('btnStep2Next');
        errorDiv.classList.add('hidden');
        btnNext.disabled = true;
        selectedSlot = null;
        selectedSlotEnd = null;

        if (!date) {
            container.innerHTML = '<div class="slots-hint">Seleccioná una fecha para ver los horarios disponibles</div>';
            return;
        }
        if (!selectedService) {
            container.innerHTML = '<div class="slots-hint">Primero elegí un servicio</div>';
            return;
        }

        container.innerHTML = '<div class="slots-loading"><div class="spinner"></div><div style="color:var(--txt-sub);font-size:13px;">Buscando horarios...</div></div>';

        var serviceId = selectedService.id;
        var url = 'api/disponibilidad.php?serviceId=' + serviceId + '&date=' + date;

        fetch(url)
        .then(function(r) {
            if (!r.ok) throw new Error('Error del servidor');
            return r.json();
        })
        .then(function(data) {
            if (!Array.isArray(data) || data.length === 0) {
                container.innerHTML = '<div class="slots-hint">No hay horarios disponibles para esta fecha</div>';
                return;
            }
            // EA returns slots as time strings like "HH:mm"
            // Or they might be objects with start/end
            var slots = [];
            if (Array.isArray(data)) {
                data.forEach(function(s) {
                    if (typeof s === 'string') {
                        // Simple time string like "09:00"
                        var end = calcularFin(s, selectedService.duration);
                        slots.push({ time: s, end: end });
                    } else if (s.start || s.time) {
                        var t = s.start || s.time;
                        var e = s.end || calcularFin(t, selectedService.duration);
                        slots.push({ time: t, end: e });
                    }
                });
            }

            if (slots.length === 0) {
                container.innerHTML = '<div class="slots-hint">No hay horarios disponibles para esta fecha</div>';
                return;
            }

            var html = '<div class="slots-grid">';
            slots.forEach(function(s) {
                html += '<button type="button" class="slot-btn" data-time="' + s.time + '" data-end="' + s.end + '" onclick="seleccionarSlot(this,\'' + s.time + '\',\'' + s.end + '\')">' + s.time.substring(0,5) + '</button>';
            });
            html += '</div>';
            container.innerHTML = html;
        })
        .catch(function() {
            container.innerHTML = '<div class="slots-hint" style="color:#e74c3c;">Error al cargar horarios. Intentá de nuevo.</div>';
        });
    };

    function calcularFin(start, duration) {
        var parts = start.split(':');
        var d = new Date();
        d.setHours(parseInt(parts[0]), parseInt(parts[1]), 0);
        d.setMinutes(d.getMinutes() + duration);
        return String(d.getHours()).padStart(2,'0') + ':' + String(d.getMinutes()).padStart(2,'0');
    }

    window.seleccionarSlot = function(el, time, end) {
        document.querySelectorAll('.slot-btn').forEach(function(b) { b.classList.remove('selected'); });
        el.classList.add('selected');
        selectedSlot = time;
        selectedSlotEnd = end;
        document.getElementById('btnStep2Next').disabled = false;
    };

    // Step 3: Update summary
    function actualizarResumen() {
        if (!selectedService || !selectedDate || !selectedSlot) return;
        var dateParts = selectedDate.split('-');
        var fechaFormateada = dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0];
        var html = '';
        html += '<div class="bf-summary-line"><span class="bf-summary-label">Servicio: </span><span class="bf-summary-val">' + selectedService.name + '</span></div>';
        html += '<div class="bf-summary-line"><span class="bf-summary-label">Fecha: </span><span class="bf-summary-val">' + fechaFormateada + '</span></div>';
        html += '<div class="bf-summary-line"><span class="bf-summary-label">Horario: </span><span class="bf-summary-val">' + selectedSlot.substring(0,5) + ' hs</span></div>';
        html += '<div class="bf-summary-line"><span class="bf-summary-label">Duración: </span><span class="bf-summary-val">' + selectedService.duration + ' min</span></div>';
        html += '<div class="bf-summary-line"><span class="bf-summary-label">Precio: </span><span class="bf-summary-val" style="color:var(--pri);">$' + selectedService.price.toLocaleString('es-AR') + '</span></div>';
        document.getElementById('step3Summary').innerHTML = html;
    }

    // Step 4: Confirm and submit
    window.confirmarTurno = function() {
        var firstName = document.getElementById('bfFirstName').value.trim();
        var lastName = document.getElementById('bfLastName').value.trim();
        var phone = document.getElementById('bfPhone').value.trim();
        var email = document.getElementById('bfEmail').value.trim();
        var errorDiv = document.getElementById('step3Error');
        var btn = document.getElementById('btnConfirmar');

        errorDiv.classList.add('hidden');

        if (!firstName) { errorDiv.textContent = 'Ingresá tu nombre'; errorDiv.classList.remove('hidden'); return; }
        if (!phone) { errorDiv.textContent = 'Ingresá tu número de WhatsApp'; errorDiv.classList.remove('hidden'); return; }

        btn.disabled = true;
        btn.textContent = 'Reservando...';

        var payload = {
            serviceId: parseInt(selectedService.id),
            date: selectedDate,
            time: selectedSlot,
            firstName: firstName,
            lastName: lastName || '',
            phone: phone,
            email: email || ''
        };

        fetch('api/crear-turno.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(function(r) {
            if (!r.ok) return r.json().then(function(err) { throw new Error(err.error || 'Error al crear el turno'); });
            return r.json();
        })
        .then(function(data) {
            var resumen = document.getElementById('confResumen');
            var dateParts = selectedDate.split('-');
            var fechaFormateada = dateParts[2] + '/' + dateParts[1] + '/' + dateParts[0];
            resumen.innerHTML = '<strong>' + selectedService.name + '</strong><br>' + fechaFormateada + ' a las ' + selectedSlot.substring(0,5) + ' hs<br><span style="color:var(--pri);">$' + selectedService.price.toLocaleString('es-AR') + '</span>';
            showStep(4);
        })
        .catch(function(err) {
            errorDiv.textContent = err.message || 'Error al crear el turno. Intentá de nuevo.';
            errorDiv.classList.remove('hidden');
        })
        .finally(function() {
            btn.disabled = false;
            btn.textContent = 'Confirmar turno';
        });
    };

    window.resetBooking = function() {
        selectedService = null;
        selectedDate = null;
        selectedSlot = null;
        selectedSlotEnd = null;
        document.querySelectorAll('.bf-service-card').forEach(function(c) { c.classList.remove('selected'); });
        document.getElementById('bfDate').value = '';
        document.getElementById('bfFirstName').value = '';
        document.getElementById('bfLastName').value = '';
        document.getElementById('bfPhone').value = '';
        document.getElementById('bfEmail').value = '';
        document.getElementById('slotsContainer').innerHTML = '<div class="slots-hint">Seleccioná una fecha para ver los horarios disponibles</div>';
        document.getElementById('step3Summary').innerHTML = '';
        document.getElementById('btnStep1Next').disabled = true;
        document.getElementById('btnStep2Next').disabled = true;
        document.querySelectorAll('.step-bar .step-dot').forEach(function(d, i) { d.classList.remove('done','current'); if (i === 0) d.classList.add('current'); });
        document.querySelectorAll('.step-bar .step-line').forEach(function(l) { l.classList.remove('done'); });
        showStep(1);
    };
})();
</script>
</body>
</html>
