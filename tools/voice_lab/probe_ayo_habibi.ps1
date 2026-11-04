param([string]$Root='D:\AI')
$ErrorActionPreference='Stop'
$report=[ordered]@{
  checked_at=(Get-Date).ToUniversalTime().ToString('o')
  folder=$Root
  profiles=@()
  python=@()
  candidates=@()
  wav_samples=@()
  can_synthesize=$false
  note='This is a capability probe only, not proof of working synthesis.'
}
$paths=@(
  (Join-Path $Root 'OnTrack_AYO_Premium_Assets\assets'),
  $Root,
  (Join-Path $Root 'OnTrack_AYO_Premium_Assets')
)
foreach($name in @('AYO-calm.pt','AYO-excited.pt','AYO-sleepy.pt')){
 $found=@()
 foreach($base in $paths){
   $p=Join-Path $base $name
   if(Test-Path -LiteralPath $p -PathType Leaf){$f=Get-Item -LiteralPath $p;$found+=@{path=$p;size=$f.Length;sha256=(Get-FileHash -LiteralPath $p -Algorithm SHA256).Hash}}
 }
 $report.profiles+=@{name=$name;locations=$found}
}
$commands=@('py','python','python3','ffmpeg','nvidia-smi')
foreach($command in $commands) {
 $cmd=Get-Command $command -ErrorAction SilentlyContinue
 if($cmd){$report.python+=@{command=$command;path=$cmd.Source}}
}
$pythonCmd=Get-Command python -ErrorAction SilentlyContinue
if(-not $pythonCmd){$pythonCmd=Get-Command py -ErrorAction SilentlyContinue}
if($pythonCmd){
  $pyscript=@'
import importlib.util,json,sys
pkgs={}
for name in ('torch','chatterbox','habibi_tts','f5_tts','soundfile','torchaudio'):
 try:
  s=importlib.util.find_spec(name)
  pkgs[name] = bool(s)
 except Exception as e:
  pkgs[name] = str(e)
print(json.dumps({'executable':sys.executable,'version':sys.version.split()[0],'packages':pkgs}))
'@
  try {$report.python+=@{package_probe=($pyscript | & $pythonCmd.Source - 2>&1 | Out-String).Trim()}}catch{$report.python+=@{package_probe_error=$_.Exception.Message}}
}
$weights=@('*.safetensors','*.ckpt','*.onnx','model*.pt','model*.pth')
foreach($folder in @($Root,'D:\models','C:\AI')){
 if(Test-Path -LiteralPath $folder -PathType Container){
  foreach($pattern in $weights){
   Get-ChildItem -LiteralPath $folder -Filter $pattern -File -Recurse -ErrorAction SilentlyContinue | Select-Object -First 10 | ForEach-Object{
    $report.candidates+=@{path=$_.FullName;bytes=$_.Length}
   }
  }
 }
}
foreach($folder in @($Root,'D:\AI\output','D:\AI\outputs')){
 if(Test-Path -LiteralPath $folder -PathType Container){
   Get-ChildItem -LiteralPath $folder -Recurse -File -Filter '*.wav' -ErrorAction SilentlyContinue |
     Select-Object -First 30 | ForEach-Object{$report.wav_samples+=@{path=$_.FullName;bytes=$_.Length}}
 }
}
$out=Join-Path $env:RUNNER_TEMP 'ayo-habibi-readiness'
New-Item -ItemType Directory -Force -Path $out | Out-Null
$report | ConvertTo-Json -Depth 10 | Set-Content -LiteralPath (Join-Path $out 'report.json') -Encoding utf8
Write-Host "Profile files present: $(@($report.profiles | Where-Object {$_.locations.Count -gt 0}).Count)/3"
Write-Host "Python tools found: $($report.python.Count); model candidates: $($report.candidates.Count); WAV candidates: $($report.wav_samples.Count)"
if($env:GITHUB_STEP_SUMMARY) {
 Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "## AYO / Habibi synthesis readiness"
 foreach($x in $report.profiles){Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "- $($x.name): $($x.locations.Count) copies"}
 Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "Detected model weight candidates: $($report.candidates.Count); WAV references: $($report.wav_samples.Count)"
 Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "No audio synthesized in this probe; see artifact for installed packages."
}
