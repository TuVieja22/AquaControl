# ============================================================================
#  AquaControl - Puente USB (ESP32 <-> pagina)
# ----------------------------------------------------------------------------
#  El ESP32 esta conectado por USB a esta PC (sin WiFi). Este script:
#    - lee la temperatura que imprime el ESP32 ("T:24.56") y la envia a la
#      pagina (POST /dashboard/api/data) para verla en tiempo real;
#    - consulta la cola de ordenes (GET /dashboard/api/commands): boton
#      "Alimentar ahora" y horarios programados;
#    - le pasa cada orden al ESP32 ("FEED:<id>:<gramos>") y, cuando el ESP32
#      responde "ACK:<id>:OK", confirma a la pagina para que quede en el historial.
#
#  Configuracion: firmware/config.local.ps1 (copiar de config.example.ps1).
#  Uso:   powershell -ExecutionPolicy Bypass -File firmware\puente_usb.ps1
#  IMPORTANTE: cerra el Monitor Serie del Arduino IDE (solo un programa puede
#  usar el puerto COM a la vez).
# ============================================================================

$ErrorActionPreference = 'Stop'
$inv = [System.Globalization.CultureInfo]::InvariantCulture

# ---------------------- Configuracion ---------------------------------------
$PORT = 'auto'                     # 'auto' detecta el adaptador USB del ESP32 (CP210x / CH340)
$BAUD = 115200
$BASE_URL = 'http://127.0.0.1:8080'
$API_KEY = ''                      # API key del dispositivo (se genera en Dispositivos)
$INTERVALO_LECTURA_S = 5           # cada cuanto se guarda una lectura en la pagina
$INTERVALO_ORDENES_S = 2           # cada cuanto se consulta si hay que alimentar
$TIMEOUT_ALIMENTACION_S = 60       # si el ESP32 no confirma en este tiempo, se marca fallida

$configLocal = Join-Path $PSScriptRoot 'config.local.ps1'
if (Test-Path $configLocal) { . $configLocal }

if ($API_KEY -eq '') {
    Write-Host 'Falta la API key. Copia firmware\config.example.ps1 como config.local.ps1 y completala.' -ForegroundColor Red
    exit 1
}

$headers = @{ 'X-Device-Key' = $API_KEY }
# ----------------------------------------------------------------------------

function Log([string]$texto, [string]$color = 'Gray') {
    Write-Host ('{0}  {1}' -f (Get-Date -Format 'HH:mm:ss'), $texto) -ForegroundColor $color
}

function Find-EspPort {
    $candidato = Get-CimInstance Win32_PnPEntity -ErrorAction SilentlyContinue |
        Where-Object { $_.Name -match 'CP210|CH340|CH910|CH9102|USB.*UART|USB Serial|Espressif' -and $_.Name -match '\((COM\d+)\)' } |
        Select-Object -First 1
    if ($candidato -and $candidato.Name -match '\((COM\d+)\)') { return $matches[1] }
    return $null
}

function Open-Port {
    while ($true) {
        $nombre = if ($PORT -eq 'auto') { Find-EspPort } else { $PORT }
        if (-not $nombre) {
            Log 'No encuentro el ESP32 por USB. Reintento en 3 s... (esta enchufado?)' 'Yellow'
            Start-Sleep -Seconds 3
            continue
        }

        $p = New-Object System.IO.Ports.SerialPort($nombre, $BAUD, 'None', 8, 'One')
        $p.ReadTimeout = 200
        $p.NewLine = "`n"
        $p.DtrEnable = $false
        $p.RtsEnable = $false
        try {
            $p.Open()
            Start-Sleep -Milliseconds 300
            $p.DiscardInBuffer()
            Log "Conectado al ESP32 en $nombre @ $BAUD" 'Green'
            return $p
        } catch {
            Log "No pude abrir $nombre ($($_.Exception.Message)). Cerraste el Monitor Serie? Reintento en 3 s..." 'Yellow'
            Start-Sleep -Seconds 3
        }
    }
}

function Invoke-Api([string]$method, [string]$path, $body = $null) {
    $params = @{ Method = $method; Uri = "$BASE_URL$path"; Headers = $headers; TimeoutSec = 5 }
    if ($null -ne $body) {
        $params.ContentType = 'application/json'
        $params.Body = ($body | ConvertTo-Json -Compress)
    }
    return Invoke-RestMethod @params
}

function Report-ApiError([string]$contexto, $err) {
    $status = $null
    try { $status = [int]$err.Exception.Response.StatusCode } catch {}
    if ($status -eq 401) {
        Log "$contexto -> 401: la API key no es valida (se regenero o se revoco?)." 'Red'
    } elseif (((Get-Date) - $script:ultimoErrorApi).TotalSeconds -ge 30) {
        Log "$contexto -> $($err.Exception.Message) (la pagina esta levantada en $BASE_URL?)" 'Red'
        $script:ultimoErrorApi = Get-Date
    }
}

