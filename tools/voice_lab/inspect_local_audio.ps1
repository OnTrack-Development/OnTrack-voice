# Windows self-hosted runner: inspect local AI voice assets (read-only).
# No audio content, credentials, or model weights are uploaded.
param(
    [string]$FolderPath = "",
    [string]$OutputDirectory = ""
)

$ErrorActionPreference = "Stop"
if ([string]::IsNullOrWhiteSpace($OutputDirectory)) {
    $OutputDirectory = Join-Path $env:RUNNER_TEMP "ontrack-voice-audio-inventory"
}
New-Item -ItemType Directory -Path $OutputDirectory -Force | Out-Null

$candidates = New-Object System.Collections.Generic.List[string]
if (-not [string]::IsNullOrWhiteSpace($FolderPath)) {
    [void]$candidates.Add($FolderPath)
} else {
    foreach ($drive in (Get-PSDrive -PSProvider FileSystem)) {
        [void]$candidates.Add((Join-Path $drive.Root "AI"))
    }
    foreach ($base in @($env:USERPROFILE, $env:OneDrive, $env:OneDriveCommercial)) {
        if (-not [string]::IsNullOrWhiteSpace($base)) {
            foreach ($sub in @("Desktop\AI", "Documents\AI", "Downloads\AI", "AI")) {
                [void]$candidates.Add((Join-Path $base $sub))
            }
        }
    }
}
$folders = @($candidates | Select-Object -Unique | Where-Object { Test-Path -LiteralPath $_ -PathType Container })
if ($folders.Count -eq 0) {
    $tested = $candidates | Select-Object -Unique
    $message = "AI folder not found in default locations. Re-run with folder_path set to its exact location. Tested: " + ($tested -join ", ")
    Write-Host $message
    if ($env:GITHUB_STEP_SUMMARY) {
        Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value "### OnTrack voice assets: folder not found\nEnter the exact `folder_path` in **Run workflow** (for example `D:\AI`)."
    }
    exit 2
}

$audioExt = @(".wav", ".mp3", ".m4a", ".flac", ".ogg", ".opus", ".aac", ".wma")
$modelExt = @(".onnx", ".pth", ".pt", ".safetensors", ".ckpt", ".tflite")
$inventory = New-Object System.Collections.Generic.List[object]
$errors = New-Object System.Collections.Generic.List[string]

foreach ($folder in $folders) {
    $fullFolder = (Resolve-Path -LiteralPath $folder).ProviderPath.TrimEnd('\')
    Write-Host ("Inspecting " + $fullFolder)
    # Access only metadata. We do not open file contents or modify source files.
    Get-ChildItem -LiteralPath $fullFolder -File -Recurse -ErrorAction SilentlyContinue -ErrorVariable +scanError | ForEach-Object {
        $file = $_
        $relative = $file.FullName.Substring($fullFolder.Length).TrimStart('\')
        if ($file.Name -match '(?i)(^\.env|secret|password|credential|private.?key|token)') { return }
        $kind = if ($audioExt -contains $file.Extension.ToLowerInvariant()) { "audio" } elseif ($modelExt -contains $file.Extension.ToLowerInvariant()) { "voice_model" } else { "other" }
        [void]$inventory.Add([pscustomobject]@{
            folder = $fullFolder
            relative_path = $relative
            type = $kind
            extension = $file.Extension.ToLowerInvariant()
            size_bytes = [int64]$file.Length
        })
    }
}
$data = @($inventory | Sort-Object folder,relative_path)
$audio = @($data | Where-Object { $_.type -eq "audio" })
$models = @($data | Where-Object { $_.type -eq "voice_model" })
$jsonPath = Join-Path $OutputDirectory "inventory.json"
[pscustomobject]@{
    scanned_at_utc = (Get-Date).ToUniversalTime().ToString("o")
    folder_count = $folders.Count
    file_count = $data.Count
    audio_count = $audio.Count
    voice_model_count = $models.Count
    files = $data
} | ConvertTo-Json -Depth 6 | Set-Content -LiteralPath $jsonPath -Encoding utf8

$lines = New-Object System.Collections.Generic.List[string]
[void]$lines.Add("### Local AI voice assets (read-only)")
[void]$lines.Add("Folders: $($folders.Count) | All files: $($data.Count) | Audio: $($audio.Count) | Model weights: $($models.Count)")
[void]$lines.Add("")
[void]$lines.Add("| Type | Relative filename | Size (bytes) |")
[void]$lines.Add("| --- | --- | ---: |")
foreach ($item in @($data | Where-Object { $_.type -ne "other" } | Select-Object -First 200)) {
    $safeName = ($item.relative_path -replace '\|', '\/' -replace '[\r\n]', ' ')
    [void]$lines.Add("| $($item.type) | $safeName | $($item.size_bytes) |")
}
if (($audio.Count + $models.Count) -gt 200) {
    [void]$lines.Add("Only first 200 audio/model filenames shown; all metadata in JSON artifact.")
}
$markdown = $lines -join "`n"
$markdown | Set-Content -LiteralPath (Join-Path $OutputDirectory "summary.md") -Encoding utf8
Write-Host "Found $($data.Count) files, including $($audio.Count) audio files and $($models.Count) voice model files."
if ($env:GITHUB_STEP_SUMMARY) { Add-Content -LiteralPath $env:GITHUB_STEP_SUMMARY -Value $markdown }
