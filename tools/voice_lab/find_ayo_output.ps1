$ErrorActionPreference = 'Stop'
Add-Type -AssemblyName System.IO.Compression
$base = 'D:\AI'
$folder = Join-Path $base 'OnTrack_AYO_Premium_Assets'
$archive = Join-Path $base 'OnTrack_AYO_Premium_Assets.zip'
$extensions = @('.wav','.mp3','.flac','.ogg','.opus','.m4a','.aac')
$result = [ordered]@{ scanned_at_utc=(Get-Date).ToUniversalTime().ToString('o'); folder=$folder; folder_exists=(Test-Path -LiteralPath $folder -PathType Container); archive_exists=(Test-Path -LiteralPath $archive -PathType Leaf); folder_files=@(); archive_entries=@(); audio_in_folder=@(); audio_in_archive=@() }
if ($result.folder_exists) {
  Get-ChildItem -LiteralPath $folder -Recurse -File -ErrorAction Stop | ForEach-Object {
    $x=[ordered]@{path=$_.FullName.Substring($folder.Length).TrimStart('\'); bytes=$_.Length; extension=$_.Extension.ToLowerInvariant()}
    $result.folder_files += $x
    if($extensions -contains $_.Extension.ToLowerInvariant()){$result.audio_in_folder += $x}
  }
}
if ($result.archive_exists) {
  $zip=[System.IO.Compression.ZipFile]::OpenRead($archive)
  try{
    foreach($entry in $zip.Entries){
      if($entry.FullName.EndsWith('/')){continue}
      $suffix=[IO.Path]::GetExtension($entry.Name).ToLowerInvariant()
      $x=[ordered]@{path=$entry.FullName;bytes=$entry.Length;extension=$suffix}
      $result.archive_entries += $x
      if($extensions -contains $suffix){$result.audio_in_archive += $x}
    }
  }finally{$zip.Dispose()}
}
$dir=Join-Path $env:RUNNER_TEMP 'ayo-output-search'
New-Item -ItemType Directory -Path $dir -Force | Out-Null
$result | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $dir 'report.json') -Encoding utf8
Write-Host "Folder exists: $($result.folder_exists); ZIP exists: $($result.archive_exists)"
Write-Host "Folder files: $($result.folder_files.Count); WAV/audio files: $($result.audio_in_folder.Count)"
Write-Host "ZIP entries: $($result.archive_entries.Count); WAV/audio entries: $($result.audio_in_archive.Count)"
if($env:GITHUB_STEP_SUMMARY){
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "## AYO Premium Assets folder and ZIP scan"
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "Folder: $folder"
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "Folder file count: $($result.folder_files.Count) | audio count: $($result.audio_in_folder.Count)"
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "ZIP file count: $($result.archive_entries.Count) | audio count: $($result.audio_in_archive.Count)"
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value 'Files:'
  foreach($f in $result.folder_files){ Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "- $($f.path)" }
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value 'ZIP entries:'
  foreach($f in $result.archive_entries){ Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "- $($f.path)" }
}
