param([switch]$Quiet)

$ErrorActionPreference = "Stop"
$script:exitCode = 0
$script:results = @()

function Write-Color {
  param([string]$Text, [string]$Color = "White")
  if (-not $Quiet) {
    Write-Host $Text -ForegroundColor $Color
  }
}

function Check-Container {
  param([string]$Name)
  $container = docker ps --filter "name=$Name" --format "{{.Names}}" 2>$null
  if ($container -match $Name) {
    $script:results += @{ Name = $Name; Status = "UP"; Color = "Green" }
    Write-Color "[OK] $Name esta UP" "Green"
  } else {
    $script:results += @{ Name = $Name; Status = "DOWN"; Color = "Red" }
    Write-Color "[FAIL] $Name esta DOWN" "Red"
    $script:exitCode = 1
  }
}

function Check-Endpoint {
  param([string]$Name, [string]$Url)
  try {
    $response = Invoke-WebRequest -Uri $Url -TimeoutSec 5 -UseBasicParsing
    if ($response.StatusCode -eq 200 -or $response.StatusCode -eq 204 -or $response.StatusCode -eq 302) {
      $script:results += @{ Name = $Name; Status = "UP"; Color = "Green" }
      Write-Color "[OK] $Name responde ($Url)" "Green"
    } else {
      $script:results += @{ Name = $Name; Status = "UNEXPECTED"; Color = "Yellow" }
      Write-Color "[WARN] $Name respondio con codigo $($response.StatusCode) ($Url)" "Yellow"
    }
  } catch {
    $script:results += @{ Name = $Name; Status = "DOWN"; Color = "Red" }
    Write-Color "[FAIL] $Name no responde ($Url)" "Red"
    $script:exitCode = 1
  }
}

Write-Color "=== TeToca Stack Verification ===" "Cyan"
Write-Color ""

# 1. Check Docker
Write-Color "--- Docker ---" "Yellow"
try {
  $dockerInfo = docker info --format "{{.ServerVersion}}" 2>$null
  if ($dockerInfo) {
    Write-Color "[OK] Docker esta corriendo (v$dockerInfo)" "Green"
  } else {
    Write-Color "[FAIL] Docker no esta disponible" "Red"
    $script:exitCode = 1
  }
} catch {
  Write-Color "[FAIL] Docker no esta disponible" "Red"
  $script:exitCode = 1
}

Write-Color ""

# 2. Check Containers
Write-Color "--- Contenedores ---" "Yellow"
Check-Container -Name "easyappointments"
Check-Container -Name "ea-mysql"
Check-Container -Name "n8n"
Check-Container -Name "mailpit"
Check-Container -Name "redis"
Check-Container -Name "tetoca_baileys"

Write-Color ""

# 3. Check Endpoints
Write-Color "--- Endpoints ---" "Yellow"
Check-Endpoint -Name "Easy!Appointments (port 8080)" -Url "http://localhost:8080"
Check-Endpoint -Name "n8n" -Url "http://localhost:5678/healthz"
Check-Endpoint -Name "Baileys" -Url "http://localhost:3001/health"
Check-Endpoint -Name "Mailpit" -Url "http://localhost:8025"

Write-Color ""

# 4. Summary
Write-Color "--- Resumen ---" "Yellow"
$total = $script:results.Count
$up = ($script:results | Where-Object { $_.Status -eq "UP" }).Count
$down = ($script:results | Where-Object { $_.Status -eq "DOWN" }).Count
Write-Color "Total: $total | UP: $up | DOWN: $down" $(if ($down -gt 0) { "Red" } else { "Green" })

Write-Color ""

if ($script:exitCode -eq 0 -and -not $Quiet) {
  Write-Color "=== Todo OK - URLs de acceso ===" "Green"
  Write-Color "  Easy!Appointments: http://localhost:8080"  "White"
  Write-Color "  EA Backend:        http://localhost:8080/index.php/backend" "White"
  Write-Color "  n8n:               http://localhost:5678"  "White"
  Write-Color "  Baileys:           http://localhost:3001"  "White"
  Write-Color "  Mailpit:           http://localhost:8025"  "White"
}

exit $script:exitCode
