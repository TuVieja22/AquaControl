@echo off
setlocal
title AquaControl - Lanzador
REM Inicia MySQL (XAMPP) y la pagina, y abre el panel.
REM Para apagar: cerra la ventana "AquaControl - Servidor" (y "AquaControl - ESP32" en modo usb).

REM Modo de conexion del ESP32:
REM   wifi = el ESP32 habla directo con la pagina (firmware\aquacontrol_esp32)
REM   usb  = el ESP32 va por cable y hace falta el puente (firmware\aquacontrol_esp32_usb)
set "MODO=wifi"

set "PROJ=%~dp0"
cd /d "%PROJ%"

echo [1/4] Cerrando instancias anteriores (libera el puerto COM y el 8080)...
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'powershell.exe' -and $_.CommandLine -like '*puente_usb*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }" >nul 2>&1
powershell -NoProfile -Command "Get-CimInstance Win32_Process | Where-Object { $_.Name -eq 'php.exe' -and $_.CommandLine -like '*spark serve*' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }" >nul 2>&1

echo [2/4] Verificando MySQL...
netstat -ano | findstr ":3306" | findstr "LISTENING" >nul
if errorlevel 1 (
  if exist "C:\xampp\mysql_start.bat" (
    start "MySQL (XAMPP)" /min "C:\xampp\mysql_start.bat"
    echo       Esperando a MySQL...
    timeout /t 6 >nul
  ) else (
    echo       [AVISO] No encontre XAMPP en C:\xampp: inicia MySQL a mano.
  )
) else (
  echo       MySQL ya estaba corriendo.
)

echo [3/4] Iniciando la pagina en http://localhost:8080 ...
start "AquaControl - Servidor" cmd /k php spark serve --host 0.0.0.0 --port 8080
timeout /t 3 >nul

if /i "%MODO%"=="usb" (
  echo [4/4] Iniciando el puente USB del ESP32 ^(cerra el Monitor Serie del Arduino IDE^)...
  if not exist "%PROJ%firmware\config.local.ps1" (
    echo       [AVISO] Falta firmware\config.local.ps1 con la API key: copia config.example.ps1.
  )
  start "AquaControl - ESP32" powershell -NoProfile -ExecutionPolicy Bypass -NoExit -File "%PROJ%firmware\puente_usb.ps1"
) else (
  echo [4/4] Modo WiFi: el ESP32 se conecta solo a la pagina ^(no hace falta el puente^).
)

start "" "http://localhost:8080/dashboard"
echo.
echo Listo. Deja abierta la ventana "AquaControl - Servidor".
timeout /t 5 >nul
endlocal
