<?php
require '/home/appontrack/iovoice-app/vendor/autoload.php';
$app = require '/home/appontrack/iovoice-app/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Mobile\CallLedgerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

echo "=== 1. VERIFY AI RELAY ENTRIES ===\n";
$keys = DB::table('ai_relay_entries')->where('account_id', 1)->get();
foreach ($keys as $k) {
    echo "ID: {$k->id} | Driver: {$k->driver} | Label: {$k->label} | Model: {$k->model} | Enabled: {$k->enabled}\n";
}

echo "\n=== 2. VERIFY CALLS LIST & TOP CALL ===\n";
$cls = app(CallLedgerService::class);
$paginator = $cls->filteredQuery(1, [])->paginate(20);
echo "Total authentic calls: " . $paginator->total() . "\n";
$firstCall = $paginator->items()[0] ?? null;
if ($firstCall) {
    echo "First Call in list: ID {$firstCall->id} | Phone: {$firstCall->phone} | Name: {$firstCall->name} | Duration: {$firstCall->duration_seconds}s\n";
    echo "  Summary: " . mb_substr($firstCall->summary ?? '', 0, 70) . "...\n";
}

echo "\n=== 3. VERIFY REAL TRANSCRIPTS FOR CALLS WITH TRANSCRIPT ===\n";
// Let's test call 75 (Mahmoud Mansour)
try {
    $detail75 = $cls->callDetail(1, 75);
    echo "Call 75 Detail:\n";
    echo "  Phone: " . $detail75['call']['phone'] . " | Name: " . $detail75['call']['name'] . "\n";
    echo "  Transcript len: " . strlen($detail75['transcript'] ?? '') . "\n";
    echo "  Transcript sample: " . mb_substr($detail75['transcript'] ?? '', 0, 100) . "...\n";
} catch (\Throwable $e) {
    echo "Call 75 Error: " . $e->getMessage() . "\n";
}

// Let's test call 60 (Ahmed Seef)
try {
    $detail60 = $cls->callDetail(1, 60);
    echo "Call 60 Detail:\n";
    echo "  Phone: " . $detail60['call']['phone'] . " | Name: " . $detail60['call']['name'] . "\n";
    echo "  Transcript len: " . strlen($detail60['transcript'] ?? '') . "\n";
    echo "  Transcript sample: " . mb_substr($detail60['transcript'] ?? '', 0, 100) . "...\n";
} catch (\Throwable $e) {
    echo "Call 60 Error: " . $e->getMessage() . "\n";
}

echo "\n=== 4. VERIFY DEVICE BINDINGS ===\n";
$devs = DB::table('device_bindings')->where('account_id', 1)->get();
foreach ($devs as $d) {
    echo "ID: {$d->id} | Device: {$d->device_name} | Slot: SIM " . ($d->sim_slot + 1) . " | Agent ID: {$d->agent_id} | Status: {$d->status}\n";
}
