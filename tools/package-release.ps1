param([string]$ProjectRoot=(Split-Path $PSScriptRoot -Parent),[string]$OutputDirectory=(Join-Path (Split-Path $PSScriptRoot -Parent) 'dist'))
$ErrorActionPreference='Stop'
Add-Type -AssemblyName System.IO.Compression.FileSystem
$taskRoot=(Resolve-Path -LiteralPath $ProjectRoot).Path
$taskRelease=Get-Content -LiteralPath (Join-Path $taskRoot 'release-files.json') -Raw | ConvertFrom-Json
$taskManifest=Get-Content -LiteralPath (Join-Path $taskRoot 'MANIFEST.json') -Raw | ConvertFrom-Json
New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null
foreach($taskKind in @('source','update')){
    $taskName=if($taskKind -eq 'source'){'LandiCy_codigo_fuente_v0.6.3_AGPL.zip'}else{'LandiCy_actualizacion_v0.6.3.zip'}
    $taskTarget=Join-Path $OutputDirectory $taskName
    if(Test-Path -LiteralPath $taskTarget){throw "El paquete ya existe: $taskTarget"}
    $taskZip=[IO.Compression.ZipFile]::Open($taskTarget,[IO.Compression.ZipArchiveMode]::Create)
    try{foreach($taskFile in $taskRelease.$taskKind){
        if($taskFile -match '(^|/)(config\.php|storage|sessions|test-fixtures|\.env)(/|$)' -or $taskFile.Contains('..') -or [IO.Path]::IsPathRooted($taskFile)){throw 'Ruta de paquete rechazada'}
        $taskAbsolute=Join-Path $taskRoot $taskFile
        if((Get-Item -LiteralPath $taskAbsolute).Attributes -band [IO.FileAttributes]::ReparsePoint){throw 'Enlace rechazado'}
        $taskEntry=$taskManifest.files | Where-Object file -eq $taskFile
        if($taskFile -ne 'MANIFEST.json' -and (!$taskEntry -or (Get-FileHash -LiteralPath $taskAbsolute -Algorithm SHA256).Hash.ToLowerInvariant() -ne $taskEntry.sha256)){throw "Hash diferente: $taskFile"}
        [IO.Compression.ZipFileExtensions]::CreateEntryFromFile($taskZip,$taskAbsolute,$taskFile,[IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }}finally{$taskZip.Dispose()}
    $taskCheck=[IO.Compression.ZipFile]::OpenRead($taskTarget)
    try{if($taskCheck.Entries.Count -ne $taskRelease.$taskKind.Count){throw 'Número de entradas distinto'};foreach($taskEntry in $taskCheck.Entries){if($taskEntry.FullName -notin $taskRelease.$taskKind){throw 'Entrada inesperada'}}}finally{$taskCheck.Dispose()}
    Write-Output "$taskName validado"
}
$taskChecksums=Get-ChildItem -LiteralPath $OutputDirectory -Filter '*v0.6.3*.zip' | ForEach-Object { '{0}  {1}' -f (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash.ToLowerInvariant(),$_.Name }
[IO.File]::WriteAllText((Join-Path $OutputDirectory 'SHA256SUMS-v0.6.3.txt'),(($taskChecksums -join "`n")+"`n"),[Text.Encoding]::ASCII)
