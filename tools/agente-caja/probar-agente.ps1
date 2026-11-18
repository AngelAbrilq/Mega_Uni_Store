# Prueba del agente de caja en Windows (PowerShell). En Linux usa probar-agente.sh.
#   .\tools\agente-caja\probar-agente.ps1 [-Tienda 3] [-Puerto 9123]
param([string]$Tienda = "", [int]$Puerto = 9123)
$ErrorActionPreference = "Stop"
$aqui = Split-Path -Parent $MyInvocation.MyCommand.Path
$raiz = Resolve-Path (Join-Path $aqui "..\..")
Push-Location $raiz
try {
    $argumentos = @("artisan", "cajon:agente") + $(if ($Tienda) { @($Tienda) } else { @() }) + @("--puerto=$Puerto")
    $salida = & php @argumentos
    $token = ($salida | Where-Object { $_ -like "TOKEN=*" } | Select-Object -Last 1) -replace "^TOKEN=", ""
    if (-not $token) { throw "cajon:agente no devolvio el token" }

    $config = Join-Path $aqui "config.json"
    if (Test-Path $config) { $cfg = Get-Content $config -Raw | ConvertFrom-Json }
    else { $cfg = [pscustomobject]@{ driver = "archivo"; archivo = @{ ruta = "C:\temp\cajon.bin" }; origenes = @() }; New-Item -ItemType Directory -Force C:\temp | Out-Null }
    $cfg | Add-Member -Force -NotePropertyName token -NotePropertyValue $token
    $cfg | Add-Member -Force -NotePropertyName puerto -NotePropertyValue $Puerto
    $cfg | ConvertTo-Json -Depth 5 | Set-Content -Encoding UTF8 $config

    $agente = Start-Process node -ArgumentList "`"$(Join-Path $aqui 'agente.js')`"" -PassThru -WindowStyle Hidden
    Start-Sleep -Seconds 1
    $url = "http://127.0.0.1:$Puerto"
    Write-Host "Estado:" (Invoke-RestMethod "$url/estado?token=$token" | ConvertTo-Json -Compress)
    $cajon = Invoke-RestMethod -Method Post -ContentType "text/plain" -Body (@{ token = $token } | ConvertTo-Json) "$url/cajon"
    if (-not $cajon.abierto) { throw "El cajon no abrio" }
    Write-Host "Cajon abierto."
    & php artisan cajon:probar-recibo
} finally {
    if ($agente) { Stop-Process -Id $agente.Id -ErrorAction SilentlyContinue }
    Pop-Location
}
