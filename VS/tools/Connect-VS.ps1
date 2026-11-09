#Requires -Version 5.1
<#
.SYNOPSIS
  Connects VS' independent shell bridge using an authorized USB or wireless ADB device.
.DESCRIPTION
  Does not use root, Shizuku, Accessibility, call recording, or the OnTrack
  Voice app. Requires installed Android platform-tools (adb) on the host.
  ADB pairing uses the code displayed in Android > Wireless debugging.
.EXAMPLE
  .\Connect-VS.ps1 -Mode USB
.EXAMPLE
  .\Connect-VS.ps1 -Mode Wireless -PairAddress 192.168.1.20:37123 -DeviceAddress 192.168.1.20:38421
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)]
    [ValidateSet('USB','Wireless')]
    [string]$Mode,
    [string]$PairAddress,
    [string]$DeviceAddress,
    [string]$DeviceSerial
)
$ErrorActionPreference='Stop'
if (-not (Get-Command adb -ErrorAction SilentlyContinue)) {
    throw 'Android platform-tools (adb) is not on PATH. Install Android SDK platform-tools on this computer.'
}
function Confirm-Address([string]$value,[string]$label) {
    if ($value -notmatch '^(?:[0-9]{1,3}\.){3}[0-9]{1,3}:[0-9]{2,5}$') {
        throw "$label must be the phone IPv4:port shown in Wireless debugging"
    }
}
if ($Mode -eq 'Wireless') {
    Confirm-Address $DeviceAddress 'DeviceAddress'
    if ($PairAddress) {
        Confirm-Address $PairAddress 'PairAddress'
        $code=Read-Host 'Enter the six-digit Android Wireless debugging pairing code'
        if ($code -notmatch '^\d{6}$') { throw 'Invalid pairing code' }
        & adb pair $PairAddress $code
        if ($LASTEXITCODE -ne 0) { throw 'ADB pairing failed' }
        Remove-Variable code
    }
    & adb connect $DeviceAddress
    if ($LASTEXITCODE -ne 0) { throw 'ADB wireless connect failed' }
    $DeviceSerial=$DeviceAddress
}
$devices=@(& adb devices | Select-Object -Skip 1 |
    Where-Object { $_ -match '^([^\s]+)\s+device\b' } |
    ForEach-Object { $Matches[1] })
if (-not $DeviceSerial) {
    if ($devices.Count -ne 1) {
        throw "Expected exactly one authorized Android device; found $($devices.Count). Specify -DeviceSerial."
    }
    $DeviceSerial=$devices[0]
}
if ($devices -notcontains $DeviceSerial) {
    throw "Device '$DeviceSerial' isn't authorized. Approve the USB debugging prompt or reconnect wireless ADB."
}
Write-Host "Authorized ADB device: $DeviceSerial"
Write-Host 'On the phone open VS > تجهيز الجسر ونسخ أمر ADB, then paste that command below.'
$command=Read-Host 'Paste the VS bridge setup command'
$pattern='^adb shell "cp (?<source>/[A-Za-z0-9_./-]+) /data/local/tmp/vs-bridge\.jar && chmod 600 /data/local/tmp/vs-bridge\.jar && CLASSPATH=/data/local/tmp/vs-bridge\.jar app_process / com\.ontrack\.vs\.shell\.VsTelephonyShell (?<token>[A-Za-z0-9_-]{40,100}) >/dev/null 2>&1 &"$'
if ($command -cnotmatch $pattern) {
    throw 'VS command is missing or does not match the expected independent bridge. Nothing was executed.'
}
$source=$Matches['source']
$token=$Matches['token']
if (-not $source.EndsWith('/Android/data/com.ontrack.vs/files/vs-bridge.jar')) {
    throw 'Source path is not the VS APK private bridge export. Refusing.'
}
# Construct the shell command only from validated paths/token; NEVER execute
# arbitrary text pasted from clipboard and never echo token to terminal/log.
$script="cp '$source' /data/local/tmp/vs-bridge.jar && chmod 600 /data/local/tmp/vs-bridge.jar && (CLASSPATH=/data/local/tmp/vs-bridge.jar app_process / com.ontrack.vs.shell.VsTelephonyShell '$token' </dev/null >/dev/null 2>&1 &)"
$token=$null
$command=$null
& adb -s $DeviceSerial shell $script
if ($LASTEXITCODE -ne 0) {
    throw 'Failed to launch VS bridge. Check debugging authorization and VS install.'
}
Write-Host 'VS shell helper launch requested. On phone, tap VS > فحص ربط ADB.'
Write-Host 'A successful ADB connection is NOT proof that the other caller receives Fish audio.'
