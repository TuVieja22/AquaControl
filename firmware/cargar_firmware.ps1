# ============================================================================
#  AquaControl - Compilar y cargar el firmware al ESP32
# ----------------------------------------------------------------------------
#  Compila el sketch con el arduino-cli que trae el Arduino IDE y lo carga por
#  USB. Esta placa no entra sola en modo de carga, asi que el script ESPERA a
#  que lo pongas vos:
#     mantene BOOT, toca EN y solta BOOT   (o: desenchufa, mantene BOOT,
#     enchufa y solta BOOT a los 3 s)
#
#  Uso:
#     powershell -ExecutionPolicy Bypass -File firmware\cargar_firmware.ps1            (WiFi)
#     powershell -ExecutionPolicy Bypass -File firmware\cargar_firmware.ps1 -Modo usb  (USB)
#
#  Cerra el Monitor Serie del Arduino IDE y el puente USB antes de cargar.
# ============================================================================
param(
    [ValidateSet('wifi', 'usb')]
    [string]$Modo = 'wifi',
    [string]$Puerto = 'auto',
    [int]$MinutosEspera = 10
)

$ErrorActionPreference = 'Stop'
$sketchNombre = if ($Modo -eq 'wifi') { 'aquacontrol_esp32' } else { 'aquacontrol_esp32_usb' }
$sketch = Join-Path $PSScriptRoot $sketchNombre
$build = Join-Path $sketch 'build'   # ignorado por git

$cli = Join-Path $env:LOCALAPPDATA 'Programs\Arduino IDE\resources\app\lib\backend\resources\arduino-cli.exe'
if (-not (Test-Path $cli)) {
    $cmd = Get-Command arduino-cli -ErrorAction SilentlyContinue
    if ($cmd) { $cli = $cmd.Source } else { Write-Host 'No encuentro arduino-cli (instala el Arduino IDE 2).' -ForegroundColor Red; exit 1 }
}

$esptool = Get-ChildItem (Join-Path $env:LOCALAPPDATA 'Arduino15\packages\esp32\tools\esptool_py') -Recurse -Filter esptool.exe -ErrorAction SilentlyContinue |
    Sort-Object FullName -Descending | Select-Object -First 1 -ExpandProperty FullName
if (-not $esptool) { Write-Host 'No encuentro esptool: instala la placa "esp32" en el Arduino IDE.' -ForegroundColor Red; exit 1 }

if ($Modo -eq 'wifi' -and -not (Test-Path (Join-Path $sketch 'secrets.h'))) {
    Write-Host 'Falta firmware\aquacontrol_esp32\secrets.h: copia secrets.example.h y completalo.' -ForegroundColor Red
    exit 1
}

function Find-EspPort {
    $d = Get-CimInstance Win32_PnPEntity -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match 'CP210|CH340|CH910|USB.*UART' -and $_.Name -match '\((COM\d+)\)' } | Select-Object -First 1
    if ($d -and $d.Name -match '\((COM\d+)\)') { return $matches[1] }
    return $null
}

Write-Host "Compilando $sketchNombre ..." -ForegroundColor Yellow
& $cli compile --fqbn esp32:esp32:esp32 --output-dir $build $sketch
if ($LASTEXITCODE -ne 0) { Write-Host 'Error de compilacion.' -ForegroundColor Red; exit 1 }
$bin = Join-Path $build "$sketchNombre.ino.merged.bin"

$limite = (Get-Date).AddMinutes($MinutosEspera)
while ((Get-Date) -lt $limite) {
    $port = if ($Puerto -eq 'auto') { Find-EspPort } else { $Puerto }
    if (-not $port) { Write-Host 'Esperando que conectes el ESP32 por USB...'; Start-Sleep -Seconds 2; continue }

    Write-Host "`nESP32 en $port. Ponelo en modo de carga: mantene BOOT, toca EN y solta BOOT." -ForegroundColor Cyan
    & $esptool --chip esp32 --port $port --baud 460800 --before no-reset --after hard-reset --connect-attempts 600 write-flash -z 0x0 $bin
    if ($LASTEXITCODE -eq 0) {
        Write-Host "`nFirmware cargado en $port. El ESP32 ya se reinicio con el programa nuevo." -ForegroundColor Green
        exit 0
    }
    Write-Host 'No se pudo cargar; reintento en 3 s...' -ForegroundColor Yellow
    Start-Sleep -Seconds 3
}
Write-Host "Se agoto el tiempo ($MinutosEspera min) sin poder cargar." -ForegroundColor Red
exit 1
