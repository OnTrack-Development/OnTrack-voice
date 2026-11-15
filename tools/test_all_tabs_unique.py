import os
import sys
import subprocess
from pathlib import Path

def main():
    ssh_key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
    server_host = "appontrack@65.21.208.232"
    server_port = "7195"

    local_test_file = Path(r"C:\OnTrack-voice\tools\remote_tab_test.php")
    with open(local_test_file, "w", encoding="utf-8") as f:
        f.write(r"""<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('email', 'admin@ontrackegy.com')->first() ?: \App\Models\User::first();
if (!$user) {
    echo "ERROR: No user found in database\n";
    exit(1);
}

echo "Authenticated as: " . $user->email . " (ID: " . $user->id . ")\n\n";

$tabsToCheck = [
    'overview' => 'نظرة عامة على التشغيل',
    'leads' => 'العملاء المحتملون (Leads CRM)',
    'appointments' => 'المواعيد وجدولة الاتصالات',
    'reports' => 'التقارير والتحليلات الشاملة',
    'calls' => 'المكالمات (Calls)',
    'messages' => 'Messages',
    'tickets' => 'تذاكر الدعم ومحرك الوكلاء المتعددين',
    'widget' => 'مُنشئ ومُخصص ودجت الموقع',
    'power_dialer' => 'المتصل الآلي السريع',
    'workflows' => 'التدفقات ومحرك الأتمتة',
    'integrations' => 'التكاملات وموديول الربط الخفيف',
    'activity' => 'النشاط والتعلم الذاتي المستمر',
];

$failed = false;

foreach ($tabsToCheck as $tabKey => $uniqueMarker) {
    $request = \Illuminate\Http\Request::create('/?tab=' . $tabKey, 'GET');
    $app->instance('request', $request);
    \Illuminate\Support\Facades\Auth::login($user);
    $request->setUserResolver(fn () => $user);
    
    $response = $kernel->handle($request);
    $status = $response->getStatusCode();
    $content = $response->getContent();
    
    if ($status !== 200) {
        echo "[-] Tab [{$tabKey}] FAIL: Status code {$status}\n";
        $failed = true;
        continue;
    }
    
    $hasMarker = str_contains($content, $uniqueMarker);
    $hasOverview = str_contains($content, 'نظرة عامة على التشغيل');
    
    if (!$hasMarker) {
        echo "[-] Tab [{$tabKey}] FAIL: Missing unique marker '{$uniqueMarker}'\n";
        $failed = true;
    } elseif ($tabKey !== 'overview' && $hasOverview) {
        echo "[-] Tab [{$tabKey}] FAIL: Illegally contains 'نظرة عامة على التشغيل' (Broken Tab Routing)\n";
        $failed = true;
    } else {
        echo "[+] Tab [{$tabKey}] PASS: Status 200, Unique marker verified, No overview bleed\n";
    }
}

if ($failed) {
    echo "\n=== SOME TABS FAILED VERIFICATION ===\n";
    exit(1);
} else {
    echo "\n=== ALL 12 TABS PASSED 100% INDEPENDENTLY ===\n";
    exit(0);
}
""")

    print("Uploading test script to server via SCP...")
    scp_cmd = [
        "scp", "-P", server_port, "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        str(local_test_file),
        f"{server_host}:/home/appontrack/iovoice-app/remote_tab_test.php"
    ]
    subprocess.run(scp_cmd, check=True)

    print("Executing live tabs verification on server...")
    ssh_cmd = [
        "ssh", "-p", server_port, "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        server_host,
        "/opt/cpanel/ea-php82/root/usr/bin/php /home/appontrack/iovoice-app/remote_tab_test.php"
    ]
    res = subprocess.run(ssh_cmd, capture_output=True, text=True)
    print("OUTPUT:\n", res.stdout)
    if res.stderr:
        print("STDERR:\n", res.stderr)

    # Cleanup remote script
    subprocess.run([
        "ssh", "-p", server_port, "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        server_host,
        "rm -f /home/appontrack/iovoice-app/remote_tab_test.php"
    ])

    if local_test_file.exists():
        local_test_file.unlink()

    if res.returncode != 0:
        print("Verification FAILED!")
        sys.exit(1)
    else:
        print("Verification SUCCESSFUL!")

if __name__ == "__main__":
    main()
