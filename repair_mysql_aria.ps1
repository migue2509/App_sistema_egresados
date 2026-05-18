<#
repair_mysql_aria.ps1
Script para respaldo y reparación de tablas Aria en XAMPP MySQL.
Ejecutar como administrador.
#>
[CmdletBinding()]
param(
    [string]$XamppPath = 'C:\xampp',
    [switch]$DryRun
)
function Ensure-Admin {
    $current = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($current)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltinRole]::Administrator)) {
        Write-Error 'Este script debe ejecutarse como administrador.'
        exit 1
    }
}
function Get-Timestamp {
    return (Get-Date).ToString('yyyyMMdd_HHmmss')
}
function Backup-MySqlData {
    param([string]$Source, [string]$Destination)
    Write-Host "Creando copia de seguridad de '$Source' en '$Destination'..."
    $result = robocopy $Source $Destination /MIR /NFL /NDL /NJH /NJS /nc /ns /np
    if ($LASTEXITCODE -ge 8) {
        throw 'Robocopy falló al crear la copia de seguridad. Verifique permisos y espacio.'
    }
}
function Repair-AriaTables {
    param([string]$DataPath, [string]$BinPath)
    $ariaChk = Join-Path $BinPath 'aria_chk.exe'
    if (-not (Test-Path $ariaChk)) {
        throw "No se encontró aria_chk.exe en $BinPath"
    }
    Get-ChildItem -Path $DataPath -Recurse -Filter *.MAI | ForEach-Object {
        Write-Host "Reparando: $($_.FullName)"
        & $ariaChk -r $_.FullName
    }
}
function Remove-AriaLogs {
    param([string]$DataPath)
    Write-Host 'Eliminando archivos aria_log.*'
    Get-ChildItem -Path $DataPath -Filter 'aria_log.*' -File -ErrorAction SilentlyContinue | Remove-Item -Force
}
Ensure-Admin
$mysqlData = Join-Path $XamppPath 'mysql\data'
$mysqlBin  = Join-Path $XamppPath 'mysql\bin'
if (-not (Test-Path $mysqlData)) {
    throw "Directorio de datos no encontrado: $mysqlData"
}
$timestamp = Get-Timestamp
$backupDir = "$mysqlData`_backup_$timestamp"
if ($DryRun) {
    Write-Host '[DryRun] fuente:' $mysqlData
    Write-Host '[DryRun] backup:' $backupDir
    Write-Host '[DryRun] bin:' $mysqlBin
    exit 0
}
Backup-MySqlData -Source $mysqlData -Destination $backupDir
Repair-AriaTables -DataPath $mysqlData -BinPath $mysqlBin
Remove-AriaLogs -DataPath $mysqlData
Write-Host 'Proceso terminado. Intente iniciar MySQL desde XAMPP Control Panel como administrador.'
Write-Host 'Si el servidor sigue fallando, revise C:\xampp\mysql\data\mysql_error.log y comparta el mensaje.'
