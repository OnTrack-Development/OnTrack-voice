param([string]$Root='D:\AI')
$ErrorActionPreference='Stop'
$ErrorView='ConciseView'
$out=Join-Path $env:RUNNER_TEMP 'ontrack-ayo-runtime'
New-Item -Force -ItemType Directory $out | Out-Null
$report=[ordered]@{
  inspected_utc=(Get-Date).ToUniversalTime().ToString('o')
  root=$Root
  root_exists=(Test-Path -LiteralPath $Root -PathType Container)
  architecture=$env:PROCESSOR_ARCHITECTURE
  system=@{}
  python=@{}
  voice_presets=@()
  checkpoint_candidates=@()
  tooling=@{}
}
$cs=Get-CimInstance Win32_ComputerSystem
$report.system.ram_gb=[math]::Round($cs.TotalPhysicalMemory/1GB,2)
$report.system.cpu=$cs.Model
$report.system.gpu=@(Get-CimInstance Win32_VideoController | ForEach-Object { @{name=$_.Name;ram_bytes=$_.AdapterRAM}})
foreach($d in Get-PSDrive -PSProvider FileSystem){
  if($d.Name -eq 'D'){$report.system.drive_D_free_gb=[math]::Round($d.Free/1GB,1)}
  if($d.Name -eq 'C'){$report.system.drive_C_free_gb=[math]::Round($d.Free/1GB,1)}
}
foreach($file in @('AYO-calm.pt','AYO-excited.pt','AYO-sleepy.pt')){
  $path=Join-Path $Root $file
  $exists=Test-Path -LiteralPath $path -PathType Leaf
  $entry=@{name=$file;exists=$exists}
  if($exists){
    $item=Get-Item -LiteralPath $path
    $entry.size_bytes=$item.Length
    $entry.sha256=(Get-FileHash -Algorithm SHA256 -LiteralPath $path).Hash
  }
  $report.voice_presets+= $entry
}
$pyCandidates=@()
foreach($cmd in @('python','py','python3')){
  $found=Get-Command $cmd -ErrorAction SilentlyContinue
  if($found){$pyCandidates+=@{name=$cmd;source=$found.Source}}
}
$report.python.commands=$pyCandidates
try {
  $report.python.installed_versions=(& py -0p 2>&1 | Out-String).Trim()
} catch {
  $report.python.installed_versions="py launcher unavailable"
}
try {
  $report.tooling.docker_version=(& docker --version 2>&1 | Out-String).Trim()
} catch {
  $report.tooling.docker_version="not found"
}
foreach($cmd in @('python','py')){
  $found=Get-Command $cmd -ErrorAction SilentlyContinue
  if(-not $found){continue}
  try{
    $args=@('-c','import sys, json, importlib.util; print(json.dumps({"executable":sys.executable,"version":sys.version.split()[0],"modules":{name: bool(importlib.util.find_spec(name)) for name in ("torch","chatterbox","habibi_tts","soundfile","safetensors","transformers","huggingface_hub")}}, ensure_ascii=False))')
    if($cmd -eq 'py'){$args=@('-3')+$args}
    $output=& $cmd @args 2>&1 | Out-String
    if($LASTEXITCODE -eq 0){
      $report.python.details=($output | ConvertFrom-Json)
      break
    }else{$report.python["error_$cmd"]=$output.Substring(0,[Math]::Min(500,$output.Length))}
  }catch{$report.python["error_$cmd"]=$_.Exception.Message}
}
if($report.root_exists){
  # Discovery by checkpoint filenames, no pickle loading or executing files.
  $names=@('ve.pt','s3gen.pt','conds.pt','grapheme_mtl_merged_expanded_v1.json','model_100000.safetensors','model_200000.safetensors','vocab.txt')
  $files=@(Get-ChildItem -LiteralPath $Root -Recurse -File -ErrorAction SilentlyContinue | Where-Object { $names -contains $_.Name -or ($_.Extension -eq '.safetensors' -and $_.FullName -match '(?i)chatterbox|habibi|f5') } | Select-Object -First 100)
  foreach($file in $files){
    $report.checkpoint_candidates+=@{relative_path=$file.FullName.Substring($Root.Length).TrimStart('\'); size_mb=[math]::Round($file.Length/1MB,1)}
  }
}
$report.tooling.git_actions_runner_name=$env:RUNNER_NAME
$report.tooling.workflow_run_id=$env:GITHUB_RUN_ID
$report | ConvertTo-Json -Depth 9 | Set-Content -LiteralPath (Join-Path $out 'runtime.json') -Encoding UTF8
$txt=@(
  '## AYO and Habibi local runtime preflight',
  "Root exists: $($report.root_exists)",
  "RAM: $($report.system.ram_gb) GB",
  "Python commands: $(($pyCandidates|ForEach-Object{$_.name}) -join ', ')",
  "Potential model checkpoints under AI: $($report.checkpoint_candidates.Count)",
  'This task inspects metadata only and does not install packages or download model weights.'
) -join "`n"
Write-Host $txt
if($env:GITHUB_STEP_SUMMARY){ Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value $txt }
