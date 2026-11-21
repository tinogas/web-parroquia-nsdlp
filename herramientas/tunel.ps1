<#
.SYNOPSIS
    Abre el túnel fijo que comparte por internet el sitio de esta computadora.
    Si ya estaba abierto, lo cierra.

.DESCRIPTION
    Es un interruptor. Cuando el túnel está cerrado: corre servidor_local.ps1
    (MySQL y PHP), renueva la vigencia del túnel por 30 días más, lo abre oculto
    —sigue arriba aunque se cierre la terminal o VS Code—, escribe la liga y la
    deja en el portapapeles. Cuando ya está abierto, lo cierra; el sitio local
    sigue arriba.

    El túnel es de Microsoft Dev Tunnels y la liga es siempre la misma. Se
    prepara una sola vez:

        winget install Microsoft.devtunnel
        devtunnel user login
        devtunnel create -a -e 30d
        devtunnel port create <ID que dio el paso anterior> -p 8080

    y el ID se anota en herramientas/.env, que no se versiona:

        TUNEL_ID=<ID>
        TUNEL_PUERTO=8080      (opcional; 8080 si no está)

    El ID no va en el script porque el repositorio es público: con él cualquiera
    podría conectarse mientras el túnel esté abierto.

    Quien tenga la liga entra al sitio con la base de esta computadora: lo que
    cambie en el panel se queda.

.EXAMPLE
    .\herramientas\tunel.ps1
#>

$ProgressPreference = 'SilentlyContinue'

function Fallar([string]$mensaje) {
    Write-Host $mensaje -ForegroundColor Red
    exit 1
}

# Lee lo que devtunnel va escribiendo, sin pelear con él por el archivo.
function LeerLog([string]$ruta) {
    try {
        $flujo = [IO.File]::Open($ruta, 'Open', 'Read', 'ReadWrite')
        try { return (New-Object IO.StreamReader($flujo)).ReadToEnd() } finally { $flujo.Close() }
    } catch {
        return ''
    }
}

# winget agrega devtunnel al PATH, pero una terminal abierta antes no se entera.
$env:Path = [Environment]::GetEnvironmentVariable('Path', 'Machine') + ';' +
            [Environment]::GetEnvironmentVariable('Path', 'User')
$devtunnel = (Get-Command devtunnel -ErrorAction SilentlyContinue).Source
if (-not $devtunnel) { Fallar 'No encuentro devtunnel. Instálalo con:  winget install Microsoft.devtunnel' }

$datos = @{}
$archivoEnv = Join-Path $PSScriptRoot '.env'
if (Test-Path $archivoEnv) {
    foreach ($linea in Get-Content $archivoEnv -Encoding UTF8) {
        if ($linea -match '^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$') {
            $datos[$Matches[1]] = $Matches[2].Trim('"', "'")
        }
    }
}
$tunel = $datos['TUNEL_ID']
if (-not $tunel) { Fallar "Falta TUNEL_ID en $archivoEnv. Cómo prepararlo:  Get-Help '$PSCommandPath' -Full" }
$puerto = if ($datos['TUNEL_PUERTO']) { [int]$datos['TUNEL_PUERTO'] } else { 8080 }
$nombre = ($tunel -split '\.')[0]   # sin la región (.usw3): también se puede abrir así

# ¿Ya está abierto, desde aquí o a mano? Entonces se cierra.
$abiertos = @(Get-CimInstance Win32_Process -Filter "Name='devtunnel.exe'" |
    Where-Object { $_.CommandLine -match '\shost\s' -and $_.CommandLine -match [regex]::Escape($nombre) })
if ($abiertos.Count) {
    $abiertos | ForEach-Object { Stop-Process -Id $_.ProcessId -Force }
    Write-Host 'Túnel cerrado: la liga ya no responde. El sitio local sigue arriba.' -ForegroundColor Yellow
    exit 0
}

# Si no, se abre. Primero, el sitio local.
& (Join-Path $PSScriptRoot 'servidor_local.ps1') -Puerto $puerto
if ($LASTEXITCODE) { exit $LASTEXITCODE }
Write-Host ''

$sesion = & $devtunnel user show 2>&1 | Out-String
if ($sesion -match 'Not logged in') {
    Write-Host 'Hay que iniciar sesión en Dev Tunnels con tu cuenta (se hace una vez):'
    & $devtunnel user login
    if ($LASTEXITCODE) { Fallar 'No se pudo iniciar sesión.' }
}

$null = & $devtunnel update $tunel -e 30d 2>&1
if ($LASTEXITCODE) {
    Fallar "No encuentro el túnel $tunel en tu cuenta; quizá venció. Cómo crear otro:  Get-Help '$PSCommandPath' -Full"
}

# Trabaja en la carpeta temporal: si heredara la de la terminal, mientras el
# túnel siga abierto Windows no dejaría mover ni borrar esa carpeta.
$log      = Join-Path $env:TEMP 'webparroquia-tunel.log'
$logError = Join-Path $env:TEMP 'webparroquia-tunel-error.log'
$proceso = Start-Process -FilePath $devtunnel -ArgumentList 'host', $tunel -WindowStyle Hidden -PassThru `
    -WorkingDirectory $env:TEMP `
    -RedirectStandardOutput $log -RedirectStandardError $logError

$liga = $null
$limite = (Get-Date).AddSeconds(30)
while (-not $liga -and -not $proceso.HasExited -and (Get-Date) -lt $limite) {
    Start-Sleep -Milliseconds 500
    $texto = LeerLog $log
    if ($texto -match 'Ready to accept connections' -and
        $texto -match "https://[a-z0-9]+-$puerto\.[a-z0-9.]+\.devtunnels\.ms") {
        $liga = $Matches[0]
    }
}
if (-not $liga) {
    if (-not $proceso.HasExited) { Stop-Process -Id $proceso.Id -Force }
    Write-Host 'El túnel no abrió. Esto dijo devtunnel:' -ForegroundColor Red
    Write-Host (LeerLog $log)
    Write-Host (LeerLog $logError)
    exit 1
}

Set-Clipboard -Value $liga
Write-Host "Túnel abierto:  $liga" -ForegroundColor Green
Write-Host '(la liga quedó en el portapapeles)'
Write-Host ''
Write-Host 'Quien la abra verá primero un aviso de Microsoft y tiene que darle «Continue».'
Write-Host 'Entra a la base de esta computadora: lo que cambie en el panel se queda.'
Write-Host 'Para cerrarlo, vuelve a correr este mismo script.'
exit 0
