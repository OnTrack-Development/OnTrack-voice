<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\SocialConnection;
use App\Models\TenantMembership;
use App\Models\Workspace;
use App\Support\ChannelCapabilities;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class SocialController extends Controller
{
    public function index(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);
        return view('app.channels.social.index', [
            'workspace' => $ws,
            'channels' => ChannelCapabilities::all(),
            'connections' => SocialConnection::where('tenant_id', $ws->tenant_id)->where('workspace_id', $ws->id)->latest()->get(),
        ]);
    }

    public function store(Request $request, string $workspace)
    {
        [$ws] = $this->context($request, $workspace);

        $data = $request->validate([
            'channel' => ['required', 'string'],
            'account_label' => ['required', 'string', 'max:160'],
            'external_account_id' => ['nullable', 'string', 'max:200'],
            'bot_token' => ['nullable', 'string', 'max:2000'],
            'page_token' => ['nullable', 'string', 'max:2000'],
        ], [], [
            'account_label' => 'تسمية الحساب',
            'bot_token' => 'Bot Token',
            'page_token' => 'Page Token',
        ]);

        $capabilities = ChannelCapabilities::for($data['channel']);
        abort_unless($capabilities !== null, 422, 'قناة غير معروفة.');
        abort_unless($capabilities['enabled'], 422, 'هذه القناة لم تُفعّل بعد رسميًا (متطلب Meta/أذونات خارجية).');

        $config = [];
        if (! blank($data['bot_token'] ?? null)) $config['bot_token'] = $data['bot_token'];
        if (! blank($data['page_token'] ?? null)) $config['page_token'] = $data['page_token'];
        if ($data['channel'] === 'telegram' && empty($config['bot_token'])) {
            return back()->withErrors(['bot_token' => 'Telegram يتطلب Bot Token من @BotFather.']);
        }

        $externalId = $data['external_account_id'] ?? ($data['channel'].':'.str()->random(12));

        $connection = SocialConnection::create([
            'tenant_id' => $ws->tenant_id,
            'workspace_id' => $ws->id,
            'channel' => $data['channel'],
            'account_label' => $data['account_label'],
            'external_account_id' => $externalId,
            'encrypted_config' => $config ? Crypt::encryptString(json_encode($config, JSON_UNESCAPED_SLASHES)) : null,
            'encrypted_webhook_secret' => Crypt::encryptString(str()->random(48)),
            'state' => 'draft',
            'capabilities' => $capabilities['capabilities'],
            'status_reason_redacted' => 'محفوظ كمسودة. لا اتصال حي قبل webhook واردة وتأكيد إرسال.',
        ]);

        return back()->with('status', 'تم حفظ اتصال '.$connection->account_label.' كمسودة. لا يوجد أي ادعاء اتصال حي.');
    }

    public function revoke(Request $request, string $workspace, SocialConnection $connection)
    {
        [$ws] = $this->context($request, $workspace);
        abort_unless($connection->tenant_id === $ws->tenant_id, 404);
        $connection->delete();
        return back()->with('status', 'تم فصل القناة وحذف بياناتها المشفرة.');
    }

    private function context(Request $request, string $workspace): array
    {
        $ws = Workspace::where('slug', $workspace)->firstOrFail();
        $member = TenantMembership::where('user_id', $request->user()->id)
            ->where('tenant_id', $ws->tenant_id)->where('status', 'active')->first();
        abort_if(! $member, 403, 'غير مصرح لك بالوصول إلى هذه المساحة.');
        return [$ws, $member];
    }
}