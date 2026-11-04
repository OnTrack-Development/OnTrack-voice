# Inspect AYO voice profiles without executing/loading untrusted PyTorch pickle.
param([string]$Root="D:\AI")
$ErrorActionPreference="Stop"
if(!(Test-Path -LiteralPath $Root -PathType Container)){throw "AI directory missing: $Root"}
$names=@("AYO-calm.pt","AYO-excited.pt","AYO-sleepy.pt")
$report=[ordered]@{folder=$Root; files=@(); metadata=@(); other_voice_files=@(); python_available=$false; torch_available=$false; status="metadata_only"}
foreach($n in $names) {
  $p=Join-Path $Root $n
  if(!(Test-Path -LiteralPath $p -PathType Leaf)){ $report.files+=@{name=$n;present=$false};continue }
  $f=Get-Item -LiteralPath $p
  $fs=[System.IO.File]::OpenRead($p)
  $magic=New-Object byte[] 12
  $num=$fs.Read($magic,0,12)
  $fs.Close()
  $hex=([BitConverter]::ToString($magic,0,$num))
  $format=if($hex -like "50-4B*"){"zip_pytorch_archive"}elseif($hex -like "80-*"){"pickle_or_torch_legacy"}else{"unknown"}
  $zipEntries=@()
  if($format -eq "zip_pytorch_archive"){
    Add-Type -AssemblyName System.IO.Compression
    try {
      $zf=[System.IO.Compression.ZipFile]::OpenRead($p)
      foreach($entry in $zf.Entries){$zipEntries+=@{name=$entry.FullName;uncompressed_bytes=$entry.Length}}
      $zf.Dispose()
    } catch {
      $zipEntries=@(@{warning="zip could not be listed: $($_.Exception.Message)"})
    }
  }
  $report.files+=@{name=$n;present=$true;size_bytes=$f.Length;sha256=(Get-FileHash -LiteralPath $p -Algorithm SHA256).Hash;magic=$hex;format=$format;archive_entries=$zipEntries}
}
foreach($f in @("OnTrack_AYO_Premium_Assets\manifest.json","OnTrack_AYO_Premium_Assets\README.txt","README_AR.md","README_EN.md","LICENSE.txt")){
  $p=Join-Path $Root $f
  if(Test-Path -LiteralPath $p -PathType Leaf){
    if((Get-Item -LiteralPath $p).Length -lt 20000){$report.metadata+=@{path=$f;text=(Get-Content -LiteralPath $p -Raw -Encoding UTF8)}}
  }
}
foreach($f in Get-ChildItem -LiteralPath $Root -File -Recurse -ErrorAction SilentlyContinue | Where-Object {$_.Extension.ToLowerInvariant() -in @(".wav",".mp3",".ogg",".flac",".m4a")} | Select-Object -First 30){
  $report.other_voice_files+=@{path=$f.FullName;size_bytes=$f.Length}
}
$dest=Join-Path $env:RUNNER_TEMP "ayo-voice-inspect"
New-Item -ItemType Directory -Force -Path $dest | Out-Null
$report | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath (Join-Path $dest "voice_report.json") -Encoding utf8
Write-Host "AYO profile files: $($report.files.Count); README/manifest files: $($report.metadata.Count); audio references: $($report.other_voice_files.Count)"
Write-Host "Voice profiles inspected as metadata only: no execution of PyTorch content, no model installation."
if($env:GITHUB_STEP_SUMMARY){
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "## AYO voice assets scan"
  foreach($f in $report.files){Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "- $($f.name): $($f.format), size $($f.size_bytes) bytes"}
  Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "Metadata available in the artifact; source files not uploaded."
}
