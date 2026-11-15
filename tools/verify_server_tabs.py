import os
import subprocess
from pathlib import Path

def main():
    ssh_key = Path(os.environ["USERPROFILE"]) / ".ssh" / "ontrack_publish_ed25519"
    php_code = """<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\\Contracts\\Console\\Kernel::class);
$kernel->bootstrap();

$user = App\\Models\\User::first();
Illuminate\\Support\\Facades\\Auth::login($user);
session(['account_id' => 1, 'authenticated_account_id' => 1]);

$wc = app(App\\Http\\Controllers\\Enterprise\\WorkspaceController::class);

$r1 = $wc->tab(Illuminate\\Http\\Request::create('/?tab=calls', 'GET', ['tab'=>'calls']), 'calls');
$htmlCalls = $r1->render();
echo "Calls Tab Rendered: " . strlen($htmlCalls) . " bytes\\n";

// Check if +201033990988, transcript modal, and waveform are present
echo "Contains +201033990988: " . (str_contains($htmlCalls, '+201033990988') ? 'YES' : 'NO') . "\\n";
echo "Contains NO ANSWERED: " . (str_contains($htmlCalls, 'NO ANSWERED') ? 'YES' : 'NO') . "\\n";
echo "Contains Latest call: " . (str_contains($htmlCalls, 'Latest call') ? 'YES' : 'NO') . "\\n";
echo "Contains Transcript Modal: " . (str_contains($htmlCalls, 'ontrackTranscriptModal') ? 'YES' : 'NO') . "\\n";
echo "Contains AI AGENT: " . (str_contains($htmlCalls, 'AI AGENT') ? 'YES' : 'NO') . "\\n";
echo "Contains CUSTOMER: " . (str_contains($htmlCalls, 'CUSTOMER') ? 'YES' : 'NO') . "\\n";
echo "Contains Link to Existing Lead: " . (str_contains($htmlCalls, 'Link to Existing Lead') ? 'YES' : 'NO') . "\\n";

$r2 = $wc->tab(Illuminate\\Http\\Request::create('/?tab=messages', 'GET', ['tab'=>'messages']), 'messages');
$htmlMessages = $r2->render();
echo "Messages Tab Rendered: " . strlen($htmlMessages) . " bytes\\n";
echo "Contains Messages Header: " . (str_contains($htmlMessages, 'Messages') ? 'YES' : 'NO') . "\\n";
echo "Contains Select a conversation: " . (str_contains($htmlMessages, 'Select a conversation') ? 'YES' : 'NO') . "\\n";
echo "Contains No conversations yet: " . (str_contains($htmlMessages, 'No conversations yet') ? 'YES' : 'NO') . "\\n";
"""
    tmp = Path(r"C:\OnTrack-voice\tools\temp_verify_tabs.php")
    tmp.write_text(php_code, encoding="utf-8")

    scp_cmd = [
        "scp", "-P", "7195", "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        str(tmp),
        "appontrack@65.21.208.232:/home/appontrack/iovoice-app/temp_verify_tabs.php"
    ]
    subprocess.run(scp_cmd, check=True)

    ssh_cmd = [
        "ssh", "-p", "7195", "-i", str(ssh_key),
        "-o", "StrictHostKeyChecking=no",
        "appontrack@65.21.208.232",
        "cd /home/appontrack/iovoice-app && /usr/local/bin/ea-php82 temp_verify_tabs.php && rm -f temp_verify_tabs.php"
    ]
    res = subprocess.run(ssh_cmd, capture_output=True, text=True)
    print("OUTPUT:\n", res.stdout)
    if res.stderr:
        print("ERROR:\n", res.stderr)

    if tmp.exists():
        tmp.unlink()

if __name__ == "__main__":
    main()
