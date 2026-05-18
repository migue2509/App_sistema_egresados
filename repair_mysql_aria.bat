@echo off
REM repair_mysql_aria.bat
REM Backup de datos, reparación Aria y borrado de logs de Aria para XAMPP

set "XAMPP_PATH=C:\xampp"
set "MYSQL_DATA=%XAMPP_PATH%\mysql\data"
set "MYSQL_BIN=%XAMPP_PATH%\mysql\bin"

if not exist "%MYSQL_DATA%" (
  echo ERROR: No se encontro el directorio %MYSQL_DATA%
  pause
  exit /b 1
)

nfor /f "tokens=2 delims==." %%I in ('wmic os get LocalDateTime /value ^| find "="') do set "dt=%%I"
set "TIMESTAMP=%dt:~0,8%_%dt:~8,6%"
set "BACKUP=%MYSQL_DATA%_backup_%TIMESTAMP%"

echo ================================================
echo Creando copia de seguridad de MySQL en:
echo   %BACKUP%
echo ================================================
robocopy "%MYSQL_DATA%" "%BACKUP%" /MIR /NFL /NDL /NJH /NJS /nc /ns /np
if errorlevel 8 (
  echo ERROR: Fallo la copia de seguridad. Verifica permisos y espacio en disco.
  pause
  exit /b 1
)

echo.
echo ================================================
echo Reparando tablas Aria (*.MAI)...
echo ================================================
for /r "%MYSQL_DATA%" %%F in (*.MAI) do (
  echo Reparando: %%F
  "%MYSQL_BIN%\aria_chk.exe" -r "%%F"
)
echo.
echo ================================================
echo Eliminando archivos de log de Aria...
echo ================================================
del /Q "%MYSQL_DATA%\aria_log.*"

echo.
echo Reparacion completada.
echo 1) Verifica que no haya errores en la salida anterior.
echo 2) Inicia XAMPP Control Panel como administrador y arranca MySQL.
echo 3) Si el error persiste, ejecuta el script PowerShell o revisa el log mysql_error.log.
pause
