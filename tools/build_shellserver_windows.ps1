# Builds the Android shell-side daemon locally with Windows JDK 21 and D8.
# Used only after saas/mobile/audio-proof/prepare.py has patched pinned JemRec.
param(
    [Parameter(Mandatory=$true)][string]$ShellServerDir,
    [Parameter(Mandatory=$true)][string]$AndroidSdk
)
$ErrorActionPreference = 'Stop'
$dir = (Resolve-Path -LiteralPath $ShellServerDir).Path
$androidJar = Join-Path $AndroidSdk 'platforms\android-36\android.jar'
$d8 = Join-Path $AndroidSdk 'build-tools\36.0.0\d8.bat'
if (-not (Test-Path -LiteralPath $androidJar)) { throw 'Android 36 platform jar missing.' }
if (-not (Test-Path -LiteralPath $d8)) { throw 'Windows D8 launcher missing.' }
$build = Join-Path $dir 'build'
$classes = Join-Path $build 'classes'
$genDir = Join-Path $build 'gen\com\genymobile\scrcpy'
# Regression (2026-11-04): prepare.py renames the upstream asset name
# everywhere to ontrack-voiceproof-capture.jar, including CaptureDaemon.ASSET
# and Gradle copyShellServer. The old Windows builder still emitted
# jemrec-capture.jar, so Gradle silently omitted the required asset while
# assembling a valid-looking, signed APK. Caller saw FileNotFoundException
# ontrack-voiceproof-capture.jar when the call reached the PCM bridge.
$jar = Join-Path $dir 'ontrack-voiceproof-capture.jar'
if (Test-Path -LiteralPath $build) { Remove-Item -LiteralPath $build -Force -Recurse }
if (Test-Path -LiteralPath $jar) { Remove-Item -LiteralPath $jar -Force }
New-Item -ItemType Directory -Path $classes,$genDir -Force | Out-Null
$buildConfig = @'
package com.genymobile.scrcpy;
public final class BuildConfig {
    public static final boolean DEBUG = false;
    private BuildConfig() {}
}
'@
[System.IO.File]::WriteAllText((Join-Path $genDir 'BuildConfig.java'), $buildConfig, [System.Text.UTF8Encoding]::new($false))
$src = Join-Path $dir 'src'
$sources = @(Get-ChildItem -LiteralPath $src,$(Join-Path $build 'gen') -Recurse -File -Filter '*.java')
if ($sources.Count -lt 10) { throw 'Java sources were unexpectedly missing.' }
$sourceArgs = Join-Path $build 'sources.txt'
@($sources | ForEach-Object { '"' + $_.FullName.Replace('\','/') + '"' }) |
  Set-Content -LiteralPath $sourceArgs -Encoding utf8
& javac --release 17 -nowarn -cp $androidJar -d $classes "@$sourceArgs"
if ($LASTEXITCODE -ne 0) { throw 'Java 17-compatible shellserver compilation failed.' }
$classFiles = @(Get-ChildItem -LiteralPath $classes -Recurse -File -Filter '*.class')
if ($classFiles.Count -lt 10) { throw 'Compiled shellserver classes missing.' }
$classArgs = Join-Path $build 'classes.txt'
@($classFiles | ForEach-Object { $_.FullName.Replace('\','/') }) |
  Set-Content -LiteralPath $classArgs -Encoding utf8
& $d8 --lib $androidJar --min-api 31 --output $jar "@$classArgs"
if ($LASTEXITCODE -ne 0) { throw 'D8 shellserver dex compilation failed.' }
if (-not (Test-Path -LiteralPath $jar)) { throw 'D8 did not produce shellserver jar.' }
if ((Get-Item -LiteralPath $jar).Length -lt 2048) { throw 'Generated shellserver jar is unexpectedly small.' }
$entries = & jar tf $jar
if ($LASTEXITCODE -ne 0 -or $entries -notcontains 'classes.dex') {
  throw 'Shellserver jar does not contain classes.dex.'
}
Write-Host ('WINDOWS_SHELLSERVER_DEX: PASS; sha256=' + (Get-FileHash -LiteralPath $jar -Algorithm SHA256).Hash.ToLowerInvariant())
# The pinned upstream Gradle Exec task has a POSIX-only commandLine("./build.sh").
# Keep its output and Copy task, but let its Exec succeed on Windows after our
# audited native Java/D8 build has already created the real shellserver jar.
# Never allow a different asset filename to slip past Gradle again.
# Compare the actual Gradle file the app packages, not a duplicated filename.
$gradle = Join-Path (Split-Path $dir -Parent) 'app\build.gradle.kts'
$gradleSource=[System.IO.File]::ReadAllText($gradle)
if (-not $gradleSource.Contains('"ontrack-voiceproof-capture.jar"') -or
    $gradleSource.Contains('"jemrec-capture.jar"')) {
  throw 'Shell daemon filename does not match the APK assets Copy task'
}
$before = 'commandLine("./build.sh")'
$sourceText = [System.IO.File]::ReadAllText($gradle)
if ($sourceText.Split([string[]]@($before),[System.StringSplitOptions]::None).Length -ne 2) {
  throw 'Unexpected pinned upstream Gradle shellserver task: refusing to patch.'
}
$patched = $sourceText.Replace($before, 'commandLine("cmd", "/c", "echo", "Windows shellserver compiled with JDK 21 and D8")')
[System.IO.File]::WriteAllText($gradle, $patched, [System.Text.UTF8Encoding]::new($false))
Write-Host 'WINDOWS_GRADLE_SHELLSERVER_BRIDGE: PASS'