function Send-Ack([long]$id, [string]$estado, [string]$mensaje = $null) {
    $body = @{ estado = $estado }
    if ($mensaje) { $body.mensaje = $mensaje }
    try {
        Invoke-Api 'POST' "/dashboard/api/commands/$id/ack" $body | Out-Null
        $color = if ($estado -eq 'ejecutado') { 'Cyan' } else { 'Red' }
        Log "Orden #$id -> $estado $mensaje" $color
    } catch {
        Report-ApiError "Confirmando orden #$id" $_
    }
}

Write-Host 'AquaControl - Puente USB' -ForegroundColor Yellow
Write-Host "Pagina: $BASE_URL  |  lecturas cada $INTERVALO_LECTURA_S s  |  ordenes cada $INTERVALO_ORDENES_S s"
Write-Host "Ctrl+C para detener.`n"

$port = Open-Port
$ultimoErrorApi = (Get-Date).AddMinutes(-5)
$ultimaLinea = Get-Date
$ultimaTemp = $null
$tempNueva = $false
$ultimoEnvio = (Get-Date).AddSeconds(-$INTERVALO_LECTURA_S)
$ultimaConsulta = (Get-Date).AddSeconds(-$INTERVALO_ORDENES_S)
$pendientes = @{}   # id de orden -> momento en que se le envio al ESP32

while ($true) {
    # ---------- 1) Leer lo que manda el ESP32 ----------
    try {
        $linea = $port.ReadLine().Trim()
    } catch [System.TimeoutException] {
        $linea = $null
    } catch {
        Log "Se perdio la conexion con el ESP32: $($_.Exception.Message)" 'Red'
        foreach ($id in @($pendientes.Keys)) { Send-Ack $id 'fallido' 'Se desconecto el ESP32' }
        $pendientes.Clear()
        try { $port.Close() } catch {}
        $port = Open-Port
        continue
    }

    if ($linea) {
        $ultimaLinea = Get-Date
        if ($linea -match '^T:(-?\d+(?:\.\d+)?)$') {
            $ultimaTemp = [double]::Parse($matches[1], $inv)
            $tempNueva = $true
        } elseif ($linea -eq 'T:ERR') {
            Log 'El ESP32 no detecta el sensor DS18B20 (revisar cables / resistencia de 4.7k).' 'Yellow'
        } elseif ($linea -match '^ACK:(\d+):OK$') {
            $id = [long]$matches[1]
            $pendientes.Remove($id)
            Send-Ack $id 'ejecutado'
        } elseif ($linea -match '^ACK:(\d+):ERR:(.*)$') {
            $id = [long]$matches[1]
            $pendientes.Remove($id)
            Send-Ack $id 'fallido' $matches[2]
        } elseif ($linea -match '^[\x20-\x7E]+$') {
            Log "[esp32] $linea" 'DarkGray'
        }
        # Lineas con bytes no imprimibles: ruido del arranque del ESP32, se ignoran.
    }

    $ahora = Get-Date
    $espVivo = ($ahora - $ultimaLinea).TotalSeconds -lt 10

    # ---------- 2) Enviar la temperatura a la pagina ----------
    if ($tempNueva -and ($ahora - $ultimoEnvio).TotalSeconds -ge $INTERVALO_LECTURA_S) {
        $ultimoEnvio = $ahora
        $tempNueva = $false
        try {
            Invoke-Api 'POST' '/dashboard/api/data' @{ temperatura = [math]::Round($ultimaTemp, 2) } | Out-Null
            Log ('Temperatura {0} C -> pagina' -f $ultimaTemp.ToString('0.00', $inv)) 'Green'
        } catch {
            Report-ApiError 'Enviando temperatura' $_
        }
    }

    # ---------- 3) Pedir ordenes (boton / horario) solo si el ESP32 responde ----------
    if ($espVivo -and ($ahora - $ultimaConsulta).TotalSeconds -ge $INTERVALO_ORDENES_S) {
        $ultimaConsulta = $ahora
        try {
            $respuesta = Invoke-Api 'GET' '/dashboard/api/commands'
            foreach ($orden in @($respuesta.comandos)) {
                if ($null -eq $orden) { continue }
                $id = [long]$orden.id
                if ($orden.accion -ne 'alimentar') {
                    Send-Ack $id 'fallido' "Accion no soportada: $($orden.accion)"
                    continue
                }
                $gramos = ([double]$orden.gramos).ToString('0.##', $inv)
                $port.WriteLine("FEED:${id}:$gramos")
                $pendientes[$id] = $ahora
                Log "Orden #$id ($($orden.origen)): alimentar $gramos g -> ESP32" 'Magenta'
            }
        } catch {
            Report-ApiError 'Consultando ordenes' $_
        }
    }

    # ---------- 4) Ordenes sin respuesta del ESP32 ----------
    foreach ($id in @($pendientes.Keys)) {
        if (($ahora - $pendientes[$id]).TotalSeconds -ge $TIMEOUT_ALIMENTACION_S) {
            $pendientes.Remove($id)
            Send-Ack $id 'fallido' 'El ESP32 no confirmo la alimentacion'
        }
    }
}
