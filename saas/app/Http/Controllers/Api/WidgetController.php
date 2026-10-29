<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\WidgetKey;
use App\Models\WidgetSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class WidgetController extends Controller
{
    public function config(string $publicKey)
    {
        $key = WidgetKey::where('public_key', $publicKey)->where('status', 'active')->with('project')->firstOrFail();
        return response()->json([
            'public_key' => $key->public_key,
            'project' => ['name' => $key->project->name],
            'capabilities' => ['messages' => true, 'smart_actions' => true],
            'demo' => true,
        ]);
    }

    public function message(Request $request, string $publicKey)
    {
        $key = WidgetKey::where('public_key', $publicKey)->where('status', 'active')->firstOrFail();
        $data = $request->validate(['session_token'=>['required','string','max:300'],'body'=>['required','string','max:10000']]);
        $session = WidgetSession::where('widget_key_id',$key->id)->where('tenant_id',$key->tenant_id)->where('session_hash',hash('sha256',$data['session_token']))->firstOrFail();
        $conversation = $session->conversation;
        abort_unless($conversation && $conversation->tenant_id === $key->tenant_id, 404);
        if ($conversation->isHumanLocked() || $conversation->isAiPaused()) {
            return response()->json(['status'=>'human_takeover','message'=>'المحادثة متاحة لفريق الموظفين حاليًا.'], 409);
        }
        Message::create(['tenant_id'=>$key->tenant_id,'conversation_id'=>$conversation->id,'role'=>'customer','body'=>$data['body'],'delivery_status'=>'accepted','occurred_at'=>now()]);
        $session->update(['last_seen_at'=>now()]);
        return response()->json(['status'=>'accepted','message'=>'تم استلام رسالتك.'], 202);
    }

    public function start(Request $request, string $publicKey)
    {
        $key = WidgetKey::where('public_key',$publicKey)->where('status','active')->firstOrFail();
        $token = Str::random(48);
        $conversation = Conversation::create(['tenant_id'=>$key->tenant_id,'workspace_id'=>$key->workspace_id,'project_id'=>$key->project_id,'contact_key'=>'widget:'.Str::random(16),'channel'=>'web','status'=>'open']);
        WidgetSession::create(['tenant_id'=>$key->tenant_id,'widget_key_id'=>$key->id,'conversation_id'=>$conversation->id,'session_hash'=>hash('sha256',$token),'last_seen_at'=>now()]);
        return response()->json(['session_token'=>$token,'conversation_id'=>$conversation->id], 201);
    }
}