@echo off
setlocal
title AquaControl - Lanzador
REM Inicia MySQL (XAMPP), la pagina y el puente USB del ESP32, y abre el panel.
REM Para apagar: cerra las ventanas "AquaControl - Servidor" y "AquaControl - ESP32".

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

echo [4/4] Iniciando el puente USB del ESP32 (cerra el Monitor Serie del Arduino IDE)...
if not exist "%PROJ%firmware\config.local.ps1" (
  echo       [AVISO] Falta firmware\config.local.ps1 con la API key: copia config.example.ps1.
)
start "AquaControl - ESP32" powershell -NoProfile -ExecutionPolicy Bypass -NoExit -File "%PROJ%firmware\puente_usb.ps1"

start "" "http://localhost:8080/dashboard"
echo.
echo Listo. Deja abiertas las ventanas "AquaControl - Servidor" y "AquaControl - ESP32".
timeout /t 5 >nul
endlocal
