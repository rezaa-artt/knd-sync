# Build WordPress-compatible plugin ZIPs (forward-slash paths).
# Usage: powershell -File scripts/build-zips.ps1

$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$root = Split-Path -Parent (Split-Path -Parent $MyInvocation.MyCommand.Path)
Set-Location $root

function New-UnixZip {
	param(
		[string]$SourceDir,
		[string]$ZipPath,
		[string]$RootName
	)

	if (Test-Path $ZipPath) {
		Remove-Item -Force $ZipPath
	}

	$sourceFull = (Resolve-Path $SourceDir).Path
	$zipFull = [System.IO.Path]::GetFullPath($ZipPath)
	$zip = [System.IO.Compression.ZipFile]::Open($zipFull, [System.IO.Compression.ZipArchiveMode]::Create)

	try {
		Get-ChildItem -Path $sourceFull -Recurse -File | ForEach-Object {
			$rel = $_.FullName.Substring($sourceFull.Length).TrimStart('\', '/')
			# Skip local test leftovers inside plugin folders.
			if ($rel -match '(^|/)tests(/|$)') {
				return
			}
			$entryName = $RootName + '/' + ($rel -replace '\\', '/')
			[void][System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile(
				$zip,
				$_.FullName,
				$entryName,
				[System.IO.Compression.CompressionLevel]::Optimal
			)
		}
	}
	finally {
		$zip.Dispose()
	}
}

New-Item -ItemType Directory -Force -Path dist | Out-Null
New-UnixZip -SourceDir 'knd-sync-receiver' -ZipPath 'dist\knd-sync-receiver.zip' -RootName 'knd-sync-receiver'
New-UnixZip -SourceDir 'knd-sync-sender' -ZipPath 'dist\knd-sync-sender.zip' -RootName 'knd-sync-sender'

Write-Host 'Built:'
Get-ChildItem dist\*.zip | ForEach-Object { Write-Host (" - {0} ({1} bytes)" -f $_.Name, $_.Length) }
Write-Host 'Do NOT use Compress-Archive for WordPress plugins (creates backslash paths).'
