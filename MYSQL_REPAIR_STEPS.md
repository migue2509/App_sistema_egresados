# Reparación de MySQL en XAMPP

Este repositorio contiene scripts para automatizar la reparación de tablas Aria y los logs de MySQL en XAMPP.

## Archivos

- `repair_mysql_aria.bat` — script CMD para Windows.
- `repair_mysql_aria.ps1` — script PowerShell para Windows.

## Pasos rápidos

1. Cerrar XAMPP y detener MySQL.
2. Abrir `CMD` o PowerShell como administrador.
3. Ejecutar uno de estos scripts:
   - En CMD:
     ```cmd
     repair_mysql_aria.bat
     ```
   - En PowerShell:
     ```powershell
     .\repair_mysql_aria.ps1
     ```
4. Revisar la salida en pantalla.
5. Si no hay errores, iniciar XAMPP Control Panel como administrador y arrancar MySQL.

## Qué hace el script

1. Crea una copia de seguridad de `C:\xampp\mysql\data`.
2. Ejecuta `aria_chk.exe -r` sobre todas las tablas Aria (`*.MAI`).
3. Elimina los archivos de log `aria_log.*`.
4. Te indica que vuelvas a intentar iniciar MySQL.

## Si MySQL sigue fallando

- Revisa `C:\xampp\mysql\data\mysql_error.log`.
- Ejecuta `mysqld --console` desde `C:\xampp\mysql\bin` para ver el error en tiempo real.
- Si hay error con `mysql.plugin`, puede haber corrupción en la base de datos del sistema.

## Aviso

No borres archivos `ibdata1`, `ib_logfile0`, `ib_logfile1` ni los `.ibd` de tus tablas sin antes tener copia de seguridad.
