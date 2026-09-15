$ErrorActionPreference = 'Stop'
$root = $PSScriptRoot
Push-Location (Join-Path $root 'frontend')
try {
    & npm.cmd test
    if ($LASTEXITCODE -ne 0) { throw 'Frontend checks failed.' }
} finally { Pop-Location }
$stage = Join-Path ([IO.Path]::GetTempPath()) ('lgfc-upload-' + [guid]::NewGuid().ToString('N'))
New-Item -ItemType Directory -Path $stage | Out-Null
foreach ($name in @('public','src','db')) {
    Copy-Item -LiteralPath (Join-Path $root $name) -Destination (Join-Path $stage $name) -Recurse
}
New-Item -ItemType Directory -Path (Join-Path $stage 'var') | Out-Null
Copy-Item -LiteralPath (Join-Path $root 'var/.htaccess') -Destination (Join-Path $stage 'var/.htaccess')
foreach ($name in @('.htaccess','DEPLOYMENT.md')) {
    Copy-Item -LiteralPath (Join-Path $root $name) -Destination (Join-Path $stage $name)
}
$release = Join-Path $root 'release'
New-Item -ItemType Directory -Force -Path $release | Out-Null
$zip = Join-Path $release ('lgfc-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.zip')
# ZipFile includes dotfiles, unlike some archive helpers. Unique staging is retained in TEMP.
Add-Type -AssemblyName System.IO.Compression.FileSystem
[IO.Compression.ZipFile]::CreateFromDirectory($stage, $zip)
Write-Output "Upload package: $zip"
