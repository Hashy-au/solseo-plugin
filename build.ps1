# Builds the wordpress.org zip.
#
# Staged first so the archive holds only what ships, then written with bsdtar
# because Compress-Archive writes backslashes into the entry names and Linux
# unpacks those as one flat file.

$ErrorActionPreference = 'Stop'

$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$version = (Select-String -Path (Join-Path $here 'solseo.php') -Pattern '^\s*\*\s*Version:\s*(.+)$').Matches[0].Groups[1].Value.Trim()
$stage = Join-Path $here 'dist\stage'
$target = Join-Path $stage 'solseo'
$tar = Join-Path $env:SystemRoot 'System32\tar.exe'
$backslash = [char]92

if (Test-Path $stage) { Remove-Item -Recurse -Force $stage }
New-Item -ItemType Directory -Force -Path $target | Out-Null

foreach ($item in @('solseo.php', 'uninstall.php', 'readme.txt', 'includes', 'assets', 'languages')) {
	$source = Join-Path $here $item
	if (Test-Path $source) {
		Copy-Item -Recurse -Force $source (Join-Path $target $item)
	}
}

$zip = Join-Path $here "dist\solseo-$version.zip"
if (Test-Path $zip) { Remove-Item -Force $zip }

Push-Location $stage
& $tar -a -cf $zip solseo
Pop-Location

$entries = & $tar -tf $zip

foreach ($entry in $entries) {
	if ($entry.Contains($backslash)) {
		throw "The archive holds backslashes in its entry names. Do not ship it."
	}
}

Remove-Item -Recurse -Force $stage
Write-Output "Built $zip with $($entries.Count) entries."
