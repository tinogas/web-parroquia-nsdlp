<#
.SYNOPSIS
    Levanta el sitio en esta computadora: el MySQL de XAMPP y el servidor de PHP
    en http://localhost:8080. Lo que ya estaba arriba lo deja como está.

.DESCRIPTION
    Para empezar a trabajar sin abrir el panel de XAMPP ni dejar una terminal
    ocupada: los procesos quedan ocultos y siguen corriendo aunque se cierre la
    terminal o VS Code. MySQL se apaga desde el panel de XAMPP; el servidor de
    PHP, cerrando php.exe en el Administrador de tareas.

    El servidor de PHP se levanta dos veces en el mismo puerto, una en ::1 y otra
    en 127.0.0.1, porque «localhost» se resuelve a una u otra según quién
    pregunte (el navegador, curl, el túnel de tunel.ps1), y con una sola a veces
    no contesta.

    Al final abre la página de inicio y avisa si no conecta con la base.

.PARAMETER Puerto
    Puerto del servidor de PHP. 8080 si no se dice.

.PARAMETER Xampp
    Carpeta de XAMPP. C:\xampp si no se dice.

.EXAMPLE
    .\herramientas\servidor_local.ps1

.EXAMPLE
    .\herramientas\servidor_local.ps1 -Puerto 8081
#>
param(
    [int]$Puerto = 8080,
    [string]$Xampp = 'C:\xampp'
)

$ProgressPreference = 'SilentlyContinue'
$raiz   = Split-Path -Parent $PSScriptRoot
$php    = Join-Path $Xampp 'php\php.exe'
$mysqld = Join-Path $Xampp 'mysql\bin\mysqld.exe'

function Fallar([string]$mensaje) {
    Write-Host $mensaje -ForegroundColor Red
    exit 1
}

# Quién escucha en el puerto, en una dirección concreta o en cualquiera. La coma
# del return la devuelve siempre como lista: sin ella, una sola conexión sale
# suelta y en Windows PowerShell 5.1 su .Count viene vacío, así que un MySQL
# que ya corre parecía apagado.
function Escuchando([int]$p, [string]$direccion) {
    $conexiones = @(Get-NetTCPConnection -LocalPort $p -State Listen -ErrorAction SilentlyContinue)
    if ($direccion) { $conexiones = @($conexiones | Where-Object LocalAddress -eq $direccion) }
    return , $conexiones
}

function Esperar([scriptblock]$condicion, [int]$segundos) {
    $limite = (Get-Date).AddSeconds($segundos)
    while ((Get-Date) -lt $limite) {
        if (& $condicion) { return $true }
        Start-Sleep -Milliseconds 300
    }
    return [bool](& $condicion)
}

foreach ($exe in $php, $mysqld) {
    if (-not (Test-Path $exe)) { Fallar "No encuentro $exe. Si XAMPP está en otra carpeta, usa -Xampp <carpeta>." }
}

# 1. MySQL, igual que lo arranca el panel de XAMPP.
if ((Escuchando 3306).Count) {
    Write-Host 'MySQL: ya estaba arriba.'
} else {
    Write-Host 'MySQL: arrancando...'
    $ini = Join-Path $Xampp 'mysql\bin\my.ini'
    Start-Process -FilePath $mysqld -ArgumentList "--defaults-file=`"$ini`"", '--standalone' `
        -WorkingDirectory (Join-Path $Xampp 'mysql') -WindowStyle Hidden
    if (-not (Esperar { (Escuchando 3306).Count -gt 0 } 30)) {
        Fallar "MySQL no arrancó. Revisa $(Join-Path $Xampp 'mysql\data\mysql_error.log')."
    }
    Write-Host 'MySQL: arriba.' -ForegroundColor Green
}

# 2. PHP, en las dos direcciones de localhost.
foreach ($direccion in '::1', '127.0.0.1') {
    $enlace = if ($direccion -eq '::1') { "[::1]:$Puerto" } else { "127.0.0.1:$Puerto" }
    $ocupado = Escuchando $Puerto $direccion
    if ($ocupado.Count) {
        $dueno = Get-CimInstance Win32_Process -Filter "ProcessId=$($ocupado[0].OwningProcess)"
        if ($dueno.Name -ne 'php.exe') {
            Fallar "El puerto $enlace lo ocupa otro programa ($($dueno.Name)). Ciérralo o usa -Puerto."
        }
        Write-Host "PHP en ${enlace}: ya estaba arriba."
        continue
    }
    Start-Process -FilePath $php -ArgumentList '-S', $enlace, 'router.php' -WorkingDirectory $raiz -WindowStyle Hidden
    if (-not (Esperar { (Escuchando $Puerto $direccion).Count -gt 0 } 10)) {
        Fallar "El servidor de PHP no arrancó en $enlace. ¿Otro programa usa ese puerto?"
    }
    Write-Host "PHP en ${enlace}: arriba." -ForegroundColor Green
}

# 3. Que la página abra y llegue a la base.
try {
    $respuesta = Invoke-WebRequest "http://127.0.0.1:$Puerto/" -UseBasicParsing -TimeoutSec 30
} catch {
    Fallar "La página no responde: $($_.Exception.Message)"
}
if ($respuesta.Content -match 'SQLSTATE') {
    Fallar 'La página abre, pero no conecta con la base. Revisa MySQL y config/database.php.'
}

Write-Host ''
Write-Host "Listo: http://localhost:$Puerto" -ForegroundColor Green
exit 0
