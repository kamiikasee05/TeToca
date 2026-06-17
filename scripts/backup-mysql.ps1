param(
    [string]$BackupDir = "E:\TUAHORA\backups",
    [int]$RetentionDays = 30,
    [string]$ContainerName = "ea-mysql",
    [string]$DbName = "easyappointments",
    [string]$DbUser = "ea_user",
    [string]$DbPassword = $env:DB_PASSWORD
)

$ErrorActionPreference = "Stop"
$timestamp = Get-Date -Format "yyyy-MM-dd"
$backupFile = "$BackupDir\ea-backup-$timestamp.sql"
$compressedFile = "$backupFile.gz"

if (-not (Test-Path -LiteralPath $BackupDir)) {
    New-Item -ItemType Directory -Path $BackupDir -Force | Out-Null
}

Write-Host "[BACKUP] Iniciando backup de $DbName en $ContainerName..." -ForegroundColor Cyan

$result = docker exec $ContainerName mysqldump --single-transaction --routines --triggers --events -u $DbUser -p$DbPassword $DbName 2>$null

if (-not $result) {
    Write-Host "[ERROR] No se pudo realizar el mysqldump. Verifica que el contenedor '$ContainerName' este corriendo." -ForegroundColor Red
    exit 1
}

$result | Out-File -FilePath $backupFile -Encoding utf8
Write-Host "[OK] Backup SQL guardado: $backupFile" -ForegroundColor Green

if (Test-Path -LiteralPath $compressedFile) {
    Remove-Item -LiteralPath $compressedFile -Force
}

$currentLocation = Get-Location
try {
    Set-Location -LiteralPath $BackupDir
    & "C:\Program Files\7-Zip\7z.exe" a -tgzip "$compressedFile" "$backupFile" -y -bso0 -bsp0 2>$null
    if ($LASTEXITCODE -eq 0) {
        Remove-Item -LiteralPath $backupFile -Force
        Write-Host "[OK] Backup comprimido: $compressedFile" -ForegroundColor Green
    } else {
        Write-Host "[WARN] No se pudo comprimir con 7-Zip. Se intenta con gzip nativo..." -ForegroundColor Yellow
        Set-Location -LiteralPath $currentLocation
        & gzip -f $backupFile 2>$null
        if (Test-Path -LiteralPath $compressedFile) {
            Write-Host "[OK] Backup comprimido (gzip): $compressedFile" -ForegroundColor Green
        }
    }
} finally {
    Set-Location -LiteralPath $currentLocation
}

$size = (Get-Item -LiteralPath $compressedFile).Length / 1MB
Write-Host "[INFO] Tamano: $([math]::Round($size, 2)) MB" -ForegroundColor Gray

$oldFiles = Get-ChildItem -LiteralPath $BackupDir -Filter "ea-backup-*.sql.gz" | Where-Object {
    $_.LastWriteTime -lt (Get-Date).AddDays(-$RetentionDays)
}

foreach ($old in $oldFiles) {
    Remove-Item -LiteralPath $old.FullName -Force
    Write-Host "[PRUNE] Backup antiguo eliminado: $($old.Name)" -ForegroundColor Yellow
}

Write-Host "[OK] Backup completado. Retencion: $RetentionDays dias." -ForegroundColor Green
exit 0
