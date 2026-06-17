param(
    [switch]$Quiet,
    [string]$NtfyTopic = "",
    [string]$NtfyServer = "https://ntfy.sh"
)

$ErrorActionPreference = "Stop"
$script:exitCode = 0
$script:failures = @()

function Write-Color {
    param([string]$Text, [string]$Color = "White")
    if (-not $Quiet) {
        Write-Host $Text -ForegroundColor $Color
    }
}

function Send-Alert {
    param([string]$Message)
    if ($NtfyTopic) {
        try {
            $body = @{ topic = $NtfyTopic; message = $Message; title = "TeToca - Alerta"; priority = 4; tags = @("warning") } | ConvertTo-Json
            Invoke-RestMethod -Uri "$NtfyServer/$NtfyTopic" -Method Put -Body $body -ContentType "application/json" -ErrorAction SilentlyContinue | Out-Null
            Write-Color "[ALERTA] Notificacion enviada a ntfy.sh/$NtfyTopic" "Yellow"
        } catch {
            Write-Color "[WARN] No se pudo enviar alerta a ntfy.sh: $($_.Exception.Message)" "Yellow"
        }
    }
}

function Check-Container {
    param([string]$Name)
    $container = docker ps --filter "name=$Name" --format "{{.Names}}" 2>$null
    if ($container -match $Name) {
        Write-Color "[OK] $Name esta UP" "Green"
    } else {
        Write-Color "[FAIL] $Name esta DOWN" "Red"
        $script:failures += "Contenedor: $Name"
        $script:exitCode = 1
    }
}

function Check-Endpoint {
    param([string]$Name, [string]$Url, [int]$ExpectedStatus = 200)
    try {
        $response = Invoke-WebRequest -Uri $Url -TimeoutSec 10 -UseBasicParsing
        if ($response.StatusCode -eq $ExpectedStatus -or $response.StatusCode -eq 204 -or $response.StatusCode -eq 302) {
            Write-Color "[OK] $Name responde ($Url)" "Green"
        } else {
            Write-Color "[WARN] $Name respondio con codigo $($response.StatusCode) ($Url)" "Yellow"
        }
    } catch {
        Write-Color "[FAIL] $Name no responde ($Url)" "Red"
        $script:failures += "Endpoint: $Name ($Url)"
        $script:exitCode = 1
    }
}

Write-Color "=== TeToca Health Check ===" "Cyan"
Write-Color "Fecha: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')" "Cyan"
Write-Color ""

# 1. Docker
Write-Color "--- Docker ---" "Yellow"
try {
    $dockerVersion = docker info --format "{{.ServerVersion}}" 2>$null
    if ($dockerVersion) {
        Write-Color "[OK] Docker v$dockerVersion" "Green"
    } else {
        Write-Color "[FAIL] Docker no responde" "Red"
        $script:failures += "Docker no disponible"
        $script:exitCode = 1
    }
} catch {
    Write-Color "[FAIL] Docker no responde" "Red"
    $script:failures += "Docker no disponible"
    $script:exitCode = 1
}
Write-Color ""

# 2. Containers
Write-Color "--- Contenedores ---" "Yellow"
Check-Container "easyappointments"
Check-Container "ea-mysql"
Check-Container "n8n"
Check-Container "tetoca_baileys"
Check-Container "redis"
Check-Container "mailpit"
Write-Color ""

# 3. Endpoints
Write-Color "--- Endpoints ---" "Yellow"
Check-Endpoint -Name "Easy!Appointments" -Url "http://localhost:8080"
Check-Endpoint -Name "n8n" -Url "http://localhost:5678/healthz"
Check-Endpoint -Name "Baileys" -Url "http://localhost:3001/health"
Check-Endpoint -Name "Mailpit UI" -Url "http://localhost:8025"
Write-Color ""

# 4. Result
Write-Color "--- Resultado ---" "Yellow"
if ($script:exitCode -eq 0) {
    Write-Color "TODO OK" "Green"
} else {
    Write-Color "FALLOS detectados:" "Red"
    foreach ($f in $script:failures) {
        Write-Color "  - $f" "Red"
    }
    $alertMsg = "Fallos en TeToca: " + ($script:failures -join ", ")
    Send-Alert -Message $alertMsg
}

exit $script:exitCode
